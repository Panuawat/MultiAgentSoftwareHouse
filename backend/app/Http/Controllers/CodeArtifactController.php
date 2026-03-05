<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CodeArtifactController extends Controller
{
    public function index(Request $request, Task $task): JsonResponse
    {
        $versionParam = $request->query('version');

        if ($versionParam === 'all') {
            // Return every artifact across all versions
            $artifacts = $task->codeArtifacts()->orderBy('version')->orderBy('filename')->get();
        } elseif ($versionParam !== null && is_numeric($versionParam)) {
            // Return artifacts for a specific version
            $artifacts = $task->codeArtifacts()
                ->where('version', (int) $versionParam)
                ->orderBy('filename')
                ->get();
        } else {
            // Default: latest version only (max version per filename)
            $latestVersion = $task->codeArtifacts()->max('version') ?? 1;
            $artifacts = $task->codeArtifacts()
                ->where('version', $latestVersion)
                ->orderBy('filename')
                ->get();
        }

        return response()->json(['artifacts' => $artifacts]);
    }

    public function versions(Task $task): JsonResponse
    {
        $versions = $task->codeArtifacts()
            ->selectRaw('version, MIN(created_at) as created_at, COUNT(*) as file_count')
            ->groupBy('version')
            ->orderBy('version')
            ->get();

        // Attach QA result from agent_logs for each version (version = retry_count + 1)
        // Exclude 'running' status — only keep the final result log per attempt
        $qaLogs = $task->agentLogs()
            ->where('agent_type', 'qa')
            ->whereIn('status', ['success', 'failed'])
            ->orderBy('id')
            ->get(['status', 'created_at'])
            ->values();

        $versionsWithMeta = $versions->map(function ($v) use ($qaLogs) {
            $qaIndex = $v->version - 1; // version 1 = first QA attempt (index 0)
            $qaStatus = $qaLogs->get($qaIndex)?->status ?? null;

            return [
                'version'    => $v->version,
                'file_count' => $v->file_count,
                'created_at' => $v->created_at,
                'qa_result'  => $qaStatus, // 'success' | 'failed' | null
            ];
        });

        return response()->json(['versions' => $versionsWithMeta]);
    }

    public function export(Task $task)
    {
        $latestVersion = $task->codeArtifacts()->max('version') ?? 1;
        $artifacts = $task->codeArtifacts()->where('version', $latestVersion)->get();

        if ($artifacts->isEmpty()) {
            return response()->json(['message' => 'No code artifacts to export'], 404);
        }

        $zipPath = storage_path('app/temp-task-'.$task->id.'.zip');
        $zip = new \ZipArchive();

        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return response()->json(['message' => 'Failed to create ZIP file'], 500);
        }

        $existingFiles = $artifacts->pluck('filename')
            ->map(fn($f) => ltrim($f, '/'))
            ->all();

        foreach ($artifacts as $artifact) {
            $zip->addFromString(ltrim($artifact->filename, '/'), $artifact->content);
        }

        // Inject missing Next.js boilerplate files so the project runs out of the box
        $boilerplate = $this->nextjsBoilerplate();
        foreach ($boilerplate as $filename => $content) {
            if (!in_array($filename, $existingFiles, true)) {
                $zip->addFromString($filename, $content);
            }
        }

        $zip->close();

        return response()->download($zipPath, 'task-'.$task->id.'-code.zip')->deleteFileAfterSend(true);
    }

    /** Standard Next.js 14 + TypeScript + Tailwind boilerplate files */
    private function nextjsBoilerplate(): array
    {
        return [
            'package.json' => json_encode([
                'name'    => 'openclaw-project',
                'version' => '0.1.0',
                'private' => true,
                'scripts' => [
                    'dev'   => 'next dev',
                    'build' => 'next build',
                    'start' => 'next start',
                ],
                'dependencies' => [
                    'next'      => '14.2.0',
                    'react'     => '^18',
                    'react-dom' => '^18',
                ],
                'devDependencies' => [
                    'typescript'       => '^5',
                    '@types/node'      => '^20',
                    '@types/react'     => '^18',
                    '@types/react-dom' => '^18',
                    'tailwindcss'      => '^3.4.0',
                    'postcss'          => '^8',
                    'autoprefixer'     => '^10.0.1',
                ],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),

            'tsconfig.json' => json_encode([
                'compilerOptions' => [
                    'lib'              => ['dom', 'dom.iterable', 'esnext'],
                    'allowJs'          => true,
                    'skipLibCheck'     => true,
                    'strict'           => true,
                    'noEmit'           => true,
                    'esModuleInterop'  => true,
                    'module'           => 'esnext',
                    'moduleResolution' => 'bundler',
                    'resolveJsonModule'=> true,
                    'isolatedModules'  => true,
                    'jsx'              => 'preserve',
                    'incremental'      => true,
                    'plugins'          => [['name' => 'next']],
                    'paths'            => ['@/*' => ['./*']],
                ],
                'include' => ['next-env.d.ts', '**/*.ts', '**/*.tsx', '.next/types/**/*.ts'],
                'exclude' => ['node_modules'],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),

            'next.config.js' => <<<'JS'
/** @type {import('next').NextConfig} */
const nextConfig = {}

module.exports = nextConfig
JS,

            'postcss.config.js' => <<<'JS'
module.exports = {
  plugins: {
    tailwindcss: {},
    autoprefixer: {},
  },
}
JS,

            'README.md' => <<<'MD'
# OpenClaw Generated Project

## Getting Started

```bash
npm install
npm run dev
```

Open [http://localhost:3000](http://localhost:3000) to view the app.
MD,
        ];
    }
}

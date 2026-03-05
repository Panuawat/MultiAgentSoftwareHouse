<?php

namespace App\Jobs;

use App\Models\AgentLog;
use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class PushToGithubJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;
    public int $tries = 1;

    public function __construct(public readonly int $taskId)
    {
    }

    public function handle(): void
    {
        $task = Task::with('project')->findOrFail($this->taskId);
        $projectId = $task->project_id;

        // Collect latest version of each artifact
        $artifacts = $task->codeArtifacts()
            ->orderBy('version', 'desc')
            ->get()
            ->unique('filename');

        if ($artifacts->isEmpty()) {
            $this->log($task, 'skipped', 'No code artifacts to push.');
            return;
        }

        $repoDir = storage_path("app/projects/project_{$projectId}");

        try {
            // 1. Write artifacts to disk
            $this->writeArtifacts($repoDir, $artifacts);

            // 2. Initialize git repo if needed
            $this->ensureGitRepo($repoDir);

            // 3. Commit and push
            $output = $this->commitAndPush($repoDir, $task);

            $this->log($task, 'success', $output);

            // 4. Notify via Telegram
            app(\App\Services\TelegramService::class)->notifyGithubPush(
                $task->id,
                $task->title,
                true
            );
        } catch (\Throwable $e) {
            $errorMsg = $e->getMessage();
            Log::warning("PushToGithubJob failed for Task #{$this->taskId}: {$errorMsg}");
            $this->log($task, 'failed', $errorMsg);

            app(\App\Services\TelegramService::class)->notifyGithubPush(
                $task->id,
                $task->title,
                false,
                $errorMsg
            );
        }
    }

    private function writeArtifacts(string $dir, $artifacts): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        foreach ($artifacts as $artifact) {
            $filePath = $dir . '/' . $artifact->filename;
            $fileDir = dirname($filePath);
            if (!is_dir($fileDir)) {
                mkdir($fileDir, 0755, true);
            }
            file_put_contents($filePath, $artifact->content);
        }
    }

    private function ensureGitRepo(string $dir): void
    {
        if (!is_dir("{$dir}/.git")) {
            $this->git($dir, 'init');
            $this->git($dir, 'checkout -b ' . config('app.github_branch', 'main'));

            $remoteUrl = config('app.github_remote_url');
            if (!empty($remoteUrl)) {
                $this->git($dir, "remote add origin {$remoteUrl}");
            }
        }
    }

    private function commitAndPush(string $dir, Task $task): string
    {
        $this->git($dir, 'add .');

        // Check if there are changes to commit
        $status = $this->git($dir, 'status --porcelain');
        if (empty(trim($status))) {
            return 'No changes to commit — code is identical to previous push.';
        }

        $message = "feat(ai): Auto-generated code for Task #{$task->id} - {$task->title}";
        $this->git($dir, "commit -m \"{$message}\"");

        $branch = config('app.github_branch', 'main');
        $remoteUrl = config('app.github_remote_url');

        if (!empty($remoteUrl)) {
            $pushOutput = $this->git($dir, "push -u origin {$branch}");
            return "Committed and pushed to {$branch}.\n{$pushOutput}";
        }

        return "Committed locally (no remote configured).";
    }

    private function git(string $dir, string $command): string
    {
        $result = Process::path($dir)->timeout(30)->run("git {$command}");

        if ($result->failed()) {
            $error = $result->errorOutput() ?: $result->output();
            throw new \RuntimeException("git {$command} failed: {$error}");
        }

        return $result->output();
    }

    private function log(Task $task, string $status, string $message): void
    {
        AgentLog::create([
            'task_id'     => $task->id,
            'agent_type'  => 'system',
            'input'       => ['action' => 'github_push', 'project_id' => $task->project_id],
            'output'      => ['message' => $message, 'status' => $status],
            'tokens_used' => 0,
            'status'      => $status,
        ]);
    }
}

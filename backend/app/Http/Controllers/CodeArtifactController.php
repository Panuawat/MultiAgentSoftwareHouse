<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\JsonResponse;

class CodeArtifactController extends Controller
{
    public function index(Task $task): JsonResponse
    {
        $artifacts = $task->codeArtifacts()->orderBy('created_at', 'asc')->get();

        return response()->json(['artifacts' => $artifacts]);
    }

    public function export(Task $task)
    {
        $artifacts = $task->codeArtifacts()->get();

        if ($artifacts->isEmpty()) {
            return response()->json(['message' => 'No code artifacts to export'], 404);
        }

        $zipPath = storage_path('app/temp-task-'.$task->id.'.zip');
        $zip = new \ZipArchive();

        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
            foreach ($artifacts as $artifact) {
                // Remove leading slash if any
                $filename = ltrim($artifact->filename, '/');
                $zip->addFromString($filename, $artifact->content);
            }
            $zip->close();
        }

        return response()->download($zipPath, 'task-'.$task->id.'-code.zip')->deleteFileAfterSend(true);
    }
}

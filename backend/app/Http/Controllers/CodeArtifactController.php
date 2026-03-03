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
}

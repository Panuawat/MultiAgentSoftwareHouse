<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\JsonResponse;

class AgentLogController extends Controller
{
    public function index(Task $task): JsonResponse
    {
        $logs = $task->agentLogs()->orderBy('created_at', 'asc')->get();

        return response()->json(['logs' => $logs]);
    }
}

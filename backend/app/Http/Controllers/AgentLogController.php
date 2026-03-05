<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\JsonResponse;

class AgentLogController extends Controller
{
    public function index(Task $task): JsonResponse
    {
        $logs = $task->agentLogs()
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn ($log) => [
                'id'          => $log->id,
                'task_id'     => $log->task_id,
                'agent'       => $log->agent_type,
                'message'     => json_encode($log->output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                'status'      => $log->status,
                'tokens_used' => $log->tokens_used,
                'created_at'  => $log->created_at,
            ]);

        return response()->json(['logs' => $logs]);
    }
}

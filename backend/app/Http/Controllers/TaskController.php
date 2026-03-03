<?php

namespace App\Http\Controllers;

use App\Jobs\PmAgentJob;
use App\Models\Project;
use App\Models\Task;
use App\Services\StateMachineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Task::query();

        if ($request->has('project_id')) {
            $query->where('project_id', $request->integer('project_id'));
        }

        return response()->json(['tasks' => $query->get()]);
    }

    public function indexByProject(Project $project): JsonResponse
    {
        return response()->json(['tasks' => $project->tasks]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_id'   => 'required|integer|exists:projects,id',
            'title'        => 'required|string|max:255',
            'description'  => 'nullable|string',
            'token_budget' => 'nullable|integer|min:1',
        ]);

        $validated['token_budget'] = $validated['token_budget'] ?? 10000;
        $validated['status'] = 'pending';

        $task = Task::create($validated);

        return response()->json(['task' => $task], 201);
    }

    public function show(Task $task): JsonResponse
    {
        $task->load(['agentLogs', 'codeArtifacts']);

        return response()->json(['task' => $task]);
    }

    public function start(Task $task, StateMachineService $sm): JsonResponse
    {
        if ($task->status !== 'pending') {
            return response()->json([
                'message' => 'Task cannot be started: current status is '.$task->status,
            ], 422);
        }

        $task = $sm->transition($task->id, 'pm_processing');
        PmAgentJob::dispatch($task->id);

        return response()->json(['task' => $task], 202);
    }

    public function cancel(Task $task, StateMachineService $sm): JsonResponse
    {
        $task = $sm->transition($task->id, 'cancelled');

        return response()->json(['task' => $task]);
    }
}

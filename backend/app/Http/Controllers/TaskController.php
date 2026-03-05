<?php

namespace App\Http\Controllers;

use App\Jobs\PmAgentJob;
use App\Jobs\UxAgentJob;
use App\Jobs\DevAgentJob;
use App\Models\Project;
use App\Models\Task;
use App\Services\StateMachineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            'project_id'        => 'required|integer|exists:projects,id',
            'title'             => 'required|string|max:255',
            'description'       => 'nullable|string',
            'token_budget'      => 'nullable|integer|min:1',
            'pm_review_enabled' => 'nullable|boolean',
        ]);

        $validated['token_budget']      = $validated['token_budget'] ?? 10000;
        $validated['status']            = 'pending';
        $validated['pm_review_enabled'] = $validated['pm_review_enabled'] ?? false;

        $task = Task::create($validated);

        return response()->json(['task' => $task], 201);
    }

    public function show(Task $task): JsonResponse
    {
        $task->load(['agentLogs', 'codeArtifacts']);

        $estimatedCost = $this->calculateCost($task);

        return response()->json(['task' => array_merge($task->toArray(), [
            'estimated_cost_usd' => $estimatedCost,
        ])]);
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

    public function resume(Request $request, Task $task, StateMachineService $sm): JsonResponse
    {
        if ($task->status !== 'human_review_required') {
            return response()->json([
                'message' => 'Task cannot be resumed unless it is in human_review_required state.',
            ], 422);
        }

        $validated = $request->validate([
            'token_budget' => 'nullable|integer|min:1',
        ]);

        return DB::transaction(function () use ($task, $validated) {
            $task = Task::where('id', $task->id)->lockForUpdate()->firstOrFail();

            if (isset($validated['token_budget']) && $validated['token_budget'] > $task->token_budget) {
                $task->token_budget = $validated['token_budget'];
            }

            // Determine which job failed by looking at the last agent log
            $lastLog = $task->agentLogs()->orderBy('id', 'desc')->first();
            $targetState = 'pm_processing';

            if ($lastLog) {
                match ($lastLog->agent_type) {
                    'pm'  => $targetState = 'pm_processing',
                    'ux'  => $targetState = 'ux_processing',
                    'dev' => $targetState = ($lastLog->status === 'success') ? 'qa_testing' : 'dev_coding',
                    'qa'  => $targetState = 'qa_testing',
                    default => $targetState = 'pm_processing',
                };
            }

            // Force transition ignoring normal strict flow since we are resuming
            $task->status = $targetState;
            $task->agent_output = collect($task->agent_output ?? [])->except(['escalation_reason', 'escalated_at'])->toArray();
            $task->save();

            match ($targetState) {
                'pm_processing' => PmAgentJob::dispatch($task->id),
                'ux_processing' => UxAgentJob::dispatch($task->id),
                'dev_coding'    => DevAgentJob::dispatch($task->id),
                'qa_testing'    => \App\Jobs\QaAgentJob::dispatch($task->id),
            };

            return response()->json(['task' => $task], 202);
        });
    }

    public function pmChat(Request $request, Task $task, StateMachineService $sm): JsonResponse
    {
        if ($task->status !== 'pm_review') {
            return response()->json([
                'message' => 'PM chat is only available when task is in pm_review state.',
            ], 422);
        }

        $validated = $request->validate([
            'message' => 'required|string|max:2000',
        ]);

        // Append user message to pm_messages history
        $messages = $task->pm_messages ?? [];
        $messages[] = [
            'role'       => 'user',
            'content'    => $validated['message'],
            'created_at' => now()->toISOString(),
        ];
        $task->pm_messages = $messages;
        $task->save();

        // Transition back to pm_processing and re-run PM agent
        $task = $sm->transition($task->id, 'pm_processing');
        PmAgentJob::dispatch($task->id);

        return response()->json(['task' => $task->fresh()], 202);
    }

    public function pmApprove(Task $task, StateMachineService $sm): JsonResponse
    {
        if ($task->status !== 'pm_review') {
            return response()->json([
                'message' => 'PM approval is only available when task is in pm_review state.',
            ], 422);
        }

        $task = $sm->transition($task->id, 'ux_processing');
        UxAgentJob::dispatch($task->id);

        return response()->json(['task' => $task], 202);
    }

    private function calculateCost(Task $task): float
    {
        $geminiTokens = $task->agentLogs
            ->whereIn('agent_type', ['pm', 'dev'])
            ->sum('tokens_used');

        return round($geminiTokens / 1_000_000 * 0.10, 6);
    }
}

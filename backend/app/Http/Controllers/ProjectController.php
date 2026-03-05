<?php

namespace App\Http\Controllers;

use App\Models\AgentLog;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ProjectController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['projects' => Project::all()]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $project = Project::create($validated);

        return response()->json(['project' => $project], 201);
    }

    public function show(Project $project): JsonResponse
    {
        $project->load('tasks.agentLogs');

        $totalCost = $project->tasks->sum(function ($task) {
            $geminiTokens = $task->agentLogs
                ->whereIn('agent_type', ['pm', 'dev'])
                ->sum('tokens_used');

            return $geminiTokens / 1_000_000 * 0.10;
        });

        return response()->json(['project' => array_merge($project->toArray(), [
            'total_cost_usd' => round($totalCost, 6),
        ])]);
    }

    public function update(Request $request, Project $project): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $project->update($validated);

        return response()->json(['project' => $project]);
    }

    public function destroy(Project $project): Response
    {
        $project->delete();

        return response()->noContent();
    }
}

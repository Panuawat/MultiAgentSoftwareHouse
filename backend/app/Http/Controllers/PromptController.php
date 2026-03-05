<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PromptController extends Controller
{
    private const AGENTS = ['pm', 'ux', 'dev', 'qa'];

    public function index(): JsonResponse
    {
        $prompts = [];

        foreach (self::AGENTS as $agent) {
            $path = base_path("../orchestrator/prompts/{$agent}_system.txt");
            $prompts[$agent] = file_exists($path) ? file_get_contents($path) : '';
        }

        return response()->json(['prompts' => $prompts]);
    }

    public function update(Request $request, string $agent): JsonResponse
    {
        if (! in_array($agent, self::AGENTS, true)) {
            return response()->json(['message' => 'Invalid agent. Must be one of: '.implode(', ', self::AGENTS)], 422);
        }

        $validated = $request->validate([
            'content' => 'required|string',
        ]);

        $path = base_path("../orchestrator/prompts/{$agent}_system.txt");

        file_put_contents($path, $validated['content']);

        return response()->json(['message' => "Prompt for '{$agent}' saved successfully."]);
    }
}

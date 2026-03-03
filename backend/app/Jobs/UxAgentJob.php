<?php

namespace App\Jobs;

use App\Events\TaskStatusUpdated;
use App\Models\AgentLog;
use App\Models\Task;
use App\Services\OllamaService;
use App\Services\StateMachineService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class UxAgentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 180;

    public function __construct(public readonly int $taskId)
    {
    }

    public function handle(StateMachineService $sm): void
    {
        $task = Task::findOrFail($this->taskId);

        if ($task->status !== 'ux_processing') {
            return;
        }

        $pmOutput = $task->agent_output['pm'] ?? $task->agent_output ?? [];

        try {
            [$response, $tokensUsed] = config('app.agent_mode') === 'mock'
                ? [$this->getMockResponse('ux'), 100]
                : $this->callOllama($pmOutput);
        } catch (Throwable $e) {
            $this->escalate($task, $sm, $pmOutput, $e->getMessage());
            return;
        }

        // Merge UX output into agent_output alongside PM output
        $task->update([
            'agent_output' => array_merge($task->agent_output ?? [], ['ux' => $response]),
        ]);

        AgentLog::create([
            'task_id'     => $task->id,
            'agent_type'  => 'ux',
            'input'       => $pmOutput,
            'output'      => $response,
            'tokens_used' => $tokensUsed,
            'status'      => 'success',
        ]);

        event(new TaskStatusUpdated($task));

        $sm->transition($this->taskId, 'dev_coding');

        DevAgentJob::dispatch($this->taskId);
    }

    /** @return array{0: array, 1: int} */
    private function callOllama(array $pmOutput): array
    {
        $systemPrompt = file_get_contents(base_path('../orchestrator/prompts/ux_system.txt'));
        $userMessage  = 'PM Requirements: '.json_encode($pmOutput, JSON_PRETTY_PRINT);

        $result = app(OllamaService::class)->generate($systemPrompt, $userMessage);

        return [$result['content'], $result['tokens_used']];
    }

    private function getMockResponse(string $agent): array
    {
        $path = base_path("../orchestrator/mock/{$agent}_response.json");

        return json_decode(file_get_contents($path), true);
    }

    private function escalate(Task $task, StateMachineService $sm, array $input, string $reason): void
    {
        AgentLog::create([
            'task_id'     => $task->id,
            'agent_type'  => 'ux',
            'input'       => $input,
            'output'      => ['error' => $reason],
            'tokens_used' => 0,
            'status'      => 'failed',
        ]);

        $task->escalate('AGENT_ERROR: '.$reason);
        event(new TaskStatusUpdated($task->fresh()));
    }
}

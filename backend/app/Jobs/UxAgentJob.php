<?php

namespace App\Jobs;

use App\Events\TaskStatusUpdated;
use App\Exceptions\TokenBudgetExceededException;
use App\Models\AgentLog;
use App\Models\Task;
use App\Services\OllamaService;
use App\Services\StateMachineService;
use App\Services\TelegramService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class UxAgentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 240; // 60s buffer over Ollama's 180s timeout

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

        // 🔔 Group chat: UX started
        app(TelegramService::class)->notifyAgentStart($task->id, $task->title, 'ux');

        try {
            [$response, $tokensUsed] = config('app.agent_mode') === 'mock'
                ? [$this->getMockResponse('ux'), 100]
                : $this->callOllama($pmOutput);
        } catch (Throwable $e) {
            $this->escalate($task, $pmOutput, $e->getMessage());
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

        // 🔔 Group chat: UX completed
        app(TelegramService::class)->notifyAgentComplete($task->id, $task->title, 'ux', [
            'components' => count($response['components'] ?? []),
        ]);

        // Transition first, then fire event with the updated state
        try {
            $updated = $sm->transition($this->taskId, 'dev_coding');
            event(new TaskStatusUpdated($updated));
            DevAgentJob::dispatch($this->taskId);
        } catch (TokenBudgetExceededException $e) {
            $this->escalate($task, $pmOutput, "Token budget exceeded ({$e->getMessage()})");
        } catch (Throwable $e) {
            $this->escalate($task, $pmOutput, $e->getMessage());
        }
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

    private function escalate(Task $task, array $input, string $reason): void
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

        // 🔔 Telegram: human review needed (private DM + group)
        app(TelegramService::class)->notifyHumanReviewRequired($task->id, $task->title, $reason);
        app(TelegramService::class)->notifyAgentError($task->id, $task->title, 'ux', $reason);
    }
}

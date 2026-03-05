<?php

namespace App\Jobs;

use App\Events\TaskStatusUpdated;
use App\Exceptions\TokenBudgetExceededException;
use App\Models\AgentLog;
use App\Models\CodeArtifact;
use App\Models\Task;
use App\Services\GeminiService;
use App\Services\StateMachineService;
use App\Services\TelegramService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Throwable;

class PmAgentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public function __construct(public readonly int $taskId)
    {
    }

    public function handle(StateMachineService $sm): void
    {
        $task = Task::findOrFail($this->taskId);

        if ($task->status !== 'pm_processing') {
            return;
        }

        try {
            [$response, $tokensUsed] = config('app.agent_mode') === 'mock'
                ? [$this->getMockResponse('pm'), 50]
                : $this->callGemini($task);
        } catch (Throwable $e) {
            $this->escalate($task, $sm, 'pm', $e->getMessage());
            return;
        }

        // Store PM output under 'pm' key so downstream agents can read it
        $task->update(['agent_output' => array_merge($task->agent_output ?? [], ['pm' => $response])]);

        // Append PM response to pm_messages history (for review mode)
        if ($task->pm_review_enabled) {
            $messages = $task->pm_messages ?? [];
            $messages[] = [
                'role'       => 'assistant',
                'content'    => $response,
                'created_at' => now()->toISOString(),
            ];
            $task->pm_messages = $messages;
            $task->save();
        }

        AgentLog::create([
            'task_id'     => $task->id,
            'agent_type'  => 'pm',
            'input'       => ['description' => $task->description],
            'output'      => $response,
            'tokens_used' => $tokensUsed,
            'status'      => 'success',
        ]);

        event(new TaskStatusUpdated($task->fresh()));

        try {
            if ($task->pm_review_enabled) {
                // Pause here — wait for user to review/approve in the dashboard
                $updated = $sm->transition($this->taskId, 'pm_review');
                event(new TaskStatusUpdated($updated));

                // Send Telegram notification with Approve/Revise buttons
                $telegramResponse = app(TelegramService::class)->notifyPmReview(
                    $task->id, $task->title, $response
                );
                if ($telegramResponse && isset($telegramResponse['result']['message_id'])) {
                    Cache::put("telegram_pm_review_msg:{$task->id}",
                        $telegramResponse['result']['message_id'], now()->addDays(1));
                }
            } else {
                $updated = $sm->transition($this->taskId, 'ux_processing');
                event(new TaskStatusUpdated($updated));
                UxAgentJob::dispatch($this->taskId);
            }
        } catch (TokenBudgetExceededException $e) {
            $this->escalate($task, $sm, 'pm', "Token budget exceeded ({$e->getMessage()})");
        } catch (Throwable $e) {
            $this->escalate($task, $sm, 'pm', $e->getMessage());
        }
    }

    /** @return array{0: array, 1: int} */
    private function callGemini(Task $task): array
    {
        $systemPrompt = file_get_contents(base_path('../orchestrator/prompts/pm_system.txt'));
        $userMessage  = 'User requirement: '.$task->description;

        // If this task continues from a previous task, provide existing code context
        if ($task->base_task_id) {
            $userMessage .= $this->buildBaseTaskContext($task->base_task_id);
        }

        // If there are prior chat messages, append revision context
        $pmMessages = $task->pm_messages ?? [];
        $userRevisions = array_filter($pmMessages, fn($m) => $m['role'] === 'user');
        if (! empty($userRevisions)) {
            $revisionText = implode("\n", array_map(fn($m) => 'Revision request: '.$m['content'], $userRevisions));
            $userMessage .= "\n\n".$revisionText;
        }

        $result = app(GeminiService::class)->generate($systemPrompt, $userMessage);

        return [$result['content'], $result['tokens_used']];
    }

    private function buildBaseTaskContext(int $baseTaskId): string
    {
        $artifacts = CodeArtifact::where('task_id', $baseTaskId)
            ->orderByDesc('version')
            ->get()
            ->unique('filename');

        if ($artifacts->isEmpty()) {
            return '';
        }

        $context = "\n\n--- EXISTING CODEBASE (from Task #{$baseTaskId}) ---\n";
        $context .= "This task modifies an existing codebase. Here is the current code:\n\n";

        foreach ($artifacts as $artifact) {
            $context .= "[File: {$artifact->filename}]\n";
            $context .= "<code>\n{$artifact->content}\n</code>\n\n";
        }

        $context .= "Please write a plan to modify this code to fulfill the new requirements.\n";

        return $context;
    }

    private function getMockResponse(string $agent): array
    {
        $path = base_path("../orchestrator/mock/{$agent}_response.json");

        return json_decode(file_get_contents($path), true);
    }

    private function escalate(Task $task, StateMachineService $sm, string $agentType, string $reason): void
    {
        AgentLog::create([
            'task_id'     => $task->id,
            'agent_type'  => $agentType,
            'input'       => ['description' => $task->description],
            'output'      => ['error' => $reason],
            'tokens_used' => 0,
            'status'      => 'failed',
        ]);

        $task->escalate('AGENT_ERROR: '.$reason);
        event(new TaskStatusUpdated($task->fresh()));

        // 🔔 Telegram: human review needed
        app(TelegramService::class)->notifyHumanReviewRequired($task->id, $task->title, $reason);
    }
}

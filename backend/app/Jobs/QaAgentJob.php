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

class QaAgentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 240; // 60s buffer over Ollama's 180s timeout

    public function __construct(public readonly int $taskId)
    {
    }

    public function handle(StateMachineService $sm): void
    {
        $task = Task::findOrFail($this->taskId);

        if ($task->status !== 'qa_testing') {
            return;
        }

        // Collect the latest version of each file from CodeArtifacts
        $artifacts = $task->codeArtifacts()
            ->orderBy('version', 'desc')
            ->get()
            ->unique('filename')
            ->map(fn ($a) => ['filename' => $a->filename, 'content' => $a->content])
            ->values()
            ->toArray();

        // Notify frontend that QA has started (Ollama can take up to 180s)
        AgentLog::create([
            'task_id'     => $task->id,
            'agent_type'  => 'qa',
            'input'       => ['files_to_review' => count($artifacts)],
            'output'      => ['status' => 'QA started — reviewing code with Ollama. This may take up to 3 minutes.'],
            'tokens_used' => 0,
            'status'      => 'running',
        ]);
        event(new TaskStatusUpdated($task));

        try {
            [$response, $tokensUsed] = config('app.agent_mode') === 'mock'
                ? [$this->getMockResponse('qa'), 75]
                : $this->callOllama($artifacts);
        } catch (Throwable $e) {
            $this->escalate($task, $artifacts, $e->getMessage());
            return;
        }

        // Guard against malformed Ollama response (missing required 'passed' field)
        if (!array_key_exists('passed', $response)) {
            $this->escalate($task, $artifacts, "QA response missing 'passed' field. Got: ".json_encode($response));
            return;
        }

        $qaStatus = $response['passed'] ? 'success' : 'failed';

        AgentLog::create([
            'task_id'     => $task->id,
            'agent_type'  => 'qa',
            'input'       => ['files_reviewed' => count($artifacts)],
            'output'      => $response,
            'tokens_used' => $tokensUsed,
            'status'      => $qaStatus,
        ]);

        Task::where('id', $this->taskId)->increment('token_used', $tokensUsed);

        if ($response['passed']) {
            try {
                $updated = $sm->transition($this->taskId, 'completed');
                event(new TaskStatusUpdated($updated));
                // 🔔 Telegram: Task completed!
                app(TelegramService::class)->notifyTaskCompleted($task->id, $task->title);
            } catch (TokenBudgetExceededException $e) {
                $this->escalate($task, $artifacts, "Token budget exceeded ({$e->getMessage()})");
            } catch (Throwable $e) {
                $this->escalate($task, $artifacts, $e->getMessage());
            }
            return;
        }

        // QA failed — attempt retry (StateMachineService auto-escalates at retry_count >= 3)
        try {
            // Fire event for qa_failed so frontend shows the QA Failed card
            $sm->transition($this->taskId, 'qa_failed');
            event(new TaskStatusUpdated($task->fresh()));

            // Then immediately move to dev_coding for retry
            $result = $sm->transition($this->taskId, 'dev_coding');
            event(new TaskStatusUpdated($result));

            if ($result->status === 'dev_coding') {
                // 🔔 Telegram: QA failed, retrying
                app(TelegramService::class)->notifyQaFailed($task->id, $task->title, $task->fresh()->retry_count);
                DevAgentJob::dispatch($this->taskId);
            }
        } catch (TokenBudgetExceededException $e) {
            $this->escalate($task, $artifacts, "Token budget exceeded ({$e->getMessage()})");
        } catch (Throwable $e) {
            $this->escalate($task, $artifacts, $e->getMessage());
        }
    }

    /** @return array{0: array, 1: int} */
    private function callOllama(array $artifacts): array
    {
        $systemPrompt = file_get_contents(base_path('../orchestrator/prompts/qa_system.txt'));
        $userMessage  = 'Code files to review: '.json_encode($artifacts, JSON_PRETTY_PRINT);

        $result = app(OllamaService::class)->generate($systemPrompt, $userMessage);

        return [$result['content'], $result['tokens_used']];
    }

    private function getMockResponse(string $agent): array
    {
        $path = base_path("../orchestrator/mock/{$agent}_response.json");

        return json_decode(file_get_contents($path), true);
    }

    private function escalate(Task $task, array $artifacts, string $reason): void
    {
        AgentLog::create([
            'task_id'     => $task->id,
            'agent_type'  => 'qa',
            'input'       => ['files_reviewed' => count($artifacts)],
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

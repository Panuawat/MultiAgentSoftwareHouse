<?php

namespace App\Jobs;

use App\Events\TaskStatusUpdated;
use App\Exceptions\TokenBudgetExceededException;
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

class QaAgentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 180;

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

        try {
            [$response, $tokensUsed] = config('app.agent_mode') === 'mock'
                ? [$this->getMockResponse('qa'), 75]
                : $this->callOllama($artifacts);
        } catch (Throwable $e) {
            $this->escalate($task, $sm, $artifacts, $e->getMessage());
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

        if ($response['passed']) {
            try {
                $sm->transition($this->taskId, 'completed');
                event(new TaskStatusUpdated($task->fresh()));
            } catch (TokenBudgetExceededException $e) {
                $this->escalate($task, $sm, $artifacts, "Token budget exceeded ({$e->getMessage()})");
            }
            return;
        }

        // QA failed — attempt retry (StateMachineService auto-escalates at retry_count >= 3)
        try {
            $sm->transition($this->taskId, 'qa_failed');
            $result = $sm->transition($this->taskId, 'dev_coding');

            event(new TaskStatusUpdated($result));

            if ($result->status === 'dev_coding') {
                DevAgentJob::dispatch($this->taskId);
            }
        } catch (TokenBudgetExceededException $e) {
            $this->escalate($task, $sm, $artifacts, "Token budget exceeded ({$e->getMessage()})");
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

    private function escalate(Task $task, StateMachineService $sm, array $artifacts, string $reason): void
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
    }
}

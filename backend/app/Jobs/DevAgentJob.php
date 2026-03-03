<?php

namespace App\Jobs;

use App\Events\TaskStatusUpdated;
use App\Models\AgentLog;
use App\Models\CodeArtifact;
use App\Models\Task;
use App\Services\GeminiService;
use App\Services\StateMachineService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class DevAgentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public function __construct(public readonly int $taskId)
    {
    }

    public function handle(StateMachineService $sm): void
    {
        $task = Task::findOrFail($this->taskId);

        if ($task->status !== 'dev_coding') {
            return;
        }

        $agentOutput = $task->agent_output ?? [];
        $pmOutput    = $agentOutput['pm'] ?? [];
        $uxOutput    = $agentOutput['ux'] ?? [];

        try {
            [$response, $tokensUsed] = config('app.agent_mode') === 'mock'
                ? [$this->getMockResponse('dev'), $this->getMockResponse('dev')['tokens_used']]
                : $this->callGemini($pmOutput, $uxOutput);
        } catch (Throwable $e) {
            $this->escalate($task, $sm, $pmOutput, $uxOutput, $e->getMessage());
            return;
        }

        // Version = retry_count + 1 (retry_count already incremented by StateMachineService)
        $version = $task->retry_count + 1;

        foreach ($response['files'] as $file) {
            CodeArtifact::create([
                'task_id'       => $task->id,
                'filename'      => $file['filename'],
                'content'       => $file['content'],
                'artifact_type' => $file['artifact_type'] ?? 'component',
                'version'       => $version,
            ]);
        }

        Task::where('id', $this->taskId)->increment('token_used', $tokensUsed);

        AgentLog::create([
            'task_id'     => $task->id,
            'agent_type'  => 'dev',
            'input'       => ['pm' => $pmOutput, 'ux' => $uxOutput],
            'output'      => ['files_count' => count($response['files'])],
            'tokens_used' => $tokensUsed,
            'status'      => 'success',
        ]);

        event(new TaskStatusUpdated($task));

        $sm->transition($this->taskId, 'qa_testing');

        QaAgentJob::dispatch($this->taskId);
    }

    /** @return array{0: array, 1: int} */
    private function callGemini(array $pmOutput, array $uxOutput): array
    {
        $systemPrompt = file_get_contents(base_path('../orchestrator/prompts/dev_system.txt'));
        $userMessage  = "Requirements:\n".json_encode($pmOutput, JSON_PRETTY_PRINT)
            ."\n\nUI Structure:\n".json_encode($uxOutput, JSON_PRETTY_PRINT);

        $result = app(GeminiService::class)->generate($systemPrompt, $userMessage);

        // Ensure the tokens_used from the API is used, not the one inside the JSON
        $result['content']['tokens_used'] = $result['tokens_used'];

        return [$result['content'], $result['tokens_used']];
    }

    private function getMockResponse(string $agent): array
    {
        $path = base_path("../orchestrator/mock/{$agent}_response.json");

        return json_decode(file_get_contents($path), true);
    }

    private function escalate(Task $task, StateMachineService $sm, array $pm, array $ux, string $reason): void
    {
        AgentLog::create([
            'task_id'     => $task->id,
            'agent_type'  => 'dev',
            'input'       => ['pm' => $pm, 'ux' => $ux],
            'output'      => ['error' => $reason],
            'tokens_used' => 0,
            'status'      => 'failed',
        ]);

        $task->escalate('AGENT_ERROR: '.$reason);
        event(new TaskStatusUpdated($task->fresh()));
    }
}

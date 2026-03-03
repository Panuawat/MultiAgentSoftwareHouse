<?php

namespace App\Services;

use App\Exceptions\InvalidStateTransitionException;
use App\Exceptions\TokenBudgetExceededException;
use App\Models\Task;
use Illuminate\Support\Facades\DB;

class StateMachineService
{
    private const TRANSITIONS = [
        'pending'               => ['pm_processing', 'cancelled'],
        'pm_processing'         => ['ux_processing', 'human_review_required', 'cancelled'],
        'ux_processing'         => ['dev_coding', 'human_review_required', 'cancelled'],
        'dev_coding'            => ['qa_testing', 'human_review_required', 'cancelled'],
        'qa_testing'            => ['completed', 'qa_failed', 'human_review_required', 'cancelled'],
        'qa_failed'             => ['dev_coding', 'human_review_required', 'cancelled'],
        'completed'             => [],
        'human_review_required' => ['cancelled'],
        'cancelled'             => [],
    ];

    public function transition(int $taskId, string $newStatus): Task
    {
        return DB::transaction(function () use ($taskId, $newStatus) {
            $task = Task::where('id', $taskId)->lockForUpdate()->firstOrFail();

            if (! $this->canTransition($task->status, $newStatus)) {
                throw new InvalidStateTransitionException($task->status, $newStatus);
            }

            $this->checkTokenBudget($task);

            // Auto-escalate if retry limit exceeded on qa_failed → dev_coding
            if ($task->status === 'qa_failed' && $newStatus === 'dev_coding') {
                if ($task->retry_count >= 3) {
                    $task->status = 'human_review_required';
                    $task->save();
                    return $task;
                }
                $task->retry_count += 1;
            }

            $task->status = $newStatus;
            $task->save();

            return $task;
        });
    }

    public function canTransition(string $fromStatus, string $toStatus): bool
    {
        return in_array($toStatus, self::TRANSITIONS[$fromStatus] ?? [], true);
    }

    private function checkTokenBudget(Task $task): void
    {
        if ($task->token_used >= $task->token_budget) {
            throw new TokenBudgetExceededException($task->token_used, $task->token_budget);
        }
    }
}

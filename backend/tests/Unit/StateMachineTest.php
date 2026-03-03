<?php

namespace Tests\Unit;

use App\Exceptions\InvalidStateTransitionException;
use App\Exceptions\TokenBudgetExceededException;
use App\Models\Project;
use App\Models\Task;
use App\Services\StateMachineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StateMachineTest extends TestCase
{
    use RefreshDatabase;

    private StateMachineService $service;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new StateMachineService();
        $this->project = Project::create(['name' => 'Test Project']);
    }

    private function makeTask(array $overrides = []): Task
    {
        return Task::create(array_merge([
            'project_id'   => $this->project->id,
            'title'        => 'Test Task',
            'status'       => 'pending',
            'token_budget' => 10000,
            'token_used'   => 0,
            'retry_count'  => 0,
        ], $overrides));
    }

    // 1. Valid transition: pending → pm_processing
    public function test_valid_transition_pending_to_pm_processing(): void
    {
        $task = $this->makeTask();

        $updated = $this->service->transition($task->id, 'pm_processing');

        $this->assertEquals('pm_processing', $updated->status);
    }

    // 2. Full happy path: pending → pm_processing → ux_processing → dev_coding → qa_testing → completed
    public function test_full_happy_path_transitions(): void
    {
        $task = $this->makeTask();

        $steps = ['pm_processing', 'ux_processing', 'dev_coding', 'qa_testing', 'completed'];

        foreach ($steps as $step) {
            $task = $this->service->transition($task->id, $step);
            $this->assertEquals($step, $task->status);
        }
    }

    // 3. Invalid transition throws exception
    public function test_invalid_transition_throws_exception(): void
    {
        $task = $this->makeTask(['status' => 'pending']);

        $this->expectException(InvalidStateTransitionException::class);

        $this->service->transition($task->id, 'completed');
    }

    // 4. Token budget exceeded throws exception
    public function test_token_budget_exceeded_escalates(): void
    {
        $task = $this->makeTask([
            'status'       => 'pending',
            'token_budget' => 500,
            'token_used'   => 500,
        ]);

        $this->expectException(TokenBudgetExceededException::class);

        $this->service->transition($task->id, 'pm_processing');
    }

    // 5. qa_failed → dev_coding increments retry_count
    public function test_qa_failed_retry_increments_counter(): void
    {
        $task = $this->makeTask([
            'status'      => 'qa_failed',
            'retry_count' => 0,
        ]);

        $updated = $this->service->transition($task->id, 'dev_coding');

        $this->assertEquals('dev_coding', $updated->status);
        $this->assertEquals(1, $updated->retry_count);
    }

    // 6. retry_count = 3 → transition to dev_coding auto-escalates to human_review_required
    public function test_retry_limit_triggers_human_review(): void
    {
        $task = $this->makeTask([
            'status'      => 'qa_failed',
            'retry_count' => 3,
        ]);

        $updated = $this->service->transition($task->id, 'dev_coding');

        $this->assertEquals('human_review_required', $updated->status);
        // retry_count should NOT be incremented when escalating
        $this->assertEquals(3, $updated->retry_count);
    }

    // 7. cancelled is a terminal state — no further transitions allowed
    public function test_cancelled_is_terminal_state(): void
    {
        $task = $this->makeTask(['status' => 'cancelled']);

        $this->expectException(InvalidStateTransitionException::class);

        $this->service->transition($task->id, 'pending');
    }
}

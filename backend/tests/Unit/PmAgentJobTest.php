<?php

namespace Tests\Unit;

use App\Jobs\PmAgentJob;
use App\Jobs\UxAgentJob;
use App\Models\AgentLog;
use App\Models\Project;
use App\Models\Task;
use App\Services\StateMachineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class PmAgentJobTest extends TestCase
{
    use RefreshDatabase;

    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();

        $project = Project::create(['name' => 'Test Project']);

        $this->task = Task::create([
            'project_id' => $project->id,
            'title'      => 'PM Job Test Task',
            'status'     => 'pm_processing',
        ]);
    }

    public function test_pm_job_transitions_to_ux_processing(): void
    {
        Bus::fake();

        $job = new PmAgentJob($this->task->id);
        $job->handle(new StateMachineService());

        $this->assertDatabaseHas('tasks', [
            'id'     => $this->task->id,
            'status' => 'ux_processing',
        ]);
    }

    public function test_pm_job_creates_agent_log(): void
    {
        Bus::fake();

        $job = new PmAgentJob($this->task->id);
        $job->handle(new StateMachineService());

        $this->assertDatabaseHas('agent_logs', [
            'task_id'    => $this->task->id,
            'agent_type' => 'pm',
            'status'     => 'success',
            'tokens_used' => 50,
        ]);
    }

    public function test_pm_job_dispatches_ux_agent_job(): void
    {
        Bus::fake();

        $job = new PmAgentJob($this->task->id);
        $job->handle(new StateMachineService());

        Bus::assertDispatched(UxAgentJob::class, function (UxAgentJob $j) {
            return $j->taskId === $this->task->id;
        });
    }
}

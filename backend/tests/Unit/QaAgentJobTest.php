<?php

namespace Tests\Unit;

use App\Jobs\DevAgentJob;
use App\Jobs\QaAgentJob;
use App\Models\Project;
use App\Models\Task;
use App\Services\StateMachineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class QaAgentJobTest extends TestCase
{
    use RefreshDatabase;

    private function makeTask(array $attributes = []): Task
    {
        $project = Project::create(['name' => 'QA Test Project']);

        return Task::create(array_merge([
            'project_id' => $project->id,
            'title'      => 'QA Test Task',
            'status'     => 'qa_testing',
            'retry_count' => 0,
        ], $attributes));
    }

    public function test_qa_job_completes_task_when_passed(): void
    {
        // The default mock qa_response.json has passed=true
        Bus::fake();

        $task = $this->makeTask();
        $job  = new QaAgentJob($task->id);
        $job->handle(new StateMachineService());

        $this->assertDatabaseHas('tasks', [
            'id'     => $task->id,
            'status' => 'completed',
        ]);
    }

    public function test_qa_job_retries_dev_on_failure(): void
    {
        Bus::fake();

        // Temporarily swap the mock to a failing response
        $mockPath = base_path('../orchestrator/mock/qa_response.json');
        $original = file_get_contents($mockPath);
        file_put_contents($mockPath, json_encode(['passed' => false, 'errors' => ['lint error'], 'report' => 'Failed']));

        try {
            $task = $this->makeTask(['retry_count' => 0]);
            $job  = new QaAgentJob($task->id);
            $job->handle(new StateMachineService());

            $this->assertDatabaseHas('tasks', [
                'id'     => $task->id,
                'status' => 'dev_coding',
            ]);

            Bus::assertDispatched(DevAgentJob::class, function (DevAgentJob $j) use ($task) {
                return $j->taskId === $task->id;
            });
        } finally {
            file_put_contents($mockPath, $original);
        }
    }

    public function test_qa_job_escalates_on_max_retries(): void
    {
        Bus::fake();

        $mockPath = base_path('../orchestrator/mock/qa_response.json');
        $original = file_get_contents($mockPath);
        file_put_contents($mockPath, json_encode(['passed' => false, 'errors' => ['lint error'], 'report' => 'Failed']));

        try {
            // retry_count = 3 means StateMachineService will escalate instead of re-entering dev_coding
            $task = $this->makeTask(['retry_count' => 3]);
            $job  = new QaAgentJob($task->id);
            $job->handle(new StateMachineService());

            $this->assertDatabaseHas('tasks', [
                'id'     => $task->id,
                'status' => 'human_review_required',
            ]);

            Bus::assertNotDispatched(DevAgentJob::class);
        } finally {
            file_put_contents($mockPath, $original);
        }
    }
}

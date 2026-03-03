<?php

namespace Tests\Feature;

use App\Jobs\PmAgentJob;
use App\Models\AgentLog;
use App\Models\CodeArtifact;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class TaskApiTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->project = Project::create(['name' => 'Test Project']);
    }

    public function test_can_create_task(): void
    {
        $response = $this->postJson('/api/tasks', [
            'project_id' => $this->project->id,
            'title'      => 'Build landing page',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['task' => ['id', 'project_id', 'title', 'status', 'token_budget']]);

        $this->assertDatabaseHas('tasks', [
            'title'        => 'Build landing page',
            'status'       => 'pending',
            'token_budget' => 10000,
        ]);
    }

    public function test_can_show_task_with_relations(): void
    {
        $task = Task::create([
            'project_id' => $this->project->id,
            'title'      => 'Show task',
        ]);

        $response = $this->getJson("/api/tasks/{$task->id}");

        $response->assertStatus(200)
            ->assertJsonStructure(['task' => ['id', 'agent_logs', 'code_artifacts']]);
    }

    public function test_start_task_dispatches_pm_agent_job(): void
    {
        Bus::fake();

        $task = Task::create([
            'project_id' => $this->project->id,
            'title'      => 'Start me',
        ]);

        $this->postJson("/api/tasks/{$task->id}/start")
            ->assertStatus(202);

        Bus::assertDispatched(PmAgentJob::class, function (PmAgentJob $job) use ($task) {
            return $job->taskId === $task->id;
        });
    }

    public function test_start_task_transitions_to_pm_processing(): void
    {
        Bus::fake();

        $task = Task::create([
            'project_id' => $this->project->id,
            'title'      => 'Transition test',
        ]);

        $this->postJson("/api/tasks/{$task->id}/start")
            ->assertStatus(202)
            ->assertJsonPath('task.status', 'pm_processing');
    }

    public function test_cannot_start_already_started_task(): void
    {
        $task = Task::create([
            'project_id' => $this->project->id,
            'title'      => 'Already started',
            'status'     => 'pm_processing',
        ]);

        $this->postJson("/api/tasks/{$task->id}/start")
            ->assertStatus(422)
            ->assertJsonStructure(['message']);
    }

    public function test_can_cancel_task(): void
    {
        $task = Task::create([
            'project_id' => $this->project->id,
            'title'      => 'Cancel me',
        ]);

        $this->postJson("/api/tasks/{$task->id}/cancel")
            ->assertStatus(200)
            ->assertJsonPath('task.status', 'cancelled');
    }

    public function test_can_list_task_logs(): void
    {
        $task = Task::create([
            'project_id' => $this->project->id,
            'title'      => 'Logs task',
        ]);

        AgentLog::create([
            'task_id'    => $task->id,
            'agent_type' => 'pm',
            'output'     => ['result' => 'ok'],
            'tokens_used' => 50,
            'status'     => 'success',
        ]);

        $this->getJson("/api/tasks/{$task->id}/logs")
            ->assertStatus(200)
            ->assertJsonStructure(['logs'])
            ->assertJsonCount(1, 'logs');
    }

    public function test_can_list_task_artifacts(): void
    {
        $task = Task::create([
            'project_id' => $this->project->id,
            'title'      => 'Artifacts task',
        ]);

        CodeArtifact::create([
            'task_id'       => $task->id,
            'filename'      => 'index.tsx',
            'content'       => 'export default function Home() {}',
            'artifact_type' => 'page',
            'version'       => 1,
        ]);

        $this->getJson("/api/tasks/{$task->id}/artifacts")
            ->assertStatus(200)
            ->assertJsonStructure(['artifacts'])
            ->assertJsonCount(1, 'artifacts');
    }
}

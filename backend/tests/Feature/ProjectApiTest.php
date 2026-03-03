<?php

namespace Tests\Feature;

use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_project(): void
    {
        $response = $this->postJson('/api/projects', [
            'name'        => 'OpenClaw Demo',
            'description' => 'A demo project',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['project' => ['id', 'name', 'description']]);

        $this->assertDatabaseHas('projects', ['name' => 'OpenClaw Demo']);
    }

    public function test_can_list_projects(): void
    {
        Project::create(['name' => 'Alpha']);
        Project::create(['name' => 'Beta']);

        $response = $this->getJson('/api/projects');

        $response->assertStatus(200)
            ->assertJsonStructure(['projects'])
            ->assertJsonCount(2, 'projects');
    }

    public function test_can_show_project(): void
    {
        $project = Project::create(['name' => 'ShowMe']);

        $response = $this->getJson("/api/projects/{$project->id}");

        $response->assertStatus(200)
            ->assertJsonPath('project.id', $project->id)
            ->assertJsonPath('project.name', 'ShowMe');
    }

    public function test_can_update_project(): void
    {
        $project = Project::create(['name' => 'Old Name']);

        $response = $this->putJson("/api/projects/{$project->id}", [
            'name' => 'New Name',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('project.name', 'New Name');

        $this->assertDatabaseHas('projects', ['id' => $project->id, 'name' => 'New Name']);
    }

    public function test_can_delete_project(): void
    {
        $project = Project::create(['name' => 'ToDelete']);

        $response = $this->deleteJson("/api/projects/{$project->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The entire data graph (datasets, tables, analyses, insights...) is
 * authorized through this single ProjectPolicy via route scopeBindings -
 * if ownership checks here regress, every nested route silently opens up
 * too, so this is the single highest-value authorization test in the app.
 */
class ProjectPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_their_project(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        $this->actingAs($owner)
            ->get(route('projects.show', $project))
            ->assertOk();
    }

    public function test_other_user_cannot_view_someone_elses_project(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        $this->actingAs($stranger)
            ->get(route('projects.show', $project))
            ->assertForbidden();
    }

    public function test_other_user_cannot_update_someone_elses_project(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        $this->actingAs($stranger)
            ->put(route('projects.update', $project), ['name' => 'Renamed'])
            ->assertForbidden();

        $this->assertDatabaseMissing('projects', ['id' => $project->id, 'name' => 'Renamed']);
    }

    public function test_other_user_cannot_view_a_nested_dataset_route(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $dataset = $project->datasets()->create([
            'name' => 'test.csv',
            'original_filename' => 'test.csv',
            'format' => 'csv',
            'disk_path' => 'datasets/1/test.csv',
            'size_bytes' => 10,
            'status' => 'imported',
        ]);

        $this->actingAs($stranger)
            ->get(route('projects.datasets.show', [$project, $dataset]))
            ->assertForbidden();
    }
}

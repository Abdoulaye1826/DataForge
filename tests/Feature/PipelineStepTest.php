<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * PipelineStepService is the shared execution engine behind manual
 * transformations, accepted AI suggestions, and notebook replay alike - a
 * regression here would silently break all three at once. Imports a real
 * file first (rather than seeding rows directly) so the table's on-disk
 * cache actually exists for clean_data.py to operate on.
 */
class PipelineStepTest extends TestCase
{
    use RefreshDatabase;

    public function test_applying_trim_spaces_records_a_pipeline_step_and_keeps_the_row_count(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $file = UploadedFile::fake()->createWithContent(
            'sample_sales.csv',
            file_get_contents(__DIR__.'/../Fixtures/sample_sales.csv'),
        );

        $this->actingAs($user)->post(route('projects.datasets.import', $project), ['files' => [$file]]);

        $dataset = $project->datasets()->first();
        $table = $dataset->tables()->first();

        $response = $this->actingAs($user)->post(
            route('projects.datasets.tables.pipeline-steps.store', [$project, $dataset, $table]),
            [
                'step_type' => 'trim_spaces',
                'rationale' => 'Test automatisé',
            ],
        );

        $response->assertRedirect(route('projects.datasets.show', [$project, $dataset]));
        $response->assertSessionHasNoErrors();

        $table->refresh();

        $this->assertSame(5, $table->row_count, 'trim_spaces must not change the row count.');

        $this->assertDatabaseHas('pipeline_steps', [
            'dataset_table_id' => $table->id,
            'step_type' => 'trim_spaces',
            'rationale' => 'Test automatisé',
        ]);
    }
}

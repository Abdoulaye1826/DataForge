<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * The single biggest and most-relied-upon flow in the app: upload -> real
 * Python parsing (import_dataset.py) -> table/columns persisted -> full
 * onboarding cascade (quality audit, EDA, default charts). No AI provider
 * is configured in the test environment, so the AI enrichment steps
 * (semantic labels, insights) are expected to no-op via their existing
 * guards rather than fail the request - this test also doubles as a check
 * that those guards actually hold under a real missing-key condition.
 */
class DatasetImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_uploading_a_real_csv_creates_a_table_with_columns_and_a_quality_report(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $file = UploadedFile::fake()->createWithContent(
            'sample_sales.csv',
            file_get_contents(__DIR__.'/../Fixtures/sample_sales.csv'),
        );

        $response = $this->actingAs($user)->post(
            route('projects.datasets.import', $project),
            ['files' => [$file]],
        );

        $response->assertRedirect(route('projects.show', $project));
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('datasets', [
            'project_id' => $project->id,
            'original_filename' => 'sample_sales.csv',
        ]);

        $dataset = $project->datasets()->first();
        $table = $dataset->tables()->first();

        $this->assertNotNull($table, 'Import should have produced at least one table.');
        $this->assertSame(5, $table->row_count);
        $this->assertSame(4, $table->column_count);
        $this->assertEqualsCanonicalizing(
            ['produit', 'region', 'quantite', 'prix_unitaire'],
            $table->columns->pluck('name')->all(),
        );

        $this->assertNotNull($table->latestQualityReport, 'Onboarding should have run a quality audit.');
        $this->assertNotNull($table->latestAnalysis, 'Onboarding should have run the EDA step.');
    }
}

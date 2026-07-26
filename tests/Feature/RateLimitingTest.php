<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Guards against the audit finding that no route had any rate limiting -
 * a Python-backed route could previously be hit unboundedly, each call
 * tying up a PHP worker for up to PYTHON_TIMEOUT seconds. Exercises the
 * "heavy" limiter (20/min, see RouteServiceProvider) against a real route
 * via the accept-all endpoint, which is safe to call repeatedly with no
 * pending suggestions (loops zero times, no Python/AI cost).
 */
class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    public function test_heavy_route_is_throttled_after_twenty_requests_per_minute(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $file = UploadedFile::fake()->createWithContent(
            'sample_sales.csv',
            file_get_contents(__DIR__.'/../Fixtures/sample_sales.csv'),
        );
        // The import itself already consumes one hit of the same per-user
        // "heavy" bucket (it shares the limiter with every other Python-
        // backed route by design), so only 19 more fit under the 20/min cap.
        $this->actingAs($user)->post(route('projects.datasets.import', $project), ['files' => [$file]]);

        $dataset = $project->datasets()->first();
        $table = $dataset->tables()->first();
        $url = route('projects.datasets.tables.pipeline-suggestions.accept-all', [$project, $dataset, $table]);

        for ($i = 1; $i <= 19; $i++) {
            $this->actingAs($user)->post($url)->assertStatus(302);
        }

        $this->actingAs($user)->post($url)->assertStatus(429);
    }
}

<?php

namespace Tests\Unit;

use App\Repositories\Contracts\PipelineSuggestionRepositoryInterface;
use App\Services\Ai\AiContextBuilder;
use App\Services\Ai\Contracts\AiProviderInterface;
use App\Services\Pipeline\PipelineRecommendationService;
use App\Services\Pipeline\UserPipelinePreferenceService;
use PHPUnit\Framework\TestCase;

/**
 * The AI is instructed to only use one of the 15 manually-applicable
 * PipelineStepType values, but instructions are not guarantees - this locks
 * in that parseItems() drops any suggestion with an invalid step_type,
 * missing rationale, or non-array params, rather than ever creating a
 * PipelineSuggestion that would blow up PipelineStepService::applyStep()
 * when accepted.
 */
class PipelineRecommendationServiceTest extends TestCase
{
    private function service(): PipelineRecommendationService
    {
        return new PipelineRecommendationService(
            $this->createStub(PipelineSuggestionRepositoryInterface::class),
            $this->createStub(AiProviderInterface::class),
            $this->createStub(AiContextBuilder::class),
            $this->createStub(UserPipelinePreferenceService::class),
        );
    }

    private function parseItems(string $json): array
    {
        $method = new \ReflectionMethod(PipelineRecommendationService::class, 'parseItems');
        $method->setAccessible(true);

        return $method->invoke($this->service(), $json);
    }

    private function parsePlan(string $json): ?string
    {
        $method = new \ReflectionMethod(PipelineRecommendationService::class, 'parsePlan');
        $method->setAccessible(true);

        return $method->invoke($this->service(), $json);
    }

    public function test_valid_suggestion_is_kept(): void
    {
        $items = $this->parseItems(json_encode([
            'suggestions' => [
                ['step_type' => 'trim_spaces', 'params' => ['columns' => null], 'rationale' => 'Espaces parasites.'],
            ],
        ]));

        $this->assertCount(1, $items);
        $this->assertSame('trim_spaces', $items[0]['step_type']);
    }

    public function test_invalid_step_type_is_dropped(): void
    {
        $items = $this->parseItems(json_encode([
            'suggestions' => [
                ['step_type' => 'delete_everything', 'params' => [], 'rationale' => 'x'],
            ],
        ]));

        $this->assertCount(0, $items);
    }

    public function test_import_step_type_is_dropped_even_though_it_is_a_real_enum_value(): void
    {
        // "import" and "join" are real PipelineStepType cases but are not
        // manually-applicable - parseItems must only accept the cleaning/
        // preprocessing subset, same restriction as the manual transform form.
        $items = $this->parseItems(json_encode([
            'suggestions' => [
                ['step_type' => 'import', 'params' => [], 'rationale' => 'x'],
            ],
        ]));

        $this->assertCount(0, $items);
    }

    public function test_missing_rationale_is_dropped(): void
    {
        $items = $this->parseItems(json_encode([
            'suggestions' => [
                ['step_type' => 'dedupe', 'params' => ['columns' => null], 'rationale' => ''],
            ],
        ]));

        $this->assertCount(0, $items);
    }

    public function test_non_array_params_is_dropped(): void
    {
        $items = $this->parseItems(json_encode([
            'suggestions' => [
                ['step_type' => 'dedupe', 'params' => 'not-an-array', 'rationale' => 'x'],
            ],
        ]));

        $this->assertCount(0, $items);
    }

    public function test_malformed_json_yields_no_items(): void
    {
        $this->assertSame([], $this->parseItems('not json at all'));
    }

    public function test_missing_suggestions_key_yields_no_items(): void
    {
        $this->assertSame([], $this->parseItems(json_encode(['foo' => 'bar'])));
    }

    public function test_plan_text_is_extracted(): void
    {
        $plan = $this->parsePlan(json_encode(['plan' => 'La table contient des doublons.', 'suggestions' => []]));

        $this->assertSame('La table contient des doublons.', $plan);
    }

    public function test_missing_plan_yields_null(): void
    {
        $this->assertNull($this->parsePlan(json_encode(['suggestions' => []])));
    }

    public function test_blank_plan_yields_null(): void
    {
        $this->assertNull($this->parsePlan(json_encode(['plan' => '   ', 'suggestions' => []])));
    }

    public function test_non_string_plan_yields_null(): void
    {
        $this->assertNull($this->parsePlan(json_encode(['plan' => ['not', 'a', 'string'], 'suggestions' => []])));
    }
}

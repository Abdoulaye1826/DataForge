<?php

namespace Tests\Unit;

use App\Repositories\Contracts\AiInsightRepositoryInterface;
use App\Services\Ai\AiContextBuilder;
use App\Services\Ai\AiInsightService;
use App\Services\Ai\Contracts\AiProviderInterface;
use PHPUnit\Framework\TestCase;

/**
 * The AI provider can - and, this session, repeatedly did - return
 * malformed or partially-hallucinated JSON (an unknown suggested_action
 * type, a legacy plain-string item, a missing "action" key). This locks in
 * the defensive parsing (extractItem/validActionOrNull) that keeps a bad
 * model response from ever reaching the database as a broken suggestion.
 */
class AiInsightServiceTest extends TestCase
{
    private function service(): AiInsightService
    {
        return new AiInsightService(
            $this->createStub(AiInsightRepositoryInterface::class),
            $this->createStub(AiProviderInterface::class),
            $this->createStub(AiContextBuilder::class),
        );
    }

    private function extractItem(mixed $item): array
    {
        $method = new \ReflectionMethod(AiInsightService::class, 'extractItem');
        $method->setAccessible(true);

        return $method->invoke($this->service(), $item);
    }

    public function test_legacy_plain_string_item_has_no_action(): void
    {
        [$text, $action] = $this->extractItem('Un simple texte.');

        $this->assertSame('Un simple texte.', $text);
        $this->assertNull($action);
    }

    public function test_object_item_with_valid_action_is_kept(): void
    {
        [$text, $action] = $this->extractItem([
            'text' => 'Tendance à la baisse.',
            'action' => ['type' => 'forecast', 'params' => ['date_column' => 'date_vente']],
        ]);

        $this->assertSame('Tendance à la baisse.', $text);
        $this->assertSame('forecast', $action['type']);
        $this->assertSame(['date_column' => 'date_vente'], $action['params']);
    }

    public function test_object_item_with_unknown_action_type_is_nulled(): void
    {
        [$text, $action] = $this->extractItem([
            'text' => 'Action inconnue.',
            'action' => ['type' => 'not_a_real_type', 'params' => []],
        ]);

        $this->assertSame('Action inconnue.', $text);
        $this->assertNull($action, 'An action type outside the 4 known types must never reach the database.');
    }

    public function test_object_item_with_null_action_is_kept_as_is(): void
    {
        [$text, $action] = $this->extractItem(['text' => 'Résumé neutre.', 'action' => null]);

        $this->assertSame('Résumé neutre.', $text);
        $this->assertNull($action);
    }

    public function test_action_missing_params_defaults_to_empty_array(): void
    {
        [, $action] = $this->extractItem([
            'text' => 'x',
            'action' => ['type' => 'visualization'],
        ]);

        $this->assertSame([], $action['params']);
    }

    public function test_malformed_item_yields_null_text(): void
    {
        [$text, $action] = $this->extractItem(42);

        $this->assertNull($text);
        $this->assertNull($action);
    }
}

<?php

namespace Tests\Unit;

use App\Services\Ai\AiContextBuilder;
use App\Services\Ai\Contracts\AiProviderInterface;
use App\Services\Ai\SemanticColumnService;
use PHPUnit\Framework\TestCase;

/**
 * The AI is asked to return a business_category constrained to the
 * ColumnBusinessCategory enum and a confidence between 0 and 1, but nothing
 * stops it from hallucinating an invalid category string or an out-of-range
 * number - this locks in that parseColumnEntry() nulls out only the bad
 * field(s) rather than dropping a perfectly good label+reasoning over one
 * malformed extra field.
 */
class SemanticColumnServiceTest extends TestCase
{
    private function service(): SemanticColumnService
    {
        return new SemanticColumnService(
            $this->createStub(AiProviderInterface::class),
            $this->createStub(AiContextBuilder::class),
        );
    }

    private function parseColumnEntry(mixed $entry): ?array
    {
        $method = new \ReflectionMethod(SemanticColumnService::class, 'parseColumnEntry');
        $method->setAccessible(true);

        return $method->invoke($this->service(), $entry);
    }

    public function test_valid_entry_is_kept_with_all_fields(): void
    {
        $entry = $this->parseColumnEntry([
            'label' => 'Montant TTC',
            'reasoning' => 'Colonne numérique nommée amount.',
            'business_category' => 'monetary',
            'confidence' => 0.9,
        ]);

        $this->assertSame('Montant TTC', $entry['semantic_label']);
        $this->assertSame('Colonne numérique nommée amount.', $entry['semantic_reasoning']);
        $this->assertSame('monetary', $entry['business_category']);
        $this->assertSame(0.9, $entry['semantic_confidence']);
    }

    public function test_missing_label_yields_null(): void
    {
        $this->assertNull($this->parseColumnEntry(['reasoning' => 'x']));
    }

    public function test_blank_label_yields_null(): void
    {
        $this->assertNull($this->parseColumnEntry(['label' => '   ']));
    }

    public function test_non_array_entry_yields_null(): void
    {
        $this->assertNull($this->parseColumnEntry('not-an-array'));
    }

    public function test_invalid_business_category_is_nulled_but_label_is_kept(): void
    {
        $entry = $this->parseColumnEntry([
            'label' => 'Identifiant client',
            'business_category' => 'not_a_real_category',
            'confidence' => 0.8,
        ]);

        $this->assertSame('Identifiant client', $entry['semantic_label']);
        $this->assertNull($entry['business_category']);
        $this->assertSame(0.8, $entry['semantic_confidence']);
    }

    public function test_out_of_range_confidence_is_nulled_but_label_is_kept(): void
    {
        $entry = $this->parseColumnEntry([
            'label' => 'Identifiant client',
            'business_category' => 'identifier',
            'confidence' => 1.5,
        ]);

        $this->assertSame('identifier', $entry['business_category']);
        $this->assertNull($entry['semantic_confidence']);
    }

    public function test_non_numeric_confidence_is_nulled(): void
    {
        $entry = $this->parseColumnEntry(['label' => 'x', 'confidence' => 'very sure']);

        $this->assertNull($entry['semantic_confidence']);
    }

    public function test_missing_reasoning_yields_null_reasoning(): void
    {
        $entry = $this->parseColumnEntry(['label' => 'x']);

        $this->assertNull($entry['semantic_reasoning']);
        $this->assertNull($entry['business_category']);
        $this->assertNull($entry['semantic_confidence']);
    }
}

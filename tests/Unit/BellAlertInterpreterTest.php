<?php

namespace Tests\Unit;

use App\Services\Integration\BellAlertInterpreter;
use PHPUnit\Framework\TestCase;

/**
 * The translation layer between raw Bell/ISO 15143-3 telemetry codes and
 * what a Dot.Mines user reads. Interpretation sources, in order:
 *
 *   1. Bell's own spec fields when the API provides them (CodeDescription,
 *      CodeSeverity, CodeSource) -- real manufacturer data, never invented.
 *   2. The curated catalog in config/telemetry-diagnostics.php.
 *   3. A graceful generic: "Machine alert" + the raw code under
 *      technical details -- never raw ISO jargon as the primary message.
 */
class BellAlertInterpreterTest extends TestCase
{
    private function interpreter(array $catalog = []): BellAlertInterpreter
    {
        return new BellAlertInterpreter($catalog);
    }

    public function test_unknown_code_gets_a_generic_human_message_with_the_code_in_technical_details(): void
    {
        $result = $this->interpreter()->interpret('E204', []);

        $this->assertSame('Machine alert', $result['title']);
        $this->assertStringNotContainsString('ISO 15143-3', $result['description']);
        $this->assertStringNotContainsString('E204', $result['description']);
        $this->assertSame('medium', $result['priority']);
        $this->assertSame('E204', $result['technical']['code']);
        $this->assertSame('Bell ISO 15143-3 telemetry', $result['technical']['source']);
    }

    public function test_bell_provided_description_and_severity_are_used_verbatim(): void
    {
        $result = $this->interpreter()->interpret('E204', [
            'CodeDescription' => 'Hydraulic oil pressure low',
            'CodeSeverity' => 'High',
            'CodeSource' => 'Hydraulic system',
        ]);

        $this->assertSame('Hydraulic oil pressure low', $result['title']);
        $this->assertSame('high', $result['priority']);
        $this->assertSame('Hydraulic system', $result['technical']['component']);
        $this->assertStringContainsString('Hydraulic system', $result['description']);
        $this->assertSame('E204', $result['technical']['code']);
    }

    public function test_catalog_entry_overrides_the_generic_fallback(): void
    {
        $result = $this->interpreter([
            'E204' => [
                'title' => 'Engine warning',
                'description' => 'The machine has reported an engine-related warning.',
                'action' => 'Inspect the machine before continuing operation.',
                'priority' => 'high',
                'component' => 'Engine',
            ],
        ])->interpret('E204', []);

        $this->assertSame('Engine warning', $result['title']);
        $this->assertStringContainsString('engine-related warning', $result['description']);
        $this->assertStringContainsString('Inspect the machine', $result['description']);
        $this->assertSame('high', $result['priority']);
        $this->assertSame('E204', $result['technical']['code']);
    }

    public function test_bell_severity_wins_over_catalog_priority_but_catalog_text_still_applies(): void
    {
        // The machine's own severity outranks our static guess about it.
        $result = $this->interpreter([
            'E204' => ['title' => 'Engine warning', 'description' => 'Engine-related warning.', 'priority' => 'medium'],
        ])->interpret('E204', ['CodeSeverity' => 'Critical']);

        $this->assertSame('Engine warning', $result['title']);
        $this->assertSame('critical', $result['priority']);
    }

    public function test_unrecognised_severity_degrades_to_medium_and_is_preserved_raw(): void
    {
        $result = $this->interpreter()->interpret('E204', ['CodeSeverity' => '2']);

        $this->assertSame('medium', $result['priority']);
        $this->assertSame('2', $result['technical']['severity_raw']);
    }
}

<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

/**
 * The analyzer-baseline ratchet (refactor program R0): baselines may only
 * ever SHRINK, and once a ledger reaches zero its baseline file is deleted
 * for good. The phpstan ledger (1,898 entries at program start) reached
 * zero in slice R6; the psalm ledger (7,754 occurrences across 253 files)
 * reached zero in the psalm-burn slices. Both analyzers now run bare, and
 * this test fails CI if anyone reintroduces a baseline for either.
 */
class AnalyzerBaselineRatchetTest extends TestCase
{
    public function test_phpstan_baseline_stays_deleted(): void
    {
        $this->assertFileDoesNotExist(
            dirname(__DIR__, 2).'/phpstan-baseline.neon',
            'The phpstan baseline was deleted in R6 with the ledger at zero. '
            .'New code must pass phpstan bare; fix findings instead of reintroducing a baseline.',
        );
    }

    public function test_psalm_baseline_stays_deleted(): void
    {
        $this->assertFileDoesNotExist(
            dirname(__DIR__, 2).'/psalm-baseline.xml',
            'The psalm baseline was deleted with the ledger at zero. '
            .'New code must pass psalm bare; fix findings instead of reintroducing a baseline.',
        );
    }

    public function test_psalm_config_carries_no_baseline_attribute(): void
    {
        $config = (string) file_get_contents(dirname(__DIR__, 2).'/psalm.xml');

        $this->assertStringNotContainsString(
            'errorBaseline',
            $config,
            'psalm.xml must not point at a baseline file; both analyzers run bare.',
        );
    }
}

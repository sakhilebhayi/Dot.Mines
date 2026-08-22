<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

/**
 * The analyzer-baseline ratchet (refactor program R0): baselines may only
 * ever SHRINK. The phpstan ledger (1,898 entries at program start) reached
 * ZERO in slice R6 and its baseline file was deleted -- phpstan now runs
 * bare, and this test fails CI if anyone reintroduces a baseline. The psalm
 * baseline is still being burned down: lower its high-water mark in the
 * same PR whenever a slice shrinks it, and never regenerate it larger.
 */
class AnalyzerBaselineRatchetTest extends TestCase
{
    private const PSALM_BASELINE_FILES_HIGH_WATER = 253;

    public function test_phpstan_baseline_stays_deleted(): void
    {
        $this->assertFileDoesNotExist(
            dirname(__DIR__, 2).'/phpstan-baseline.neon',
            'The phpstan baseline was deleted in R6 with the ledger at zero. '
            .'New code must pass phpstan bare; fix findings instead of reintroducing a baseline.',
        );
    }

    public function test_psalm_baseline_only_shrinks(): void
    {
        $path = dirname(__DIR__, 2).'/psalm-baseline.xml';

        if (! file_exists($path)) {
            $this->assertTrue(true, 'Baseline deleted: the ledger is paid off.');

            return;
        }

        $count = substr_count((string) file_get_contents($path), '<file ');

        $this->assertLessThanOrEqual(
            self::PSALM_BASELINE_FILES_HIGH_WATER,
            $count,
            "psalm-baseline.xml grew to {$count} files (high-water mark ".self::PSALM_BASELINE_FILES_HIGH_WATER.'). '
            .'Regenerating the baseline may only ever shrink it; fix new findings instead.',
        );
    }
}

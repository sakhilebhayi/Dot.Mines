<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

/**
 * The analyzer-baseline ratchet (refactor program R0): both baselines may
 * only ever SHRINK. "phpstan/psalm clean" has really meant "clean modulo
 * ~2,150 baselined issues" -- the refactor program burns that ledger down
 * to zero (slices R4-R6), and this test makes any regression a CI failure
 * on the way there.
 *
 * When a slice reduces the debt, lower the high-water marks to the new
 * counts in the same PR. When a baseline file is finally deleted, its mark
 * becomes 0 and stays there.
 */
class AnalyzerBaselineRatchetTest extends TestCase
{
    private const PHPSTAN_BASELINE_HIGH_WATER = 1766;

    private const PSALM_BASELINE_FILES_HIGH_WATER = 258;

    public function test_phpstan_baseline_only_shrinks(): void
    {
        $path = dirname(__DIR__, 2).'/phpstan-baseline.neon';

        if (! file_exists($path)) {
            $this->assertTrue(true, 'Baseline deleted: the ledger is paid off.');

            return;
        }

        $count = substr_count((string) file_get_contents($path), 'message:');

        $this->assertLessThanOrEqual(
            self::PHPSTAN_BASELINE_HIGH_WATER,
            $count,
            "phpstan-baseline.neon grew to {$count} entries (high-water mark ".self::PHPSTAN_BASELINE_HIGH_WATER.'). '
            .'New code must pass phpstan without baseline additions; fix the findings instead.',
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

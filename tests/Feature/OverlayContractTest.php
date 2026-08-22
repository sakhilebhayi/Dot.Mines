<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Static contract for every full-screen overlay in the app, so backdrop
 * styling can never drift page-by-page again (the audit found bg-black/50
 * vs /60 shade drift, a one-off inline blur, and z-50 modals rendering
 * UNDER Leaflet's z-1000 map layers -- which one page "fixed" with
 * z-[10000]).
 *
 * The layer scale: mobile-nav backdrop 45 < page content 50 < Leaflet
 * panes/controls <=1000 < modal overlays 1100 < toasts 1200. Custom modal
 * overlays carry `data-app-overlay` (which drives the body scroll lock in
 * app.js) plus one canonical class recipe; Jetstream's x-modal wrapper and
 * the mobile-nav backdrop are the only sanctioned exceptions.
 */
class OverlayContractTest extends TestCase
{
    private const STANDARD_OVERLAY = 'data-app-overlay class="fixed inset-0 z-[1100] bg-black/60 flex items-center justify-center p-4 overflow-y-auto"';

    /** @var array<string, list<string>> file suffix => allowed `fixed inset-0` line fragments */
    private const SANCTIONED_EXCEPTIONS = [
        'components/modal.blade.php' => [
            'jetstream-modal fixed inset-0 overflow-y-auto px-4 py-6 sm:px-0 z-[1100]',
            'fixed inset-0 transform transition-all',
        ],
        'components/layouts/app.blade.php' => [
            'fixed inset-0 bg-black/60 z-[45] md:hidden',
        ],
        'layouts/app.blade.php' => [
            'fixed inset-0 bg-black/60 z-[45] md:hidden',
        ],
    ];

    public function test_every_full_screen_overlay_follows_the_single_contract(): void
    {
        $violations = [];

        foreach ($this->bladeFiles() as $path => $contents) {
            foreach (explode("\n", $contents) as $index => $line) {
                if (! str_contains($line, 'fixed inset-0')) {
                    continue;
                }

                if (str_contains($line, self::STANDARD_OVERLAY)) {
                    continue;
                }

                if ($this->isSanctionedException($path, $line)) {
                    continue;
                }

                $violations[] = sprintf('%s:%d', $path, $index + 1);
            }
        }

        $this->assertSame([], $violations, implode("\n", [
            'Full-screen overlays must use the standard recipe:',
            '  <div '.self::STANDARD_OVERLAY.'>',
            'Non-conforming lines:',
            ...$violations,
        ]));
    }

    public function test_no_backdrop_shade_drift_or_rogue_stacking_layers(): void
    {
        $violations = [];

        foreach ($this->bladeFiles() as $path => $contents) {
            foreach (explode("\n", $contents) as $index => $line) {
                foreach (['bg-black/50', 'backdrop-filter', 'z-[9998]', 'z-[9999]', 'z-[10000]'] as $forbidden) {
                    if (str_contains($line, $forbidden)) {
                        $violations[] = sprintf('%s:%d contains "%s"', $path, $index + 1, $forbidden);
                    }
                }
            }
        }

        $this->assertSame([], $violations, implode("\n", [
            'Backdrops are bg-black/60 with no blur; the stacking scale tops out at z-[1200] (toasts).',
            'Violations:',
            ...$violations,
        ]));
    }

    /**
     * @return iterable<string, string> repo-relative path => file contents
     */
    private function bladeFiles(): iterable
    {
        $root = dirname(__DIR__, 2).'/resources/views';
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $relative = 'resources/views/'.ltrim(str_replace($root, '', $file->getPathname()), '/');

                yield $relative => (string) file_get_contents($file->getPathname());
            }
        }
    }

    private function isSanctionedException(string $path, string $line): bool
    {
        foreach (self::SANCTIONED_EXCEPTIONS as $suffix => $fragments) {
            if (! str_ends_with($path, $suffix)) {
                continue;
            }

            foreach ($fragments as $fragment) {
                if (str_contains($line, $fragment)) {
                    return true;
                }
            }
        }

        return false;
    }
}

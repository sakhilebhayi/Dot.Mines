<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Built assets are committed and deployed as they are (the deploy runs no
 * npm). A manifest that names a file missing from public/build, or a built
 * chunk that imports a missing sibling, would ship a broken page, so both
 * are checked against what is on disk.
 */
class BuildManifestIntegrityTest extends TestCase
{
    private const BUILD = __DIR__.'/../../public/build';

    /**
     * @return array<string, array<string, mixed>>
     */
    private function manifest(): array
    {
        /** @var array<string, array<string, mixed>> $manifest */
        $manifest = json_decode((string) file_get_contents(self::BUILD.'/manifest.json'), true, 512, JSON_THROW_ON_ERROR);

        return $manifest;
    }

    public function test_every_file_the_manifest_names_exists(): void
    {
        $manifest = $this->manifest();
        $this->assertNotEmpty($manifest);

        foreach ($manifest as $entry => $chunk) {
            $files = array_merge([$chunk['file']], (array) ($chunk['css'] ?? []), (array) ($chunk['assets'] ?? []));

            foreach ($files as $file) {
                $this->assertFileExists(self::BUILD.'/'.$file, "{$entry} names {$file}");
            }

            foreach (array_merge((array) ($chunk['imports'] ?? []), (array) ($chunk['dynamicImports'] ?? [])) as $import) {
                $this->assertArrayHasKey($import, $manifest, "{$entry} imports {$import}");
            }
        }
    }

    public function test_the_entry_points_the_views_load_are_in_the_manifest(): void
    {
        $manifest = $this->manifest();

        foreach (['resources/css/app.css', 'resources/js/app.js', 'resources/js/live-map.js', 'resources/js/fleet-movement-replay.js'] as $entry) {
            $this->assertArrayHasKey($entry, $manifest);
        }
    }

    public function test_built_chunks_only_import_siblings_that_exist(): void
    {
        $checked = 0;

        foreach ((array) glob(self::BUILD.'/assets/*.{js,css}', GLOB_BRACE) as $path) {
            preg_match_all('#(?:\./|assets/)([A-Za-z0-9_.-]+-[A-Za-z0-9_-]{8}\.(?:js|css|png))#', (string) file_get_contents((string) $path), $matches);

            foreach (array_unique($matches[1]) as $sibling) {
                $this->assertFileExists(self::BUILD.'/assets/'.$sibling, basename((string) $path)." references {$sibling}");
                $checked++;
            }
        }

        $this->assertGreaterThan(0, $checked);
    }
}

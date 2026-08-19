<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ScanBladeUnescaped extends Command
{
    protected $signature = 'scan:blade-unescaped {--path= : Scan an alternate directory instead of resources/views}';

    protected $description = 'Scan Blade templates for unescaped output patterns (e.g. {!!)';

    /**
     * Files allowed to render raw output because the content is trusted by
     * provenance, not user input. Every entry needs a reason -- this list is
     * the reviewed exception record, and CI fails on anything not on it.
     *
     * @var array<string, string>
     */
    private const ALLOWED_RAW_OUTPUT = [
        // Str::markdown() of the repo's own markdown files (Jetstream's
        // terms/policy convention and the hand-wired cookies page). The
        // source is version-controlled content, not user input.
        'resources/views/terms.blade.php' => 'Jetstream terms markdown (repo-controlled source)',
        'resources/views/policy.blade.php' => 'Jetstream policy markdown (repo-controlled source)',
        'resources/views/cookies.blade.php' => 'Cookie policy markdown (repo-controlled source)',
        // Jetstream's registration consent line interpolates server-built
        // anchor tags for the terms/privacy links into a translation string.
        'resources/views/auth/register.blade.php' => 'Jetstream terms-consent links (server-built anchors)',
        // Fortify's 2FA QR code is an SVG generated server-side by
        // BaconQrCode; escaping it would render markup as text.
        'resources/views/profile/two-factor-authentication-form.blade.php' => 'Fortify 2FA QR code SVG (server-generated)',
    ];

    public function handle(): int
    {
        $pathOption = $this->option('path');
        $base = is_string($pathOption) && $pathOption !== '' ? $pathOption : resource_path('views');
        $base = rtrim($base, '/');

        if (! is_dir($base)) {
            $this->error("Directory not found: {$base}");

            return 1;
        }

        $finder = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base));
        $matches = [];

        foreach ($finder as $file) {
            if (! $file instanceof \SplFileInfo || ! $file->isFile()) {
                continue;
            }
            if ($file->getExtension() !== 'blade.php' && $file->getExtension() !== 'php') {
                continue;
            }

            $realPath = $file->getRealPath();
            if ($realPath === false) {
                continue;
            }

            $relative = str_replace(base_path().'/', '', $realPath);
            $relativeToBase = ltrim(substr($realPath, strlen((string) realpath($base))), '/');

            // Vendor-published views (mail templates etc.) render raw
            // framework-generated HTML by upstream design.
            if (str_starts_with($relativeToBase, 'vendor/')) {
                continue;
            }

            if (array_key_exists($relative, self::ALLOWED_RAW_OUTPUT)) {
                continue;
            }

            $contents = (string) file_get_contents($realPath);
            if (str_contains($contents, '{!!') || preg_match('/<\?=|echo\s+[^;]+;/i', $contents)) {
                $matches[] = $realPath;
            }
        }

        if (count($matches) > 0) {
            $this->error('Found unescaped Blade/PHP output in templates:');
            foreach ($matches as $match) {
                $this->line(' - '.str_replace(base_path().'/', '', $match));
            }
            $this->error('Escape with {{ }} instead, or add a justified entry to the allowlist in '.self::class);

            return 2;
        }

        $this->info('No unescaped outputs found in Blade templates.');

        return 0;
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ScanBladeUnescaped extends Command
{
    protected $signature = 'scan:blade-unescaped';

    protected $description = 'Scan Blade templates for unescaped output patterns (e.g. {!!)';

    public function handle(): int
    {
        $base = base_path('resources/views');
        $finder = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base));
        $matches = [];

        foreach ($finder as $file) {
            if (! $file->isFile()) {
                continue;
            }
            if ($file->getExtension() !== 'blade.php' && $file->getExtension() !== 'php') {
                continue;
            }

            $rawContents = file_get_contents($file->getRealPath());

            if ($rawContents === false) {
                continue;
            }

            $contents = $rawContents;

            // 1. Detect {!! unescaped output.
            //    Safe patterns are explicitly allowed:
            //      - {!! e($...)        : content pre-escaped with e()
            //      - {!! app(...MentionParser...)->highlight(e( : pre-escaped body via MentionParser
            //      - {!! $attributes->merge( : Blade component attribute merging (Jetstream core)
            //      - {!! __(            : Translation strings with HTML (Jetstream core)
            //      - {!! $this->user->twoFactor : Server-generated QR SVG (Jetstream core)
            //      - {!! $terms !!} / {!! $policy !!} : Static markdown files rendered via
            //        Str::markdown(..., ['html_input' => 'strip']) — HTML input is stripped
            $hasBareUnescaped = false;
            foreach (explode("\n", $contents) as $line) {
                if (strpos($line, '{!!') !== false) {
                    $isSafe = preg_match(
                        '/\{!!\s*(?:e\(|\s*app\([^)]*MentionParser|\$attributes->merge\(|__\(|\$this->user->twoFactor|\$(terms|policy)\s*!!)/',
                        $line
                    );
                    if (! $isSafe) {
                        $hasBareUnescaped = true;
                        break;
                    }
                }
            }

            // 2. Detect PHP short echo tags (<?=).
            $hasShortEcho = preg_match('/<\?=/', $contents);

            // 3. Detect PHP echo statements inside @php blocks.
            //    Negative lookbehind (?<![.\w]) prevents false positives from
            //    JavaScript references like window.Echo or Laravel Echo library.
            //    Case-sensitive: PHP echo is always lowercase in this codebase.
            $hasPhpEcho = preg_match('/(?<![.\w])echo\s+[^;]+;/', $contents);

            if ($hasBareUnescaped || $hasShortEcho || $hasPhpEcho) {
                $matches[] = $file->getRealPath();
            }
        }

        if (count($matches) > 0) {
            $this->error('Found unescaped Blade/PHP output in templates:');
            foreach ($matches as $m) {
                $this->line(' - '.str_replace(base_path().'/', '', $m));
            }
            $this->error('Please replace raw outputs with escaped output using {{ }} or e()');

            return 2;
        }

        $this->info('No unescaped outputs found in Blade templates.');

        return 0;
    }
}

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

            $contents = file_get_contents($file->getRealPath());
            if (strpos($contents, '{!!') !== false || preg_match('/<\?=|echo\s+[^;]+;/i', $contents)) {
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

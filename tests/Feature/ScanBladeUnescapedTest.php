<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The blade-escape scanner is a CI gate: it fails on any {!! !!}, <?=, or
 * echo in Blade templates unless the file carries a justified entry on the
 * command's allowlist (trusted-by-provenance raw output) or is a
 * vendor-published view (raw by upstream design).
 */
class ScanBladeUnescapedTest extends TestCase
{
    private string $fixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixtures = storage_path('framework/testing/blade-scan-fixtures');
        File::deleteDirectory($this->fixtures);
        File::ensureDirectoryExists($this->fixtures.'/vendor/mail');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->fixtures);
        parent::tearDown();
    }

    public function test_passes_on_a_clean_tree(): void
    {
        File::put($this->fixtures.'/clean.blade.php', '<p>{{ $safe }}</p>');

        $this->artisan('scan:blade-unescaped', ['--path' => $this->fixtures])
            ->assertExitCode(0);
    }

    public function test_fails_on_raw_blade_output(): void
    {
        File::put($this->fixtures.'/dirty.blade.php', '<p>{!! $danger !!}</p>');

        $this->artisan('scan:blade-unescaped', ['--path' => $this->fixtures])
            ->assertExitCode(2);
    }

    public function test_fails_on_php_echo(): void
    {
        File::put($this->fixtures.'/echoing.blade.php', '<?php echo $danger; ?>');

        $this->artisan('scan:blade-unescaped', ['--path' => $this->fixtures])
            ->assertExitCode(2);
    }

    public function test_ignores_vendor_published_views(): void
    {
        File::put($this->fixtures.'/vendor/mail/layout.blade.php', '{!! $header !!}');

        $this->artisan('scan:blade-unescaped', ['--path' => $this->fixtures])
            ->assertExitCode(0);
    }

    public function test_the_real_view_tree_is_clean(): void
    {
        // The actual CI gate: every raw-output site in resources/views is
        // either fixed or carries a justified allowlist entry.
        $this->artisan('scan:blade-unescaped')->assertExitCode(0);
    }
}

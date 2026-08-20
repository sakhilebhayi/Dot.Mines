<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards the single-Alpine-instance invariant.
 *
 * Livewire v3 bundles and starts its own Alpine and assigns it to
 * `window.Alpine`. Its dist also contains BARE `Alpine.…` references (e.g.
 * `Alpine.reactive()` in the Component constructor) that resolve to the
 * global at call time. If application code assigns a second Alpine to
 * `window.Alpine`, component data and DOM x-data scopes end up on two
 * different reactivity engines and every server->client `entangle()` sync
 * dies silently — confirmed live in production: no Jetstream
 * confirms-password modal (including the 2FA Enable flow) could open.
 */
class SingleAlpineInstanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_app_js_does_not_import_or_assign_a_second_alpine(): void
    {
        $appJs = file_get_contents(resource_path('js/app.js'));

        $this->assertDoesNotMatchRegularExpression(
            '/^\s*import\s+.*[\'"]alpinejs[\'"]/m',
            $appJs,
            'resources/js/app.js must not import alpinejs — Livewire bundles and owns Alpine.'
        );

        $this->assertDoesNotMatchRegularExpression(
            '/window\.Alpine\s*=/',
            $appJs,
            'resources/js/app.js must not assign window.Alpine — overwriting it splits the page across two reactivity engines and breaks entangle().'
        );
    }

    public function test_no_frontend_source_imports_alpinejs(): void
    {
        $offenders = [];

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('js'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if ($file->getExtension() !== 'js') {
                continue;
            }

            if (preg_match('/^\s*import\s+.*[\'"]alpinejs[\'"]/m', file_get_contents($file->getPathname()))) {
                $offenders[] = $file->getPathname();
            }
        }

        $this->assertSame([], $offenders, 'These files import alpinejs, which would bundle a second Alpine instance alongside Livewire\'s: '.implode(', ', $offenders));
    }

    public function test_authenticated_layout_does_not_mask_the_duplicate_alpine_warning(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        // __fromLivewire on window.Alpine suppressed Livewire's "multiple
        // instances of Alpine" warning — the warning that would have exposed
        // the broken 2FA modal immediately. It must stay audible.
        $this->assertStringNotContainsString('__fromLivewire', $response->getContent());
    }
}

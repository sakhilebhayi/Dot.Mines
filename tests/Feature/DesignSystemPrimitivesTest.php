<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Slice 1 of the live-operations UX program: every shared primitive must
 * render in the ink/gold token system (the modal wrapper was still the
 * stock Jetstream gray box), and the reusable skeleton/freshness/busy
 * patterns exist for the loading/lazy and freshness slices to build on.
 */
class DesignSystemPrimitivesTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_renders_no_legacy_gray_modal_chrome(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $response = $this->actingAs($user)->get('/user/profile');

        $response->assertOk();
        // The profile page renders the full Jetstream modal chain inline
        // (confirms-password, logout-other-sessions, delete-user), so this
        // guards the wrapper for every consumer at once.
        $this->assertStringNotContainsString('bg-gray-800', $response->getContent());
        $this->assertStringNotContainsString('bg-gray-500', $response->getContent());
    }

    public function test_dropdown_defaults_are_token_surfaces_not_white(): void
    {
        $html = Blade::render(
            '<x-dropdown><x-slot name="trigger">t</x-slot><x-slot name="content"><x-dropdown-link href="#">Item</x-dropdown-link></x-slot></x-dropdown>'
        );

        $this->assertStringNotContainsString('bg-white\'', $html);
        $this->assertStringContainsString('bg-[var(--ink-soft)]', $html);
        $this->assertStringNotContainsString('text-gray-700', $html);
    }

    public function test_skeleton_variants_render_pulsing_shapes(): void
    {
        foreach (['text', 'card', 'kpi', 'row', 'chart'] as $variant) {
            $html = Blade::render('<x-skeleton :variant="$variant" />', ['variant' => $variant]);

            $this->assertStringContainsString('animate-pulse', $html, "skeleton {$variant}");
            $this->assertStringContainsString('role="status"', $html, "skeleton {$variant}");
        }

        $text = Blade::render('<x-skeleton variant="text" :lines="5" />');
        $this->assertSame(5, substr_count($text, 'h-3 rounded'));
    }

    public function test_freshness_renders_relative_time_and_stale_state(): void
    {
        $fresh = Blade::render('<x-freshness :timestamp="now()->subSeconds(30)" />');
        $this->assertStringContainsString('Updated', $fresh);
        $this->assertStringContainsString('<time', $fresh);
        $this->assertStringNotContainsString('text-amber-400"', substr($fresh, strpos($fresh, '<time')));

        $stale = Blade::render('<x-freshness :timestamp="now()->subMinutes(30)" :stale-after="300" />');
        $this->assertStringContainsString('text-amber-400', $stale);

        $empty = Blade::render('<x-freshness :timestamp="null" />');
        $this->assertStringContainsString('No data yet', $empty);
    }

    public function test_busy_button_disables_and_spins_scoped_to_its_target(): void
    {
        $html = Blade::render('<x-busy-button target="saveThing">Save</x-busy-button>');

        $this->assertStringContainsString('wire:loading.attr="disabled"', $html);
        $this->assertStringContainsString('wire:target="saveThing"', $html);
        $this->assertStringContainsString('animate-spin', $html);
        $this->assertStringContainsString('Save', $html);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Readiness slice 6 (§18): accessibility basics on the app shell.
 * Icon-only controls carry accessible names (the notification bell also
 * announces its unread count), and keyboard users get a skip link to the
 * main landmark on every authenticated page.
 */
class AccessibilityBasicsTest extends TestCase
{
    use RefreshDatabase;

    private function authenticatedPage(): TestResponse
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        return $this->actingAs($user)->get('/dashboard');
    }

    public function test_the_app_shell_provides_a_skip_link_to_the_main_landmark(): void
    {
        $response = $this->authenticatedPage();

        $response->assertOk();
        $response->assertSee('Skip to main content');
        $response->assertSee('id="main-content"', false);
    }

    public function test_icon_only_shell_controls_carry_accessible_names(): void
    {
        $response = $this->authenticatedPage();

        $response->assertOk();
        // Notification bell (icon-only, with SR-announced unread state).
        $response->assertSee('aria-label="Notifications', false);
        // Account menu trigger (icon-only on small screens).
        $response->assertSee('aria-label="Open account menu"', false);
        // Mobile navigation toggle (pre-existing, pinned so it stays).
        $response->assertSee('aria-label="Toggle navigation menu"', false);
    }
}

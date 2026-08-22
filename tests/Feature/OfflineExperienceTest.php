<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Tests\TestCase;

/**
 * Hybrid architecture Slice 2: the offline shell, the connectivity pill,
 * and the session-authenticated sync context the browser layer boots from.
 */
class OfflineExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_offline_shell_renders_for_guests_without_touching_auth(): void
    {
        $response = $this->get('/offline');

        $response->assertOk();
        $response->assertSee('offline-snapshot', false);
        $response->assertSee("You're offline", false);
    }

    public function test_service_worker_ships_from_the_public_root(): void
    {
        $this->assertFileExists(public_path('sw.js'));
        $this->assertStringContainsString(
            "'/offline'",
            (string) file_get_contents(public_path('sw.js')),
            'The worker must precache the offline shell it falls back to.',
        );
    }

    public function test_api_requests_are_stateful_for_the_first_party_sync_client(): void
    {
        $group = app('router')->getMiddlewareGroups()['api'] ?? [];

        $this->assertContains(
            EnsureFrontendRequestsAreStateful::class,
            $group,
            'statefulApi() must be active or the browser sync client cannot authenticate by session cookie.',
        );
    }

    public function test_authenticated_pages_carry_the_sync_context_and_pill(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('data-connectivity-pill', false);
        $response->assertSee('window.__syncContext', false);
        $response->assertSee((string) $user->current_team_id, false);
    }

    public function test_guest_pages_never_leak_a_sync_context(): void
    {
        $response = $this->get('/offline');

        $response->assertDontSee('window.__syncContext', false);
    }
}

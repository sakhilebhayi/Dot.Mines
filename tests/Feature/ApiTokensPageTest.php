<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression test: Jetstream's api-tokens.index route only registers when
 * Features::api() is enabled in config/jetstream.php -- it was commented
 * out, so resources/views/api/index.blade.php and the api.api-token-manager
 * Livewire component (both fully built) were completely unroutable. The
 * Documentation page's own "Generate API Token" instructions pointed at a
 * page that didn't exist. Enabled the feature and linked it from the navbar
 * account menu.
 */
class ApiTokensPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_away_from_api_tokens(): void
    {
        $response = $this->get('/user/api-tokens');

        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_the_api_tokens_page(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        $response = $this->actingAs($user)->get(route('api-tokens.index'));

        $response->assertOk();
        $response->assertSee('API Tokens');
    }
}

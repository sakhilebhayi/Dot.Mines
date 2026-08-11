<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression test: every content section on /documentation wrapped its text
 * in Tailwind Typography's `prose` class with no `prose-invert`, so on this
 * app's always-dark background the body text rendered in prose's
 * light-mode dark gray/black palette -- effectively invisible. Added
 * `prose-invert` across all sections.
 */
class DocumentationPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_away_from_documentation(): void
    {
        $response = $this->get('/documentation');

        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_with_a_team_can_view_documentation(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        $response = $this->actingAs($user)->get('/documentation');

        $response->assertOk();
        $response->assertSee('Welcome to Mines Fleet Manager');
        $response->assertSee('prose-invert', false);
    }
}

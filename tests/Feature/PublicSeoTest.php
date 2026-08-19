<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Production-readiness slice 4: SEO and web standards for public pages
 * (§20). Public marketing/legal pages are indexable and listed in the
 * sitemap; authenticated operational pages are robots-blocked AND carry a
 * binding X-Robots-Tag noindex header (robots.txt is advisory only).
 */
class PublicSeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_sitemap_lists_public_pages_and_no_authenticated_routes(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml');
        $content = $response->getContent();

        $this->assertStringContainsString(route('welcome'), $content);
        $this->assertStringContainsString(route('pricing'), $content);
        $this->assertStringContainsString(route('cookies'), $content);

        foreach (['/dashboard', '/fleet', '/billing', '/settings', '/gdpr', '/reports'] as $private) {
            $this->assertStringNotContainsString('<loc>'.config('app.url').$private, $content, "Sitemap must never expose {$private}.");
        }
    }

    public function test_authenticated_pages_carry_a_binding_noindex_header(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_public_pages_remain_indexable(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $this->assertNull($response->headers->get('X-Robots-Tag'), 'Marketing pages must stay indexable.');
    }

    public function test_robots_txt_blocks_operational_paths_and_advertises_the_sitemap(): void
    {
        $robots = (string) file_get_contents(public_path('robots.txt'));

        $this->assertStringContainsString('Disallow: /dashboard', $robots);
        $this->assertStringContainsString('Disallow: /billing', $robots);
        $this->assertStringContainsString('Disallow: /gdpr', $robots);
        $this->assertStringContainsString('Sitemap:', $robots);
    }
}

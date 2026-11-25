<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use App\Services\InfoDot\PlatformAvailability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

/**
 * R-61: the AVAILABILITY-ONLY check against InfoDot, behind MINES_AVAILABILITY_CHECK (default false).
 *
 * Mines decides its own access, so `entitled` must never refuse anyone, and nothing a machine, device, webhook or
 * data-subject request relies on may ever be switched off by InfoDot's maintenance switch.
 */
class PlatformAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private const COMPANY = 'aaaaaaaa-1111-4111-8111-aaaaaaaaaaaa';

    /** Signed-in web screens the check guards. */
    private const GUARDED = ['/dashboard', '/fleet', '/alerts', '/settings', '/billing', '/reports', '/production', '/search?q=x', '/teams/create'];

    private function turnOn(): void
    {
        config([
            'ecosystem.availability.check' => true,
            'ecosystem.infodot.secret' => 'test-secret',
            'ecosystem.infodot.redeem_url' => 'https://infodot.test/api/ecosystem/redeem',
            'ecosystem.infodot.platform' => 'mines',
        ]);
    }

    private function person(?string $uuid = self::COMPANY, bool $personal = false): User
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id, 'personal_team' => $personal]);
        $team->forceFill(['infodot_team_uuid' => $uuid])->save();
        $user->update(['current_team_id' => $team->id]);

        return $user;
    }

    /** @param array<string, mixed> $row */
    private function answer(array $row = [], string $uuid = self::COMPANY): array
    {
        return [
            'contract' => 2,
            'company' => ['uuid' => $uuid],
            'platforms' => [
                ['key' => 'tasks', 'entitled' => true, 'available' => true],
                array_merge(['key' => 'mines', 'entitled' => true, 'status' => 'active', 'available' => true, 'state' => 'operational', 'message' => null], $row),
            ],
        ];
    }

    /** What the faked InfoDot says next; swapped between requests to model a switch or an outage. */
    private mixed $reply = null;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake(['infodot.test/*' => fn () => ($this->reply)(), 'other.test/*' => fn () => ($this->reply)()]);
    }

    private function infodotSays(array $row = []): void
    {
        $this->reply = fn () => Http::response($this->answer($row));
    }

    private function infodotSaysRaw(array $body): void
    {
        $this->reply = fn () => Http::response($body);
    }

    /** Never our own 503 page or JSON, whatever else the route answers. */
    private function assertNotOurRefusal(TestResponse $response, string $label = ''): void
    {
        $this->assertStringNotContainsString('not available right now', (string) $response->getContent(), $label);
        $this->assertStringNotContainsString('platform_unavailable', (string) $response->getContent(), $label);
        $this->assertNull($response->headers->get('Retry-After'), $label);
    }

    private function unavailable(?string $message = 'Back at 18:00.'): void
    {
        $this->infodotSays(['available' => false, 'state' => 'maintenance', 'message' => $message]);
    }

    private function infodotDown(): void
    {
        $this->reply = fn () => Http::response('', 500);
    }

    // ---------------------------------------------------------------- defaults and the off state

    public function test_the_flag_defaults_to_false(): void
    {
        $this->assertFalse(config('ecosystem.availability.check'));
        $this->assertFalse(app(PlatformAvailability::class)->enabled());
        $this->assertSame(3, config('ecosystem.availability.timeout'));
        $this->assertNull(config('ecosystem.availability.url'));
        $this->assertStringContainsString("MINES_AVAILABILITY_CHECK=false\n", (string) file_get_contents(base_path('.env.example')));
    }

    public function test_with_the_flag_off_no_call_is_made_and_no_screen_changes(): void
    {
        config([
            'ecosystem.infodot.secret' => 'test-secret',
            'ecosystem.infodot.redeem_url' => 'https://infodot.test/api/ecosystem/redeem',
        ]);
        $this->unavailable();
        $user = $this->person();

        foreach (self::GUARDED as $path) {
            $this->assertNotSame(503, $this->actingAs($user)->get($path)->getStatusCode(), $path);
        }
        $this->actingAs($user)->getJson('/dashboard')->assertStatus(200);
        $this->actingAs($user)->post('/livewire/update', ['components' => []]);

        Http::assertNothingSent();
        $this->assertNull(Cache::get('infodot:availability:mines:'.self::COMPANY));
        $this->assertNull(Cache::get('infodot:availability:mines:'.self::COMPANY.':last'));
    }

    public function test_the_flag_only_counts_when_exactly_true(): void
    {
        config(['ecosystem.availability.check' => 'yes']);

        $this->assertFalse(app(PlatformAvailability::class)->enabled());
    }

    // ---------------------------------------------------------------- the refusal

    public function test_an_unavailable_platform_answers_503_html_for_a_linked_team(): void
    {
        $this->turnOn();
        $this->unavailable('Back at 18:00.');
        $user = $this->person();

        foreach (self::GUARDED as $path) {
            $response = $this->actingAs($user)->get($path);
            $response->assertStatus(503)
                ->assertHeader('Retry-After', '60')
                ->assertSee('Dot.Mines is not available right now', false)
                ->assertSee('Back at 18:00.', false);
            $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'), $path);
        }
    }

    public function test_an_unavailable_platform_answers_503_json(): void
    {
        $this->turnOn();
        $this->unavailable('Back at 18:00.');
        $user = $this->person();

        $this->actingAs($user)->getJson('/dashboard')
            ->assertStatus(503)
            ->assertHeader('Retry-After', '60')
            ->assertExactJson(['message' => 'Back at 18:00.', 'reason' => 'platform_unavailable']);
    }

    public function test_an_unavailable_platform_answers_503_json_to_livewire(): void
    {
        $this->turnOn();
        $this->unavailable();
        $user = $this->person();

        $this->actingAs($user)->post('/livewire/update', ['components' => []])
            ->assertStatus(503)
            ->assertHeader('Retry-After', '60')
            ->assertJson(['reason' => 'platform_unavailable']);

        $this->actingAs($user)->post('/livewire/upload-file')->assertStatus(503);
    }

    public function test_a_livewire_component_on_an_account_security_page_keeps_working(): void
    {
        $this->turnOn();
        $this->unavailable();
        $user = $this->person();
        $snapshot = json_encode(['data' => [], 'memo' => ['id' => 'x', 'name' => 'profile.two-factor-authentication-form', 'path' => 'user/profile', 'method' => 'GET']]);

        $response = $this->actingAs($user)->post('/livewire/update', ['components' => [['snapshot' => $snapshot, 'updates' => [], 'calls' => []]]]);

        $this->assertNotSame(503, $response->getStatusCode());
        Http::assertNothingSent();
    }

    public function test_a_missing_message_falls_back_to_plain_words(): void
    {
        $this->turnOn();
        $this->unavailable(null);

        $this->actingAs($this->person())->get('/dashboard')->assertStatus(503)->assertSee('Dot.Mines is not available right now', false);
    }

    public function test_the_message_is_escaped(): void
    {
        $this->turnOn();
        $this->unavailable('<script>alert(1)</script> & "quotes"');

        $response = $this->actingAs($this->person())->get('/dashboard');

        $response->assertStatus(503)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_the_page_asks_for_the_right_url_with_the_secret_and_platform_header(): void
    {
        $this->turnOn();
        $this->unavailable();

        $this->actingAs($this->person())->get('/dashboard')->assertStatus(503);

        Http::assertSent(fn (Request $r) => str_starts_with($r->url(), 'https://infodot.test/api/ecosystem/companies/'.self::COMPANY.'/platforms')
            && $r->hasHeader('Authorization', 'Bearer test-secret')
            && $r->hasHeader('X-Dot-Platform', 'mines'));
    }

    public function test_the_url_can_be_overridden_with_a_uuid_placeholder(): void
    {
        $this->turnOn();
        config(['ecosystem.availability.url' => 'https://other.test/x/{uuid}/platforms']);
        $this->infodotSays(['available' => false]);

        $this->actingAs($this->person())->get('/dashboard')->assertStatus(503);

        Http::assertSent(fn (Request $r) => str_starts_with($r->url(), 'https://other.test/x/'.self::COMPANY.'/platforms'));
    }

    // ---------------------------------------------------------------- passes

    public function test_an_available_platform_passes(): void
    {
        $this->turnOn();
        $this->infodotSays(['available' => true]);

        $this->actingAs($this->person())->get('/dashboard')->assertOk();
    }

    public function test_a_missing_available_key_counts_as_available(): void
    {
        $this->turnOn();
        $this->infodotSaysRaw([
            'company' => ['uuid' => self::COMPANY],
            'platforms' => [['key' => 'mines', 'entitled' => true, 'status' => 'active']],
        ]);

        $this->actingAs($this->person())->get('/dashboard')->assertOk();
    }

    public function test_entitled_false_never_refuses_anyone(): void
    {
        $this->turnOn();
        $this->infodotSays(['entitled' => false, 'status' => 'suspended', 'available' => true]);

        $user = $this->person();
        $this->actingAs($user)->get('/dashboard')->assertOk();
        $this->actingAs($user)->getJson('/dashboard')->assertOk();
    }

    public function test_an_unreadable_entitled_value_is_not_needed(): void
    {
        $this->turnOn();
        $this->infodotSaysRaw([
            'company' => ['uuid' => self::COMPANY],
            'platforms' => [['key' => 'mines', 'available' => false]],
        ]);

        $this->actingAs($this->person())->get('/dashboard')->assertStatus(503);
    }

    public function test_the_answer_for_another_platform_or_company_is_not_used(): void
    {
        $this->turnOn();
        $this->infodotSaysRaw([
            'company' => ['uuid' => self::COMPANY],
            'platforms' => [['key' => 'tasks', 'available' => false]],
        ]);
        $user = $this->person();
        $this->actingAs($user)->get('/dashboard')->assertOk();

        Cache::flush();
        $this->infodotSaysRaw($this->answer(['available' => false], 'bbbbbbbb-2222-4222-8222-bbbbbbbbbbbb'));
        $this->actingAs($user)->get('/dashboard')->assertOk();
    }

    // ---------------------------------------------------------------- who is asked

    public function test_a_team_with_no_infodot_company_is_never_asked_or_blocked(): void
    {
        $this->turnOn();
        $this->unavailable();

        foreach ([null, '', '   '] as $uuid) {
            $this->actingAs($this->person($uuid))->get('/dashboard')->assertOk();
        }

        Http::assertNothingSent();
    }

    public function test_a_personal_team_is_never_asked_or_blocked(): void
    {
        $this->turnOn();
        $this->unavailable();

        $this->actingAs($this->person(self::COMPANY, personal: true))->get('/dashboard')->assertOk();

        Http::assertNothingSent();
    }

    public function test_a_guest_is_never_asked(): void
    {
        $this->turnOn();
        $this->unavailable();

        $this->get('/dashboard')->assertRedirect();
        $this->post('/livewire/update', ['components' => []]);

        Http::assertNothingSent();
    }

    // ---------------------------------------------------------------- caching and failure rules

    public function test_an_answer_is_kept_for_sixty_seconds(): void
    {
        $this->turnOn();
        $this->unavailable();
        $user = $this->person();

        $this->actingAs($user)->get('/dashboard')->assertStatus(503);
        $this->travel(59)->seconds();
        $this->actingAs($user)->get('/fleet')->assertStatus(503);
        Http::assertSentCount(1);

        $this->travel(2)->seconds();
        $this->actingAs($user)->get('/fleet')->assertStatus(503);
        Http::assertSentCount(2);
    }

    public function test_a_switch_made_at_infodot_holds_within_a_minute(): void
    {
        $this->turnOn();
        $this->infodotSays(['available' => true]);
        $user = $this->person();
        $this->actingAs($user)->get('/dashboard')->assertOk();

        $this->unavailable();
        $this->travel(61)->seconds();

        $this->actingAs($user)->get('/dashboard')->assertStatus(503);
    }

    public function test_last_known_unavailable_is_kept_through_an_infodot_outage(): void
    {
        $this->turnOn();
        $this->unavailable('Back at 18:00.');
        $user = $this->person();
        $this->actingAs($user)->get('/dashboard')->assertStatus(503);

        $this->infodotDown();
        $this->travel(2)->hours();

        $this->actingAs($user)->get('/dashboard')->assertStatus(503)->assertSee('Back at 18:00.', false);
    }

    public function test_last_known_available_is_kept_through_an_infodot_outage(): void
    {
        $this->turnOn();
        $this->infodotSays(['available' => true]);
        $user = $this->person();
        $this->actingAs($user)->get('/dashboard')->assertOk();

        $this->reply = fn () => throw new ConnectionException('down');
        $this->travel(2)->hours();

        $this->actingAs($user)->get('/dashboard')->assertOk();
    }

    public function test_the_last_answer_stops_counting_after_six_hours(): void
    {
        $this->turnOn();
        $this->unavailable();
        $user = $this->person();
        $this->actingAs($user)->get('/dashboard')->assertStatus(503);

        $this->infodotDown();
        $this->travel(6)->hours();
        $this->travel(5)->seconds();

        $this->actingAs($user)->get('/dashboard')->assertOk();
    }

    public function test_with_no_answer_ever_it_fails_open(): void
    {
        $this->turnOn();
        $this->infodotDown();

        $this->actingAs($this->person())->get('/dashboard')->assertOk();
    }

    public function test_a_malformed_body_fails_open(): void
    {
        $this->turnOn();
        $this->infodotSaysRaw(['nonsense' => true]);

        $this->actingAs($this->person())->get('/dashboard')->assertOk();
    }

    public function test_a_missing_secret_fails_open_without_a_call(): void
    {
        $this->turnOn();
        config(['ecosystem.infodot.secret' => '']);
        $this->unavailable();

        $this->actingAs($this->person())->get('/dashboard')->assertOk();

        Http::assertNothingSent();
    }

    public function test_a_missing_url_fails_open_without_a_call(): void
    {
        $this->turnOn();
        config(['ecosystem.infodot.redeem_url' => '']);
        $this->unavailable();

        $this->actingAs($this->person())->get('/dashboard')->assertOk();

        Http::assertNothingSent();
    }

    public function test_a_broken_cache_never_throws_into_a_request(): void
    {
        $this->turnOn();
        $this->unavailable();
        Cache::shouldReceive('get')->andThrow(new RuntimeException('cache down'));
        Cache::shouldReceive('put')->andThrow(new RuntimeException('cache down'));

        $answer = app(PlatformAvailability::class)->of(self::COMPANY);

        $this->assertSame(['available' => false, 'message' => 'Back at 18:00.'], $answer);
    }

    // ---------------------------------------------------------------- what is never blocked

    public function test_machine_and_api_traffic_is_never_blocked_or_asked(): void
    {
        $this->turnOn();
        $this->unavailable();
        $user = $this->person();
        Sanctum::actingAs($user, ['*']);

        foreach (['/api/machines', '/api/v1/machines', '/api/sync', '/api/alerts', '/api/user', '/api/fuel/tanks'] as $path) {
            $this->assertNotSame(503, $this->getJson($path)->getStatusCode(), $path);
        }
        foreach (['/api/machines', '/api/v1/machines', '/api/v1/alerts'] as $path) {
            $this->assertNotSame(503, $this->postJson($path, [])->getStatusCode(), $path);
        }
        $this->assertNotSame(503, $this->getJson('/api/openapi.json')->getStatusCode());

        Http::assertNothingSent();
    }

    public function test_webhooks_health_and_public_pages_are_never_blocked(): void
    {
        $this->turnOn();
        $this->unavailable();
        $user = $this->person();

        $this->assertNotSame(503, $this->actingAs($user)->post('/webhooks/paystack', [])->getStatusCode());
        $this->assertNotOurRefusal($this->actingAs($user)->get('/guardian/health'), 'guardian');
        foreach (['/', '/offline', '/features', '/pricing', '/capabilities', '/cookies', '/terms-of-service', '/privacy-policy', '/core-features/fuel'] as $path) {
            $this->actingAs($user)->get($path)->assertOk();
        }
        // Health probes may answer 503 for their own reasons; what matters is that the check is never asked.
        foreach (['/health', '/ready', '/up/realtime'] as $path) {
            $this->assertNotOurRefusal($this->actingAs($user)->get($path), $path);
        }
        $this->actingAs($user)->get('/sitemap.xml')->assertOk();
        $this->actingAs($user)->get('/up')->assertOk();

        Http::assertNothingSent();
    }

    public function test_signed_urls_and_the_guardian_token_route_are_never_blocked(): void
    {
        $this->turnOn();
        $this->unavailable();
        $user = $this->person();
        $signed = URL::signedRoute('email.unsubscribe', ['user' => $user->id, 'type' => 'alert_notifications']);

        $this->assertNotSame(503, $this->actingAs($user)->get($signed)->getStatusCode());
        $this->assertNotOurRefusal($this->get('/guardian/health'), 'guardian');
        $this->assertNotSame(503, $this->actingAs($user)->get('/reports/999999/download')->getStatusCode());
        $this->assertNotSame(503, $this->actingAs($user)->get('/mine-plans/999999/download')->getStatusCode());
        $this->assertNotSame(503, $this->actingAs($user)->get('/operator-documents/999999/download')->getStatusCode());

        Http::assertNothingSent();
    }

    public function test_sign_in_sign_out_two_factor_and_the_handoff_are_never_blocked(): void
    {
        $this->turnOn();
        $this->unavailable();
        $user = $this->person();

        $this->get('/login')->assertOk();
        $this->assertNotSame(503, $this->get('/two-factor-challenge')->getStatusCode());
        $this->assertNotSame(503, $this->post('/auth/ecosystem', ['token' => 'x'])->getStatusCode());
        $this->assertNotSame(503, $this->get('/auth/ecosystem')->getStatusCode());
        $this->assertNotSame(503, $this->get('/sso/login')->getStatusCode());
        $this->assertNotSame(503, $this->actingAs($user)->get('/user/profile')->getStatusCode());
        $this->assertNotSame(503, $this->actingAs($user)->get('/user/confirm-password')->getStatusCode());
        $this->assertNotSame(503, $this->actingAs($user)->post('/user/two-factor-authentication')->getStatusCode());
        $this->assertNotSame(503, $this->actingAs($user)->put('/current-team', ['team_id' => $user->current_team_id])->getStatusCode());
        $this->actingAs($user)->post('/logout')->assertRedirect();

        Http::assertNothingSent();
    }

    public function test_the_data_subject_routes_are_never_blocked(): void
    {
        $this->turnOn();
        $this->unavailable();
        $user = $this->person();

        $this->actingAs($user)->get('/gdpr')->assertOk();
        $this->assertNotSame(503, $this->actingAs($user)->post('/gdpr/export')->getStatusCode());
        $this->assertNotSame(503, $this->actingAs($user)->get('/gdpr/download/not-a-token')->getStatusCode());
        $this->assertNotSame(503, $this->actingAs($user)->post('/gdpr/delete')->getStatusCode());

        Http::assertNothingSent();
    }

    public function test_broadcast_auth_for_realtime_is_never_blocked(): void
    {
        $this->turnOn();
        $this->unavailable();
        $user = $this->person();

        $this->assertNotSame(503, $this->actingAs($user)->post('/broadcasting/auth', ['channel_name' => 'private-team.1', 'socket_id' => '1.2'])->getStatusCode());

        Http::assertNothingSent();
    }
}

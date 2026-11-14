<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamRoleProvisioner;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * R-52 section 6: Dot.Mines receives InfoDot's launch form. Mines only LINKS existing accounts, never trusts the
 * browser, never puts a token in a URL or a log, and keeps its own two-factor and tenant checks.
 */
class EcosystemSsoReceiverTest extends TestCase
{
    use RefreshDatabase;

    private const REDEEM = 'https://infodot.test/api/ecosystem/redeem';

    private const ORIGIN = 'https://infodot.test';

    private const TOKEN = '41|launch-token-must-never-leak-aaaaaaaaaaaaaaaa';

    private const SECRET = 'mines-service-secret-must-never-leak-bbbbbbbb';

    /** @var list<string> every message and context the app logged while the test ran */
    private array $logged = [];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ecosystem.infodot.redeem_url' => self::REDEEM,
            'ecosystem.infodot.secret' => self::SECRET,
            'ecosystem.infodot.platform' => 'mines',
            'ecosystem.infodot.provision' => false,
            'ecosystem.platforms.infodot.url' => self::ORIGIN,
        ]);

        $this->logged = [];
        Event::listen(MessageLogged::class, function (MessageLogged $event): void {
            $this->logged[] = $event->message.' '.json_encode($event->context);
        });
    }

    /** @return array{0: User, 1: Team} */
    private function minesUser(string $email = 'thandi@example.test', string $role = 'viewer', ?string $teamUuid = null): array
    {
        $user = User::factory()->create(['email' => $email]);
        $team = Team::factory()->create(['user_id' => $user->id, 'personal_team' => false]);
        $team->forceFill(['infodot_team_uuid' => $teamUuid])->save();
        $user->update(['current_team_id' => $team->id]);
        TeamRoleProvisioner::assignRole($user, $team, $role);

        return [$user, $team];
    }

    /** @return array<string, mixed> */
    private function identity(string $email = 'thandi@example.test', ?string $uuid = null, ?string $currentTeamUuid = null, array $user = []): array
    {
        return [
            'platform' => 'mines',
            'user' => array_merge([
                'id' => 7,
                'uuid' => $uuid ?? 'aaaaaaaa-1111-4111-8111-aaaaaaaaaaaa',
                'name' => 'Thandi Test',
                'email' => $email,
                'email_verified' => true,
            ], $user),
            'teams' => [],
            'current_team_id' => 3,
            'current_team_uuid' => $currentTeamUuid,
        ];
    }

    /** @param array<string, mixed> $identity */
    private function fakeRedeem(array $identity, int $status = 200): void
    {
        Http::fake([self::REDEEM => Http::response($status === 200 ? $identity : ['error' => 'invalid_request'], $status)]);
    }

    private function handoff(array $headers = ['Origin' => self::ORIGIN], string $token = self::TOKEN): TestResponse
    {
        return $this->withHeaders($headers)->post('/auth/ecosystem', ['token' => $token]);
    }

    private function assertNothingLeaked(): void
    {
        foreach ($this->logged as $line) {
            $this->assertStringNotContainsString(self::TOKEN, $line);
            $this->assertStringNotContainsString(self::SECRET, $line);
            $this->assertStringNotContainsString('thandi@example.test', $line);
        }
    }

    public function test_successful_handoff_signs_in_the_linked_user_and_selects_the_linked_team(): void
    {
        $companyUuid = 'bbbbbbbb-2222-4222-8222-bbbbbbbbbbbb';
        [$user, $first] = $this->minesUser();
        $other = Team::factory()->create(['user_id' => $user->id, 'personal_team' => false]);
        $other->forceFill(['infodot_team_uuid' => $companyUuid])->save();
        $user->forceFill(['infodot_user_uuid' => 'aaaaaaaa-1111-4111-8111-aaaaaaaaaaaa'])->save();
        $this->fakeRedeem($this->identity(currentTeamUuid: $companyUuid));

        $response = $this->handoff();

        $response->assertStatus(303)->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertSame($other->id, $user->fresh()->current_team_id);
        $this->assertNotSame($first->id, $user->fresh()->current_team_id);
        $this->assertSame('no-referrer', $response->headers->get('Referrer-Policy'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringNotContainsString('token', (string) $response->headers->get('Location'));

        Http::assertSent(function (HttpRequest $request): bool {
            return $request->url() === self::REDEEM
                && $request->hasHeader('Authorization', 'Bearer '.self::SECRET)
                && $request['token'] === self::TOKEN
                && $request['platform'] === 'mines';
        });
        $this->assertNothingLeaked();
    }

    public function test_a_verified_email_match_links_and_stamps_the_uuid_then_uuid_wins_over_a_changed_email(): void
    {
        [$user] = $this->minesUser('thandi@example.test');
        $this->fakeRedeem($this->identity('Thandi@Example.Test'));

        $this->handoff()->assertRedirect(route('dashboard'));
        $this->assertSame('aaaaaaaa-1111-4111-8111-aaaaaaaaaaaa', $user->fresh()->infodot_user_uuid);
        $this->post('/logout');

        // The person changes their address on InfoDot: the uuid, not the email, finds the Mines account.
        $this->fakeRedeem($this->identity('someone.new@example.test'));
        $this->handoff()->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_an_unlinked_person_gets_the_refusal_page_and_no_account_is_created(): void
    {
        $this->minesUser('someone.else@example.test');
        $users = User::count();
        $teams = Team::count();
        $this->fakeRedeem($this->identity('nobody@example.test'));

        $response = $this->handoff();

        $response->assertStatus(403)
            ->assertSee('not linked to a Dot.Mines account', false)
            ->assertSee('Sign in to Dot.Mines', false)
            ->assertSee('no_mines_account', false);
        $this->assertGuest();
        $this->assertSame($users, User::count());
        $this->assertSame($teams, Team::count());
        $this->assertTrue(collect($this->logged)->contains(fn ($l) => str_contains($l, 'Mines SSO refused') && str_contains($l, 'no_mines_account')));
        $this->assertNothingLeaked();
    }

    public function test_a_mines_account_with_an_unverified_email_is_refused_not_adopted(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'thandi@example.test']);
        $this->fakeRedeem($this->identity());

        $this->handoff()->assertStatus(403)->assertSee('mines_email_unverified', false);
        $this->assertGuest();
        $this->assertNull($user->fresh()->infodot_user_uuid);
    }

    public function test_an_unverified_infodot_identity_is_refused(): void
    {
        $this->minesUser();
        $this->fakeRedeem($this->identity(user: ['email_verified' => false]));

        $this->handoff()->assertStatus(403)->assertSee('identity_incomplete', false);
        $this->assertGuest();
    }

    public function test_a_mines_account_linked_to_another_identity_is_never_repointed(): void
    {
        [$user] = $this->minesUser();
        $user->forceFill(['infodot_user_uuid' => 'cccccccc-3333-4333-8333-cccccccccccc'])->save();
        $this->fakeRedeem($this->identity());

        $this->handoff()->assertStatus(403)->assertSee('mines_account_linked_to_another_identity', false);
        $this->assertGuest();
        $this->assertSame('cccccccc-3333-4333-8333-cccccccccccc', $user->fresh()->infodot_user_uuid);
    }

    public function test_wrong_secret_is_refused_and_logged_as_an_error(): void
    {
        $this->minesUser();
        $this->fakeRedeem([], 401);

        $this->handoff()->assertStatus(403)->assertSee('token_refused', false);
        $this->assertGuest();
        $this->assertTrue(collect($this->logged)->contains(fn ($l) => str_contains($l, 'InfoDot refused a handoff token') && str_contains($l, '401')));
        $this->assertNothingLeaked();
    }

    public function test_a_replayed_token_is_refused_the_second_time(): void
    {
        [$user] = $this->minesUser();
        Http::fake([self::REDEEM => Http::sequence()
            ->push($this->identity(), 200)
            ->push(['error' => 'invalid_request'], 403)]);

        $this->handoff()->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->post('/logout');

        $this->handoff()->assertStatus(403)->assertSee('expired or was already used', false);
        $this->assertGuest();
    }

    public function test_an_expired_token_is_refused(): void
    {
        $this->minesUser();
        $this->fakeRedeem([], 403);

        $this->handoff()->assertStatus(403)->assertSee('expired or was already used', false);
        $this->assertGuest();
    }

    public function test_infodot_being_down_says_try_again_instead_of_forbidden(): void
    {
        $this->minesUser();
        $this->fakeRedeem([], 503);
        $this->handoff()->assertStatus(503)->assertSee('infodot_unavailable', false)->assertSee('Try again from InfoDot', false);

        Http::fake([self::REDEEM => fn () => throw new ConnectionException('boom')]);
        $this->handoff()->assertStatus(503)->assertSee('infodot_unavailable', false);
        $this->assertGuest();
    }

    public function test_an_identity_minted_for_another_platform_is_refused(): void
    {
        $this->minesUser();
        $identity = $this->identity();
        $identity['platform'] = 'tasks';
        $this->fakeRedeem($identity);

        $this->handoff()->assertStatus(403)->assertSee('wrong_platform', false);
        $this->assertGuest();
    }

    public function test_a_wrong_or_missing_origin_is_refused_before_anything_is_redeemed(): void
    {
        $this->minesUser();
        $this->fakeRedeem($this->identity());

        $this->handoff(['Origin' => 'https://evil.test'])->assertStatus(403)->assertSee('origin_rejected', false);
        $this->handoff([])->assertStatus(403)->assertSee('origin_rejected', false);
        $this->handoff(['Origin' => 'null'])->assertStatus(403);
        $this->handoff(['Referer' => 'https://infodot.test.evil.test/launch'])->assertStatus(403);

        Http::assertNothingSent();
        $this->assertGuest();
    }

    public function test_the_referer_is_accepted_when_the_browser_sends_no_usable_origin(): void
    {
        [$user] = $this->minesUser();
        $this->fakeRedeem($this->identity());

        $this->handoff(['Origin' => 'null', 'Referer' => self::ORIGIN.'/go/mines'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_token_in_a_get_url_is_never_read(): void
    {
        $this->minesUser();
        $this->fakeRedeem($this->identity());

        $this->get('/auth/ecosystem?token='.urlencode(self::TOKEN))->assertRedirect('/login');

        Http::assertNothingSent();
        $this->assertGuest();
    }

    public function test_a_missing_token_and_an_unconfigured_receiver_are_refused_clearly(): void
    {
        $this->minesUser();
        $this->fakeRedeem($this->identity());

        $this->handoff(token: '')->assertStatus(400)->assertSee('token_missing', false);

        config(['ecosystem.infodot.secret' => '']);
        $this->handoff()->assertStatus(503)->assertSee('not_configured', false)->assertSee('Sign in to Dot.Mines', false);
        Http::assertNothingSent();
        $this->assertTrue(collect($this->logged)->contains(fn ($l) => str_contains($l, 'receiver not configured')));
    }

    public function test_only_this_route_is_csrf_exempt(): void
    {
        $middleware = app(ValidateCsrfToken::class);
        $exempt = new \ReflectionMethod($middleware, 'inExceptArray');

        $post = fn (string $uri) => Request::create($uri, 'POST');

        $this->assertTrue($exempt->invoke($middleware, $post('/auth/ecosystem')));
        $this->assertTrue($exempt->invoke($middleware, $post('/webhooks/paystack')));
        $this->assertFalse($exempt->invoke($middleware, $post('/login')));
        $this->assertFalse($exempt->invoke($middleware, $post('/logout')));
        $this->assertFalse($exempt->invoke($middleware, $post('/auth/ecosystem/other')));
        $this->assertFalse($exempt->invoke($middleware, $post('/dashboard')));
    }

    public function test_the_session_is_fresh_and_a_previous_person_on_the_browser_is_replaced(): void
    {
        [$user] = $this->minesUser('thandi@example.test');
        [$previous] = $this->minesUser('previous@example.test');
        $this->fakeRedeem($this->identity());

        $this->actingAs($previous)->withSession(['leftover' => 'from-the-previous-person']);
        $before = session()->getId();

        $this->handoff()->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($before, session()->getId());
        $this->assertNull(session('leftover'));
    }

    public function test_session_cookie_attributes_are_host_only_http_only_secure_and_lax(): void
    {
        // The documented production setting (.env.production.example / .env.example): SESSION_SAME_SITE=lax.
        config(['session.same_site' => 'lax']);
        $this->assertTrue(config('session.http_only'));
        $this->assertTrue(config('session.secure'));
        $this->assertNull(config('session.domain'));

        $this->minesUser();
        $this->fakeRedeem($this->identity());
        $cookie = collect($this->handoff()->headers->getCookies())->first(fn ($c) => $c->getName() === config('session.cookie'));

        $this->assertNotNull($cookie, 'the handoff sets the session cookie');
        $this->assertSame('lax', $cookie->getSameSite());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertTrue($cookie->isSecure());
        $this->assertNull($cookie->getDomain());
    }

    public function test_env_examples_ship_lax_and_document_the_receiver_with_provisioning_off(): void
    {
        foreach (['.env.example', '.env.production.example'] as $file) {
            $env = (string) file_get_contents(base_path($file));
            $this->assertMatchesRegularExpression('/^SESSION_SAME_SITE=lax$/m', $env, $file);
            $this->assertMatchesRegularExpression('/^INFODOT_SERVICE_SECRET=$/m', $env, $file);
            $this->assertMatchesRegularExpression('/^INFODOT_REDEEM_URL=https:\/\/infodot\.co\.za\/api\/ecosystem\/redeem$/m', $env, $file);
            $this->assertMatchesRegularExpression('/^INFODOT_PROVISION_USERS=false$/m', $env, $file);
        }
    }

    public function test_provisioning_defaults_to_false(): void
    {
        $original = (string) file_get_contents(base_path('config/ecosystem.php'));
        $this->assertStringContainsString("'provision' => filter_var(env('INFODOT_PROVISION_USERS', false)", $original);

        $config = require base_path('config/ecosystem.php');
        $this->assertFalse($config['infodot']['provision']);
        $this->assertSame('mines', $config['infodot']['platform']);
    }

    public function test_tenant_isolation_a_team_the_person_is_not_in_is_never_selected(): void
    {
        $foreignCompany = 'dddddddd-4444-4444-8444-dddddddddddd';
        [$user, $mine] = $this->minesUser();
        [, $foreign] = $this->minesUser('owner@other-company.test', 'admin', $foreignCompany);
        $this->fakeRedeem($this->identity(currentTeamUuid: $foreignCompany));

        $this->handoff()->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame($mine->id, $user->fresh()->current_team_id);
        $this->assertFalse($user->fresh()->belongsToTeam($foreign));
        $this->assertSame($mine->id, $user->fresh()->currentTeam?->id);
    }

    public function test_an_unlinked_or_missing_company_leaves_the_current_team_alone(): void
    {
        [$user, $mine] = $this->minesUser();

        foreach ([null, 'eeeeeeee-5555-4555-8555-eeeeeeeeeeee', 'not-a-uuid'] as $company) {
            $this->fakeRedeem($this->identity(currentTeamUuid: $company));
            $this->handoff()->assertRedirect(route('dashboard'));
            $this->assertSame($mine->id, $user->fresh()->current_team_id);
            $this->post('/logout');
        }
    }

    public function test_sso_does_not_launder_membership_the_existing_team_guard_still_applies(): void
    {
        [$user] = $this->minesUser();
        [, $foreign] = $this->minesUser('owner@other-company.test');
        $user->forceFill(['current_team_id' => $foreign->id])->save(); // a team the person is not in
        $this->fakeRedeem($this->identity());

        $this->handoff()->assertRedirect(route('dashboard'));

        $this->get('/dashboard')->assertForbidden();
    }

    public function test_a_viewer_stays_a_viewer_and_no_role_or_membership_is_written(): void
    {
        $companyUuid = 'ffffffff-6666-4666-8666-ffffffffffff';
        [$user, $team] = $this->minesUser('thandi@example.test', 'viewer', $companyUuid);
        $this->fakeRedeem($this->identity(currentTeamUuid: $companyUuid, user: ['role' => 'owner']));
        $rolesBefore = $user->roles()->pluck('roles.id')->all();
        $membersBefore = $team->users()->count();
        $roleRows = Role::count();

        $this->handoff()->assertRedirect(route('dashboard'));

        $user = $user->fresh();
        $this->assertSame($rolesBefore, $user->roles()->pluck('roles.id')->all());
        $this->assertTrue($user->hasRole('viewer'));
        $this->assertFalse($user->hasRole('admin'));
        $this->assertSame($membersBefore, $team->users()->count());
        $this->assertSame($roleRows, Role::count());
    }

    public function test_a_user_with_two_factor_is_sent_to_the_challenge_not_signed_in(): void
    {
        [$user, $team] = $this->minesUser('thandi@example.test', 'admin');
        $user->forceFill([
            'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-aaaa-bbbb', 'recovery-cccc-dddd'])),
            'two_factor_confirmed_at' => now(),
        ])->save();
        $this->fakeRedeem($this->identity());

        $this->handoff()->assertStatus(303)->assertRedirect(route('two-factor.login'));

        $this->assertGuest();
        $this->assertSame($user->id, session('login.id'));
        $this->get('/dashboard')->assertRedirect();            // still not signed in
        $this->assertGuest();
        $this->get(route('two-factor.login'))->assertOk();     // Fortify's own challenge page

        // A wrong code does not sign in; a valid recovery code finishes the sign-in as usual.
        $this->post(route('two-factor.login.store'), ['recovery_code' => 'wrong'])->assertSessionHasErrors();
        $this->assertGuest();
        $this->post(route('two-factor.login.store'), ['recovery_code' => 'recovery-aaaa-bbbb'])->assertRedirect();
        $this->assertAuthenticatedAs($user);
        $this->get('/dashboard')->assertOk();
    }

    public function test_provisioning_off_creates_nothing_and_provisioning_on_creates_a_roleless_account(): void
    {
        $this->fakeRedeem($this->identity('brand.new@example.test'));
        $this->handoff()->assertStatus(403);
        $this->assertNull(User::where('email', 'brand.new@example.test')->first());

        config(['ecosystem.infodot.provision' => true]);
        $this->handoff()->assertStatus(303);

        $created = User::where('email', 'brand.new@example.test')->firstOrFail();
        $this->assertAuthenticatedAs($created);
        $this->assertNotNull($created->email_verified_at);
        $this->assertSame('aaaaaaaa-1111-4111-8111-aaaaaaaaaaaa', $created->infodot_user_uuid);
        $this->assertSame(0, $created->roles()->count());
    }

    public function test_mines_own_email_and_password_sign_in_still_works(): void
    {
        [$user] = $this->minesUser('thandi@example.test');

        $this->get('/login')->assertOk();
        $this->post('/login', ['email' => 'thandi@example.test', 'password' => 'password'])->assertRedirect();

        $this->assertAuthenticatedAs($user);
    }

    public function test_the_migration_adds_nullable_unique_columns_and_is_reversible(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'infodot_user_uuid'));
        $this->assertTrue(Schema::hasColumn('teams', 'infodot_team_uuid'));

        // Existing rows stay valid (nullable) and several unlinked rows can coexist.
        User::factory()->count(2)->create();
        Team::factory()->count(2)->create();

        $uuid = (string) Str::uuid();
        User::factory()->create()->forceFill(['infodot_user_uuid' => $uuid])->save();
        $this->expectException(QueryException::class);
        User::factory()->create()->forceFill(['infodot_user_uuid' => $uuid])->save();
    }

    public function test_the_team_uuid_is_unique_and_the_migration_rolls_back_and_forward(): void
    {
        $uuid = (string) Str::uuid();
        Team::factory()->create()->forceFill(['infodot_team_uuid' => $uuid])->save();

        try {
            Team::factory()->create()->forceFill(['infodot_team_uuid' => $uuid])->save();
            $this->fail('a second team with the same InfoDot company uuid must be refused');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        $migration = require database_path('migrations/2026_11_15_000001_add_infodot_identity_columns.php');
        $migration->down();
        $this->assertFalse(Schema::hasColumn('users', 'infodot_user_uuid'));
        $this->assertFalse(Schema::hasColumn('teams', 'infodot_team_uuid'));
        $migration->up();
        $this->assertTrue(Schema::hasColumn('users', 'infodot_user_uuid'));
        $this->assertTrue(Schema::hasColumn('teams', 'infodot_team_uuid'));
        $migration->up(); // idempotent
    }

    public function test_the_link_command_links_refuses_clashes_and_unlinks(): void
    {
        $team = Team::factory()->create();
        $second = Team::factory()->create();
        $uuid = (string) Str::uuid();

        $this->artisan('mines:link-infodot-team', ['team' => $team->id, 'companyUuid' => 'nope'])->assertFailed();
        $this->artisan('mines:link-infodot-team', ['team' => 99999, 'companyUuid' => $uuid])->assertFailed();

        $this->artisan('mines:link-infodot-team', ['team' => $team->id, 'companyUuid' => strtoupper($uuid)])->assertSuccessful();
        $this->assertSame($uuid, $team->fresh()->infodot_team_uuid);

        // The same company cannot be linked to a second Mines team, nor a second company to this team without --force.
        $this->artisan('mines:link-infodot-team', ['team' => $second->id, 'companyUuid' => $uuid])->assertFailed();
        $other = (string) Str::uuid();
        $this->artisan('mines:link-infodot-team', ['team' => $team->id, 'companyUuid' => $other])->assertFailed();
        $this->assertSame($uuid, $team->fresh()->infodot_team_uuid);
        $this->artisan('mines:link-infodot-team', ['team' => $team->id, 'companyUuid' => $other, '--force' => true])->assertSuccessful();
        $this->assertSame($other, $team->fresh()->infodot_team_uuid);

        $this->artisan('mines:link-infodot-team', ['team' => $team->id, 'companyUuid' => $other, '--unlink' => true])->assertSuccessful();
        $this->assertNull($team->fresh()->infodot_team_uuid);
    }
}

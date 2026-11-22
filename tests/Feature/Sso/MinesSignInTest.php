<?php

namespace Tests\Feature\Sso;

use App\Mail\SsoClaimCodeMail;
use App\Models\Machine;
use App\Models\MineArea;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Providers\MinesSsoServiceProvider;
use App\Services\TeamRoleProvisioner;
use App\Sso\MinesProvisioner;
use App\Support\CentralSignIn;
use Dot\SsoClient\Support\Sso;
use Dot\SsoClient\Tests\Support\FakeIdp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Laravel\Jetstream\Mail\TeamInvitation;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * R-54 rollout M: Dot.Mines behind DOTSSO_ENABLED. Off, nothing changes. On, every sign-in entry goes to InfoDot, and the
 * non-negotiables hold: tenant isolation (central sign-in never picks, creates or changes a mine team), mandatory admin
 * two-factor, no accounts for strangers, linking by stored id then verified email, claim code for anything with authority.
 */
class MinesSignInTest extends TestCase
{
    use RefreshDatabase;

    private const UUID = 'aaaaaaaa-1111-4111-8111-aaaaaaaaaaaa';

    private FakeIdp $idp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->idp = (new FakeIdp)->install();
        config(['dotsso.provisioner' => MinesProvisioner::class, 'dotsso.enabled' => false]);
        Mail::fake();
    }

    private function on(): void
    {
        config(['dotsso.enabled' => true]);
    }

    /** @return array{0: User, 1: Team} */
    private function member(string $email = 'thandi@example.test', string $role = 'viewer', ?string $uuid = null, bool $verified = true): array
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id, 'personal_team' => false]);
        $user = User::factory()->create(['email' => $email, 'email_verified_at' => $verified ? now() : null]);
        $user->forceFill(['current_team_id' => $team->id, 'infodot_user_uuid' => $uuid])->save();
        $team->users()->attach($user, ['role' => $role]);
        TeamRoleProvisioner::assignRole($user, $team, $role);

        return [$user, $team];
    }

    private function claims(string $email = 'thandi@example.test', string $uuid = self::UUID): array
    {
        return ['sub' => $uuid, 'email' => $email, 'name' => 'Thandi Test'];
    }

    private function signInVia(array $claims = [], array $session = [])
    {
        $response = $this->withSession($session)->get('/sso/login')->assertRedirect();
        $authorize = FakeIdp::authorizeParams($response->headers->get('Location'));
        $code = $this->idp->approve($authorize, $claims ?: $this->claims());

        return $this->get('/sso/callback?'.http_build_query(['code' => $code, 'state' => $authorize['state']]));
    }

    // -- flag off: nothing changes ------------------------------------------------------------------------------

    public function test_with_the_flag_off_nothing_changes(): void
    {
        $this->get('/login')->assertOk()->assertSee('Remember me');
        $this->get('/sso/login')->assertNotFound();
        $this->get('/register')->assertRedirect('/login');
        $this->assertSame(route('login'), CentralSignIn::login());
        $this->get('/')->assertOk()->assertSee(route('login'), false)->assertDontSee('/sso/login', false);
    }

    public function test_with_the_flag_off_the_old_receiver_wording_and_behaviour_are_unchanged(): void
    {
        config([
            'ecosystem.infodot.redeem_url' => 'https://infodot.test/api/ecosystem/redeem', 'ecosystem.infodot.secret' => 's',
            'ecosystem.infodot.platform' => 'mines', 'ecosystem.infodot.provision' => false, 'ecosystem.platforms.infodot.url' => 'https://infodot.test',
        ]);
        Http::fake(['https://infodot.test/api/ecosystem/redeem' => Http::response(['platform' => 'mines', 'user' => ['uuid' => self::UUID, 'name' => 'X', 'email' => 'nobody@example.test', 'email_verified' => true]])]);

        $this->withHeaders(['Origin' => 'https://infodot.test'])->post('/auth/ecosystem', ['token' => 't'])
            ->assertForbidden()->assertSee('If you already have a Dot.Mines login, sign in with it.');
        $this->assertSame(0, User::where('email', 'nobody@example.test')->count());
    }

    // -- flag on: interception, entry points, carve-outs --------------------------------------------------------

    public function test_with_the_flag_on_the_local_pages_go_to_infodot_and_there_is_no_create_account(): void
    {
        $this->on();

        $this->get('/login')->assertRedirect(route('dotsso.login'));
        $this->get('/register')->assertRedirect(route('dotsso.login', ['screen' => 'register']));
        $this->get('/forgot-password')->assertRedirect(route('dotsso.login'));
        $this->get('/reset-password/abc?email=a@b.test')->assertRedirect(route('dotsso.login'));
        $this->post('/login', ['email' => 'a@b.test', 'password' => 'x'])->assertRedirect(route('dotsso.login'));
        $this->post('/forgot-password', ['email' => 'a@b.test'])->assertRedirect(route('dotsso.login'));
        $this->post('/reset-password', ['email' => 'a@b.test', 'token' => 't', 'password' => 'x', 'password_confirmation' => 'x'])->assertRedirect(route('dotsso.login'));

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('href="'.route('dotsso.login').'"', $html);
        $this->assertStringNotContainsString('href="'.route('login').'"', $html);
        $this->assertStringNotContainsString('Create account', $html);
    }

    public function test_the_break_glass_switch_brings_the_local_login_back(): void
    {
        $this->on();
        config(['dotsso.allow_local_login' => true]);

        $this->get('/login')->assertOk()->assertSee('Remember me');
        $this->assertSame(route('login'), CentralSignIn::login());
    }

    public function test_a_signed_out_deep_link_is_carried_to_infodot_and_back_to_the_exact_page(): void
    {
        $this->on();
        [$user] = $this->member(uuid: self::UUID);

        // The guest hits a protected page; Laravel records it as the intended URL; the sign-in finishes on it.
        $this->get('/fleet')->assertRedirect();
        $this->assertSame(url('/fleet'), session('url.intended'));

        $this->signInVia(session: ['url.intended' => url('/fleet')])->assertRedirect('/fleet');
        $this->assertAuthenticatedAs($user);
    }

    public function test_next_on_the_sso_login_address_is_validated_and_never_echoed(): void
    {
        $this->on();

        $this->get('/sso/login?next='.urlencode('/fleet?tab=1'))->assertRedirect();
        $this->assertSame('/fleet?tab=1', session('url.intended'));

        foreach (['https://evil.test/fleet', '//evil.test/x', '/login', '/api/machines', '/auth/ecosystem', 'javascript:alert(1)'] as $bad) {
            $this->flushSession();
            $this->get('/sso/login?next='.urlencode($bad))->assertRedirect();
            $this->assertNull(session('url.intended'), $bad);
        }
    }

    public function test_the_post_logout_address_ends_with_a_slash_because_infodot_matches_it_exactly(): void
    {
        $this->assertStringEndsWith('/', Sso::postLogoutUri());
        $this->assertStringEndsWith('/', (string) config('dotsso.post_logout_redirect_uri'));

        // Even a host .env that forgets the slash is normalised by config/dotsso.php.
        putenv('DOTSSO_POST_LOGOUT_URI=https://mines.infodot.co.za');
        $_ENV['DOTSSO_POST_LOGOUT_URI'] = 'https://mines.infodot.co.za';
        try {
            $config = require base_path('config/dotsso.php');
            $this->assertSame('https://mines.infodot.co.za/', $config['post_logout_redirect_uri']);
        } finally {
            putenv('DOTSSO_POST_LOGOUT_URI');
            unset($_ENV['DOTSSO_POST_LOGOUT_URI']);
        }
    }

    public function test_central_sign_in_never_leaves_a_strict_session_cookie_in_place(): void
    {
        config(['session.same_site' => 'strict', 'dotsso.enabled' => false]);
        $provider = new MinesSsoServiceProvider($this->app);

        $provider->enforceLaxSession();
        $this->assertSame('strict', config('session.same_site'), 'flag off: the code default stays strict');

        $this->on();
        $provider->enforceLaxSession();
        $this->assertSame('lax', config('session.same_site'), 'flag on: the cross-site return from InfoDot needs lax');
    }

    public function test_the_guard_comes_from_the_package_default_not_a_hand_set_value(): void
    {
        $this->assertStringNotContainsString("'guard'", (string) file_get_contents(base_path('config/dotsso.php')));
        $this->assertSame('web', config('dotsso.guard'));
    }

    public function test_machine_and_public_doors_are_untouched_with_the_flag_on(): void
    {
        $this->on();
        [$user, $team] = $this->member(role: 'admin');
        $user->forceFill(['two_factor_confirmed_at' => now()])->save();

        // Sanctum bearer API (ability + ensure_team), and the same API without a token.
        Sanctum::actingAs($user->fresh(), ['*']);
        $this->getJson('/api/machines')->assertOk();

        $this->get('/up')->assertOk();
        $this->get('/offline')->assertOk();
        $this->get('/api/openapi.json')->assertOk();
        $this->assertNotSame(302, $this->get('/guardian/health')->status(), 'the guardian bearer door is not redirected to sign-in');
        $this->assertNotSame(302, $this->post('/broadcasting/auth', ['channel_name' => 'private-x', 'socket_id' => '1.2'])->status(), 'broadcast channel auth is never redirected to sign-in');

        $signed = URL::signedRoute('email.unsubscribe', ['user' => User::factory()->create()->id, 'type' => 'alert_notifications']);
        $this->get($signed)->assertSuccessful();

        $this->assertNotContains($this->post('/webhooks/paystack', [])->status(), [302, 419], 'Paystack webhooks are neither redirected to sign-in nor stopped by CSRF');
    }

    public function test_the_api_without_a_token_is_a_401_not_a_redirect_to_sign_in(): void
    {
        $this->on();
        $this->getJson('/api/machines')->assertUnauthorized();
    }

    public function test_a_browser_session_still_reaches_the_cookie_stateful_api(): void
    {
        $this->on();
        [$user] = $this->member(role: 'admin');
        $user->forceFill(['two_factor_confirmed_at' => now()])->save();

        $this->actingAs($user->fresh())->getJson('/api/machines')->assertOk();
    }

    // -- linking -----------------------------------------------------------------------------------------------

    public function test_the_stored_infodot_id_wins_even_when_the_email_changed(): void
    {
        $this->on();
        [$user] = $this->member('old@example.test', uuid: self::UUID);

        $this->signInVia($this->claims('renamed@example.test'))->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
        $this->assertSame(1, User::count() - 1, 'only the team owner made by the helper exists besides the member');
        $this->assertSame(0, User::where('email', 'renamed@example.test')->count());
    }

    public function test_a_verified_email_is_linked_when_both_sides_are_verified(): void
    {
        $this->on();
        [$user] = $this->member();
        $hash = $user->password;

        $this->signInVia()->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
        $this->assertSame(self::UUID, $user->fresh()->infodot_user_uuid);
        $this->assertSame($hash, $user->fresh()->password);
    }

    public function test_an_existing_link_is_never_re_pointed(): void
    {
        $this->on();
        [$user] = $this->member(uuid: 'bbbbbbbb-2222-4222-8222-bbbbbbbbbbbb');

        $this->signInVia()->assertForbidden()->assertSee('different Dot identity');

        $this->assertGuest();
        $this->assertSame('bbbbbbbb-2222-4222-8222-bbbbbbbbbbbb', $user->fresh()->infodot_user_uuid);
    }

    public function test_an_unverified_local_account_goes_to_the_claim_flow_and_keeps_everything(): void
    {
        $this->on();
        [$user, $team] = $this->member(verified: false);
        $hash = $user->password;

        $this->signInVia()->assertRedirect(route('dotsso.claim'));
        $this->assertGuest();
        $this->assertNull($user->fresh()->infodot_user_uuid);

        $code = null;
        Mail::assertSent(SsoClaimCodeMail::class, function (SsoClaimCodeMail $m) use (&$code, $user) {
            $code = $m->code;

            return $m->hasTo($user->email);
        });

        $this->post(route('dotsso.claim.verify'), ['code' => $code])->assertRedirect('/dashboard');

        $fresh = $user->fresh();
        $this->assertAuthenticatedAs($fresh);
        $this->assertSame($hash, $fresh->password);
        $this->assertNotNull($fresh->email_verified_at);
        $this->assertSame(self::UUID, $fresh->infodot_user_uuid);
        $this->assertTrue($fresh->belongsToTeam($team));
    }

    public function test_admin_owner_and_two_factor_accounts_are_never_linked_silently(): void
    {
        $this->on();

        [$admin] = $this->member('admin@example.test', 'admin');
        [$viewer, $team] = $this->member('owner@example.test', 'viewer');
        $team->forceFill(['user_id' => $viewer->id])->save(); // owner of a company team
        [$twofa] = $this->member('tf@example.test');
        $twofa->forceFill(['two_factor_secret' => encrypt('SECRET')])->save();
        [$plain] = $this->member('plain@example.test');

        $p = new MinesProvisioner;
        $this->assertFalse($p->hasVerifiedEmail($admin->fresh()));
        $this->assertFalse($p->hasVerifiedEmail($viewer->fresh()));
        $this->assertFalse($p->hasVerifiedEmail($twofa->fresh()));
        $this->assertTrue($p->hasVerifiedEmail($plain->fresh()));

        $this->signInVia($this->claims('admin@example.test'))->assertRedirect(route('dotsso.claim'));
        $this->assertGuest();
        $this->assertNull($admin->fresh()->infodot_user_uuid);
    }

    public function test_after_the_claim_the_stored_id_is_the_identity_and_no_second_code_is_needed(): void
    {
        $this->on();
        [$admin] = $this->member('admin@example.test', 'admin', uuid: self::UUID);
        $admin->forceFill(['two_factor_confirmed_at' => now()])->save();

        $this->signInVia($this->claims('admin@example.test'))->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($admin);
    }

    // -- no auto-provisioning ----------------------------------------------------------------------------------

    public function test_a_verified_infodot_person_with_no_mines_account_gets_the_no_account_page_and_no_row(): void
    {
        $this->on();
        config(['ecosystem.infodot.provision' => false]);
        $users = User::count();
        $teams = Team::count();

        $this->signInVia($this->claims('stranger@example.test'))
            ->assertForbidden()
            ->assertSee('There is no Dot.Mines account for this email yet')
            ->assertSee('created by invitation')
            ->assertDontSee('password');

        $this->assertGuest();
        $this->assertSame($users, User::count());
        $this->assertSame($teams, Team::count());
        $this->assertSame(0, User::where('email', 'stranger@example.test')->count());
    }

    public function test_only_a_pending_team_invitation_opens_a_bare_account_and_it_returns_to_the_invitation(): void
    {
        $this->on();
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id, 'personal_team' => false]);
        $invitation = $team->teamInvitations()->create(['email' => 'new.hire@example.test', 'role' => 'viewer']);
        $accept = URL::signedRoute('team-invitations.accept', ['invitation' => $invitation]);
        $teams = Team::count();

        $this->get('/sso/login?next='.urlencode($accept))->assertRedirect();
        $path = parse_url($accept, PHP_URL_PATH).'?'.parse_url($accept, PHP_URL_QUERY);
        $this->assertSame($path, session('url.intended'));

        $this->signInVia($this->claims('new.hire@example.test'), ['url.intended' => $path])->assertRedirect($path);

        $user = User::where('email', 'new.hire@example.test')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame(self::UUID, $user->infodot_user_uuid);
        $this->assertNull($user->current_team_id, 'central sign-in never picks a team');
        $this->assertSame($teams, Team::count(), 'and never creates one');
        $this->assertFalse($user->belongsToTeam($team), 'the invitation itself adds the person, not the sign-in');
        $this->assertSame(0, $user->roles()->count());

        // The signed accept link then does what it always did.
        $this->get($path)->assertRedirect();
        $this->assertTrue($user->fresh()->belongsToTeam($team));
    }

    public function test_the_invitation_door_can_be_closed(): void
    {
        $this->on();
        config(['dotsso.accept_invited' => false]);
        $team = Team::factory()->create(['user_id' => User::factory()->create()->id, 'personal_team' => false]);
        $team->teamInvitations()->create(['email' => 'new.hire@example.test', 'role' => 'viewer']);

        $this->signInVia($this->claims('new.hire@example.test'))->assertForbidden();
        $this->assertSame(0, User::where('email', 'new.hire@example.test')->count());
    }

    public function test_the_invitation_email_leads_to_central_sign_in_with_the_signed_link_when_on(): void
    {
        $team = Team::factory()->create(['user_id' => User::factory()->create()->id, 'personal_team' => false]);
        $invitation = $team->teamInvitations()->create(['email' => 'new.hire@example.test', 'role' => 'viewer']);

        $off = (new TeamInvitation($invitation))->render();
        $this->assertStringNotContainsString('/sso/login', $off);

        $this->on();
        $on = html_entity_decode((new TeamInvitation($invitation))->render());
        $this->assertStringContainsString('/sso/login?next=', $on);
        $this->assertStringContainsString(rawurlencode('/team-invitations/'.$invitation->id), $on);
        $this->assertStringNotContainsString(route('register'), $on);
    }

    // -- admin two-factor ---------------------------------------------------------------------------------------

    public function test_a_person_with_two_factor_is_sent_to_the_challenge_not_signed_in(): void
    {
        $this->on();
        [$admin, $team] = $this->member('admin@example.test', 'admin', uuid: self::UUID);
        $admin->forceFill(['two_factor_secret' => encrypt('SECRET'), 'two_factor_confirmed_at' => now()])->save();

        $this->signInVia($this->claims('admin@example.test'), ['url.intended' => url('/fleet')])->assertRedirect(route('two-factor.login'));

        $this->assertGuest();
        $this->assertSame($admin->id, session('login.id'));
        $this->assertSame(url('/fleet'), session('url.intended') ? url(session('url.intended')) : null, 'the deep link survives the challenge');
        $this->assertSame(self::UUID, session('dotsso.session.sub'));
        $this->get(route('two-factor.login'))->assertOk();
    }

    public function test_a_two_factor_admin_goes_through_the_challenge_after_the_claim_code_too(): void
    {
        $this->on();
        [$admin] = $this->member('admin@example.test', 'admin');
        $admin->forceFill(['two_factor_secret' => encrypt('SECRET'), 'two_factor_confirmed_at' => now()])->save();

        $this->signInVia($this->claims('admin@example.test'))->assertRedirect(route('dotsso.claim'));
        $code = null;
        Mail::assertSent(SsoClaimCodeMail::class, function (SsoClaimCodeMail $m) use (&$code) {
            $code = $m->code;

            return true;
        });

        $this->post(route('dotsso.claim.verify'), ['code' => $code])->assertRedirect(route('two-factor.login'));
        $this->assertGuest();
        $this->assertSame($admin->id, session('login.id'));
    }

    public function test_an_admin_without_confirmed_two_factor_still_cannot_use_the_app_after_central_sign_in(): void
    {
        $this->on();
        [$admin] = $this->member('admin@example.test', 'admin', uuid: self::UUID);

        $this->signInVia($this->claims('admin@example.test'))->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($admin);
        $this->get('/dashboard')->assertRedirect(route('profile.show'));
    }

    // -- tenant isolation --------------------------------------------------------------------------------------

    public function test_central_sign_in_never_changes_teams_roles_or_reaches_another_teams_data(): void
    {
        $this->on();
        [$user, $teamA] = $this->member(uuid: self::UUID);
        [$other, $teamB] = $this->member('other@example.test');
        $areaB = MineArea::factory()->create(['team_id' => $teamB->id]);
        $machineB = Machine::factory()->create(['team_id' => $teamB->id, 'mine_area_id' => $areaB->id]);
        $teams = Team::count();
        $roles = Role::count();
        $memberships = $user->teams()->count();

        $this->signInVia($this->claims('thandi@example.test', self::UUID), ['url.intended' => url('/fleet/'.$machineB->id)]);

        $this->assertAuthenticatedAs($user);
        $this->assertSame($teamA->id, $user->fresh()->current_team_id);
        $this->assertSame($teams, Team::count());
        $this->assertSame($roles, Role::count());
        $this->assertSame($memberships, $user->fresh()->teams()->count());
        $this->assertNotSame(200, $this->get('/fleet/'.$machineB->id)->status(), "a second team's machine is not reachable");
        $this->assertNotSame(200, $this->getJson('/api/machines/'.$machineB->id)->status());
    }

    public function test_central_login_does_not_choose_a_team_from_a_claim(): void
    {
        $this->on();
        [$user, $teamA] = $this->member(uuid: self::UUID);
        $teamB = Team::factory()->create(['user_id' => User::factory()->create()->id, 'personal_team' => false]);
        $teamB->forceFill(['infodot_team_uuid' => 'cccccccc-3333-4333-8333-cccccccccccc'])->save();

        $this->signInVia($this->claims('thandi@example.test', self::UUID) + ['current_team_uuid' => 'cccccccc-3333-4333-8333-cccccccccccc']);

        $this->assertSame($teamA->id, $user->fresh()->current_team_id);
    }

    // -- the old receiver, flag on -----------------------------------------------------------------------------

    public function test_the_old_receiver_with_the_flag_on_uses_the_claim_flow_and_no_password_wording(): void
    {
        $this->on();
        config([
            'ecosystem.infodot.redeem_url' => 'https://infodot.test/api/ecosystem/redeem', 'ecosystem.infodot.secret' => 's',
            'ecosystem.infodot.platform' => 'mines', 'ecosystem.infodot.provision' => false, 'ecosystem.platforms.infodot.url' => 'https://infodot.test',
        ]);
        [$admin] = $this->member('admin@example.test', 'admin');
        $identity = fn (string $email) => ['platform' => 'mines', 'user' => ['uuid' => self::UUID, 'name' => 'A', 'email' => $email, 'email_verified' => true]];

        $who = 'admin@example.test';
        Http::fake(['https://infodot.test/api/ecosystem/redeem' => function () use (&$who, $identity) {
            return Http::response($identity($who));
        }]);
        $this->withHeaders(['Origin' => 'https://infodot.test'])->post('/auth/ecosystem', ['token' => 't', 'redirect' => '/fleet'])->assertRedirect(route('dotsso.claim'));
        $this->assertGuest();
        $this->assertNull($admin->fresh()->infodot_user_uuid);

        $who = 'stranger@example.test';
        $this->flushSession();
        $r = $this->withHeaders(['Origin' => 'https://infodot.test'])->post('/auth/ecosystem', ['token' => 't']);
        $r->assertForbidden()->assertSee('created by invitation')->assertSee('Sign in with InfoDot')
            ->assertDontSee('Mines password')->assertDontSee('sign in with it');
        $this->assertSame(0, User::where('email', 'stranger@example.test')->count());
    }

    public function test_the_old_receiver_honours_a_validated_destination_only_with_the_flag_on(): void
    {
        config([
            'ecosystem.infodot.redeem_url' => 'https://infodot.test/api/ecosystem/redeem', 'ecosystem.infodot.secret' => 's',
            'ecosystem.infodot.platform' => 'mines', 'ecosystem.platforms.infodot.url' => 'https://infodot.test',
        ]);
        [$user] = $this->member(uuid: self::UUID);
        Http::fake(['https://infodot.test/api/ecosystem/redeem' => Http::response(['platform' => 'mines', 'user' => ['uuid' => self::UUID, 'name' => 'A', 'email' => 'thandi@example.test', 'email_verified' => true]])]);
        $go = fn (string $to) => $this->withHeaders(['Origin' => 'https://infodot.test'])->post('/auth/ecosystem', ['token' => 't', 'redirect' => $to]);

        $go('/fleet')->assertRedirect(route('dashboard'));   // flag off: ignored, as before
        auth()->logout();

        $this->on();
        $go('/fleet')->assertRedirect('/fleet');
        auth()->logout();
        $go('https://evil.test/')->assertRedirect(route('dashboard'));
    }

    public function test_the_claim_and_error_pages_render_in_the_mines_card(): void
    {
        $this->on();
        [$user] = $this->member(verified: false);
        $this->signInVia()->assertRedirect(route('dotsso.claim'));

        $this->get(route('dotsso.claim'))->assertOk()->assertSee('Confirm it is you')->assertSee('t**')
            ->assertSee('mine teams, roles and sessions stay exactly as they are')->assertSee('Dot.Mines');

        $this->get('/sso/callback?state=nope&code=x')->assertStatus(400)->assertSee('already been used')->assertSee('Start again');
    }
}

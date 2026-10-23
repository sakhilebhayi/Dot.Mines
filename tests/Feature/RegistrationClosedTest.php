<?php

namespace Tests\Feature;

use App\Actions\Jetstream\InviteTeamMember;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Laravel\Fortify\Features;
use Laravel\Jetstream\Mail\TeamInvitation;
use Laravel\Jetstream\TeamInvitation as TeamInvitationModel;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Public sign-up is closed. Accounts are created by invitation or by an administrator, never by the visitor.
 *
 * The suite runs with ALLOW_REGISTRATION unset (the production default), so the in-process tests exercise the closed
 * state directly. The "flag on" behaviour needs the route table rebuilt, so it is proven in a child process.
 */
class RegistrationClosedTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_registration_feature_is_off_by_default(): void
    {
        $this->assertFalse(Features::enabled(Features::registration()));
    }

    public function test_the_register_screen_redirects_to_login(): void
    {
        $this->get('/register')->assertRedirect('/login');
    }

    public function test_posting_to_register_redirects_and_creates_no_user_or_team(): void
    {
        $teams = Team::count();
        $secret = Str::password(24);

        $this->post('/register', [
            'name' => 'Walk In',
            'email' => 'walk-in@example.test',
            'password' => $secret,
            'password_confirmation' => $secret,
            'terms' => 'on',
        ])->assertRedirect('/login');

        $this->assertDatabaseMissing('users', ['email' => 'walk-in@example.test']);
        $this->assertSame($teams, Team::count());
        $this->assertGuest();
    }

    public function test_other_verbs_on_register_create_nothing(): void
    {
        foreach (['put', 'patch', 'delete'] as $verb) {
            $this->{$verb}('/register', ['email' => 'verb@example.test'])->assertRedirect('/login');
        }

        $this->assertDatabaseMissing('users', ['email' => 'verb@example.test']);
    }

    public function test_the_named_register_route_still_resolves(): void
    {
        $this->assertTrue(Route::has('register'));
        $this->assertStringEndsWith('/register', route('register'));
    }

    public function test_the_welcome_page_has_no_sign_up_link(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('/register', (string) $html);
        $this->assertStringNotContainsString('Start free trial', (string) $html);
        $this->assertStringContainsString(route('login'), (string) $html);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function marketingPages(): iterable
    {
        foreach (['/features', '/capabilities', '/pricing', '/core-features/fleet-tracking', '/core-features/maintenance', '/core-features/fuel'] as $path) {
            yield $path => [$path];
        }
    }

    #[DataProvider('marketingPages')]
    public function test_public_marketing_pages_have_no_sign_up_link(string $path): void
    {
        $html = (string) $this->get($path)->assertOk()->getContent();

        $this->assertStringNotContainsString('/register', $html);
        $this->assertStringNotContainsString('Start free trial', $html);
    }

    public function test_login_screen_has_no_sign_up_link_and_login_still_works(): void
    {
        $this->get('/login')->assertOk()->assertDontSee('/register');

        $secret = Str::password(24);
        $user = User::factory()->create(['password' => bcrypt($secret)]);

        $this->post('/login', ['email' => $user->email, 'password' => $secret])
            ->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_the_invitation_email_does_not_offer_account_creation(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id, 'personal_team' => false]);
        $invitation = $team->teamInvitations()->create(['email' => 'invitee@example.test', 'role' => 'viewer']);

        $html = (new TeamInvitation($invitation))->render();

        $this->assertStringNotContainsString('/register', $html);
        $this->assertStringNotContainsString('Create Account', $html);
        $this->assertStringContainsString('Accept Invitation', $html);
    }

    public function test_an_invitation_can_still_be_sent_and_accepted_by_an_existing_user(): void
    {
        Mail::fake();

        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id, 'personal_team' => false]);
        $team->users()->attach($owner, ['role' => 'admin']);
        $invitee = User::factory()->create(['email' => 'invitee@example.test']);

        app(InviteTeamMember::class)->invite($owner, $team, $invitee->email, 'viewer');

        $invitation = TeamInvitationModel::where('email', $invitee->email)->firstOrFail();
        $acceptUrl = URL::signedRoute('team-invitations.accept', ['invitation' => $invitation]);

        $this->actingAs($invitee)->get($acceptUrl)->assertRedirect();

        $this->assertTrue($team->fresh()->hasUser($invitee->fresh()));
        $this->assertDatabaseMissing('team_invitations', ['id' => $invitation->id]);
    }

    public function test_seeded_demo_accounts_can_still_sign_in(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(DemoSeeder::class);

        // The seeder's own password is not repeated here; set a throwaway one on the seeded account instead.
        $secret = Str::password(24);
        User::where('email', 'manager@demo.mines.local')->firstOrFail()->forceFill(['password' => bcrypt($secret)])->save();

        $this->post('/login', ['email' => 'manager@demo.mines.local', 'password' => $secret])->assertRedirect();
        $this->assertAuthenticated();
    }

    public function test_the_env_example_ships_with_registration_closed(): void
    {
        $this->assertStringContainsString("\nALLOW_REGISTRATION=false\n", (string) file_get_contents(base_path('.env.example')));
    }

    /**
     * Boots a fresh application with the given ALLOW_REGISTRATION value (null = unset) and returns what it reports.
     *
     * @return array<string, mixed>
     */
    private function probeWith(?string $allow): array
    {
        $code = <<<'PHP'
            require "vendor/autoload.php";
            $app = require "bootstrap/app.php";
            $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
            $get = $kernel->handle(Illuminate\Http\Request::create("/register", "GET"));
            $route = Illuminate\Support\Facades\Route::getRoutes()->getByName("register");
            echo json_encode([
                "feature" => Laravel\Fortify\Features::enabled(Laravel\Fortify\Features::registration()),
                "has_route" => Illuminate\Support\Facades\Route::has("register"),
                "get_status" => $get->getStatusCode(),
                "get_location" => $get->headers->get("Location"),
                "action" => $route ? $route->getActionName() : null,
            ]);
            PHP;

        $env = [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'MAIL_MAILER' => 'array',
            'BROADCAST_DRIVER' => 'null',
            'BROADCAST_CONNECTION' => 'null',
        ];
        if ($allow === null) {
            // Child inherits this process's environment; make sure the variable is genuinely absent.
            $env['ALLOW_REGISTRATION'] = false;
        } else {
            $env['ALLOW_REGISTRATION'] = $allow;
        }

        $process = new Process([PHP_BINARY, '-d', 'display_errors=stderr', '-r', $code], base_path(), $env);
        $process->mustRun();

        /** @var array<string, mixed> $result */
        $result = json_decode(trim($process->getOutput()), true);

        return $result;
    }

    public function test_a_fresh_boot_with_the_variable_unset_has_registration_closed(): void
    {
        $result = $this->probeWith(null);

        $this->assertFalse($result['feature']);
        $this->assertTrue($result['has_route']);
        $this->assertSame(302, $result['get_status']);
        $this->assertStringEndsWith('/login', (string) $result['get_location']);
    }

    public function test_a_fresh_boot_with_the_variable_false_has_registration_closed(): void
    {
        $result = $this->probeWith('false');

        $this->assertFalse($result['feature']);
        $this->assertSame(302, $result['get_status']);
    }

    public function test_registration_is_only_available_when_explicitly_enabled(): void
    {
        $result = $this->probeWith('true');

        $this->assertTrue($result['feature']);
        $this->assertTrue($result['has_route']);
        $this->assertNotSame(302, $result['get_status']);
        $this->assertStringContainsString('RegisteredUserController', (string) $result['action']);
    }
}

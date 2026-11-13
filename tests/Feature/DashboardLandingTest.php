<?php

namespace Tests\Feature;

use App\Livewire\DashboardLanding;
use App\Models\Alert;
use App\Models\Geofence;
use App\Models\Machine;
use App\Models\MineArea;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Livewire\Attributes\Lazy;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use Tests\TestCase;

/**
 * The signed-in landing at the top of /dashboard: the original logo, a
 * welcome from real user and team data, the state in words, the readings as
 * real counts, exactly one primary action chosen from that state and the
 * person's permissions, and the way back to InfoDot.
 */
class DashboardLandingTest extends TestCase
{
    use RefreshDatabase;

    /** SHA-256 of the logo artwork as committed; the landing must never ship an altered file. */
    private const LOGO_LIGHT_SHA256 = 'f848a3875cf77bd1c166127dd9b97dfcecbb112e599d28ce150ceaea37803ebf';

    private const LOGO_SHA256 = '8d33780ee3573da252da5766070efcbffb99134749bab8ae8aab61fccc3938ff';

    /**
     * @return array{0: User, 1: Team}
     */
    private function memberWithRole(string $role, string $name = 'Thandi Mokoena'): array
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id, 'name' => 'Kopano Colliery']);
        $member = User::factory()->create(['name' => $name, 'current_team_id' => $team->id]);
        $team->users()->attach($member->id);
        TeamRoleProvisioner::assignRole($member, $team, $role);

        if ($role === 'admin') {
            // Admin accounts are held at the profile page until 2FA is
            // confirmed (admin.2fa); the landing is what they reach after.
            $member->forceFill(['two_factor_confirmed_at' => now()])->save();
        }

        return [$member, $team];
    }

    private function seedWorkingMine(Team $team): void
    {
        MineArea::factory()->create(['team_id' => $team->id]);
        Machine::factory()->count(3)->create(['team_id' => $team->id, 'status' => 'active']);
        Machine::factory()->count(2)->create(['team_id' => $team->id, 'status' => 'idle']);
        Geofence::factory()->count(4)->create(['team_id' => $team->id]);

        foreach (range(1, 2) as $n) {
            Alert::create([
                'team_id' => $team->id,
                'type' => 'sensor',
                'title' => 'Coolant temperature high '.$n,
                'description' => 'Coolant temperature exceeded threshold.',
                'priority' => 'high',
                'status' => 'active',
                'triggered_at' => now(),
            ]);
        }
    }

    /**
     * The href of the single element carrying data-primary-action, and its label.
     *
     * @return array{href: string, label: string}
     */
    private function primaryAction(TestResponse $response): array
    {
        $html = (string) $response->getContent();

        $this->assertSame(1, substr_count($html, 'data-primary-action'), 'exactly one primary action on the page');
        $this->assertSame(1, preg_match('/<a href="([^"]+)" data-primary-action[^>]*>\s*([^<]+?)\s*<\/a>/', $html, $m));

        return ['href' => html_entity_decode($m[1]), 'label' => html_entity_decode($m[2])];
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function rolesOnAWorkingMine(): array
    {
        return [
            'admin reviews alerts' => ['admin', 'alerts', 'Review 2 active alerts'],
            'fleet manager reviews alerts' => ['fleet_manager', 'alerts', 'Review 2 active alerts'],
            'operator reviews alerts' => ['operator', 'alerts', 'Review 2 active alerts'],
            'viewer opens the live map' => ['viewer', 'map', 'Open the live map'],
        ];
    }

    #[DataProvider('rolesOnAWorkingMine')]
    public function test_each_role_gets_one_primary_action_that_answers_200(string $role, string $route, string $label): void
    {
        [$member, $team] = $this->memberWithRole($role);
        $this->seedWorkingMine($team);

        $response = $this->actingAs($member)->get('/dashboard');
        $response->assertOk();

        $action = $this->primaryAction($response);
        $this->assertSame(route($route), $action['href']);
        $this->assertSame($label, $action['label']);

        $this->actingAs($member)->get($action['href'])->assertOk();
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function rolesOnAnEmptyMine(): array
    {
        return [
            'admin starts with a mine area' => ['admin', 'mine-areas', 'Create your first mine area'],
            'fleet manager starts with a mine area' => ['fleet_manager', 'mine-areas', 'Create your first mine area'],
            'operator cannot set up' => ['operator', 'map', 'Open the live map'],
            'viewer cannot set up' => ['viewer', 'map', 'Open the live map'],
        ];
    }

    #[DataProvider('rolesOnAnEmptyMine')]
    public function test_the_empty_state_renders_with_an_honest_next_step_per_role(string $role, string $route, string $label): void
    {
        [$member] = $this->memberWithRole($role);

        $response = $this->actingAs($member)->get('/dashboard');
        $response->assertOk();
        $response->assertSee('data-landing-setup', false);
        $response->assertSee('there is no mine area and no machine yet.');

        $action = $this->primaryAction($response);
        $this->assertSame(route($route), $action['href']);
        $this->assertSame($label, $action['label']);
        $this->actingAs($member)->get($action['href'])->assertOk();

        if (in_array($role, ['operator', 'viewer'], true)) {
            $response->assertSee('An administrator or fleet manager still has to do what is left.');
        }

        foreach (['machines', 'marked-active', 'active-alerts', 'geofences', 'mine-areas'] as $reading) {
            $this->assertSame('0', $this->reading($response, $reading), $reading);
        }
    }

    public function test_setup_moves_to_the_first_machine_once_a_mine_area_exists(): void
    {
        [$manager, $team] = $this->memberWithRole('fleet_manager');
        MineArea::factory()->create(['team_id' => $team->id]);

        $response = $this->actingAs($manager)->get('/dashboard');

        $action = $this->primaryAction($response);
        $this->assertSame(route('fleet'), $action['href']);
        $this->assertSame('Add your first machine', $action['label']);
        $this->actingAs($manager)->get($action['href'])->assertOk();
    }

    public function test_a_quiet_fleet_sends_everyone_to_the_live_map(): void
    {
        [$operator, $team] = $this->memberWithRole('operator');
        MineArea::factory()->create(['team_id' => $team->id]);
        Machine::factory()->count(2)->create(['team_id' => $team->id, 'status' => 'active']);

        $response = $this->actingAs($operator)->get('/dashboard');

        $this->assertSame(route('map'), $this->primaryAction($response)['href']);
        $response->assertSee('the whole fleet is marked active and no alerts are open.');
        $response->assertDontSee('data-landing-setup', false);
    }

    public function test_the_welcome_uses_the_real_first_name_and_team_name(): void
    {
        [$member] = $this->memberWithRole('operator', 'Thandi Mokoena');

        $response = $this->actingAs($member)->get('/dashboard');

        $response->assertSee(', Thandi.');
        $response->assertDontSee('Thandi Mokoena.');
        $response->assertSee('Kopano Colliery');
    }

    private function reading(TestResponse $response, string $key): ?string
    {
        $found = preg_match('/data-reading="'.preg_quote($key, '/').'">([^<]*)</', (string) $response->getContent(), $m);

        return $found === 1 ? trim($m[1]) : null;
    }

    public function test_every_figure_on_the_landing_is_a_count_of_seeded_records(): void
    {
        [$member, $team] = $this->memberWithRole('operator');
        $this->seedWorkingMine($team);

        // Another team's records must never leak into the readings.
        $other = Team::factory()->create(['user_id' => User::factory()->create()->id]);
        Machine::factory()->count(7)->create(['team_id' => $other->id, 'status' => 'active']);

        $response = $this->actingAs($member)->get('/dashboard');

        $this->assertSame('5', $this->reading($response, 'machines'));
        $this->assertSame('3', $this->reading($response, 'marked-active'));
        $this->assertSame('2', $this->reading($response, 'active-alerts'));
        $this->assertSame('4', $this->reading($response, 'geofences'));
        $this->assertSame('1', $this->reading($response, 'mine-areas'));
        $response->assertSee('part of the fleet is marked active and alerts are open.');

        // The landing itself (headline, welcome, action, setup, readings)
        // carries no digits beyond those five counts, the primary action's
        // alert count and the date. Strip those and nothing numeric remains.
        $html = (string) $response->getContent();
        $start = strpos($html, '<section aria-labelledby="landing-headline"');
        $end = strpos($html, '</section>', (int) $start);
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);

        $landing = substr($html, $start, $end - $start);
        $landing = (string) preg_replace('/<span[^>]*x-data=.*?<\/time>\s*<\/span>|<span class="inline-flex[^>]*>No data yet<\/span>/s', '', $landing);
        $text = trim((string) preg_replace('/\s+/', ' ', strip_tags($landing)));
        $text = str_replace(now()->format('D j M Y'), '', $text);
        $text = str_replace('Review 2 active alerts', '', $text);

        preg_match_all('/\d+/', $text, $numbers);
        $this->assertSame(['5', '3', '2', '4', '1'], $numbers[0]);
    }

    public function test_the_landing_shows_the_original_logo_file_unaltered(): void
    {
        [$member] = $this->memberWithRole('viewer');

        $response = $this->actingAs($member)->get('/dashboard');

        $response->assertSee('data-landing-logo', false);
        $response->assertSee(asset('images/logo-light.png'), false);

        $this->assertSame(self::LOGO_LIGHT_SHA256, hash_file('sha256', public_path('images/logo-light.png')));
        $this->assertSame(self::LOGO_SHA256, hash_file('sha256', public_path('images/logo.png')));
    }

    public function test_the_way_back_to_infodot_is_two_plain_links(): void
    {
        [$member] = $this->memberWithRole('viewer');

        $response = $this->actingAs($member)->get('/dashboard');

        $html = (string) $response->getContent();
        $this->assertMatchesRegularExpression('#<a href="https://infodot\.co\.za/home"[^>]*>InfoDot</a>#', $html);
        $this->assertMatchesRegularExpression('#<a href="https://infodot\.co\.za/platforms"[^>]*>Platforms</a>#', $html);
    }

    public function test_the_landing_is_in_the_first_response_not_behind_the_lazy_load(): void
    {
        $this->assertEmpty(
            (new ReflectionClass(DashboardLanding::class))->getAttributes(Lazy::class),
            'The landing must paint with the page; only the board beneath it is lazy.',
        );
    }

    public function test_what_the_old_tiles_linked_to_is_still_reachable_from_the_landing(): void
    {
        [$member] = $this->memberWithRole('viewer');

        $response = $this->actingAs($member)->get('/dashboard');

        foreach (['fleet', 'map', 'alerts', 'geofences', 'mine-areas'] as $route) {
            $response->assertSee('href="'.route($route).'"', false);
        }
    }

    public function test_the_shared_platform_registry_lists_only_current_platforms(): void
    {
        $current = [
            'agents', 'analytics', 'auction', 'central', 'charts', 'design', 'docs', 'dopemine', 'ehail', 'emall',
            'engage', 'files', 'finance', 'forms', 'hr', 'memory', 'mines', 'notify', 'press', 'projects', 'pulse',
            'sheet', 'tasks', 'tutor', 'infodot',
        ];

        $this->assertSame([], array_values(array_diff(array_keys((array) config('ecosystem.platforms')), $current)));
    }
}

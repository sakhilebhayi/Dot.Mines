<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use App\Services\TeamRoleProvisioner;
use App\Support\PlainSwitcherModel;
use Dot\Ui\PlatformSwitcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * R-51 rollout of the shared dot-ui package into Dot.Mines: the in-page filters wear the shared look with their
 * logic untouched, and the header carries the honest "plain / own sign-in" Dot Apps switcher.
 */
class DotUiRolloutTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $teamName = 'Kopano Colliery'): User
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id, 'name' => $teamName]);
        $member = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($member->id);
        TeamRoleProvisioner::assignRole($member, $team, 'viewer');

        return $member;
    }

    public function test_the_header_carries_the_plain_switcher_and_asks_infodot_nothing(): void
    {
        Http::fake();
        $html = (string) $this->actingAs($this->member())->get('/dashboard')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'data-dsw data-state="degraded" data-platform="mines"'));
        $this->assertStringContainsString('Could not check your platforms from here', $html);
        $this->assertStringContainsString('has its own sign-in, so it does not know which platforms your company has', $html);
        $this->assertStringContainsString('You are here', $html);
        Http::assertNothingSent();
    }

    public function test_it_never_claims_what_the_company_holds_and_never_links_a_platform_directly(): void
    {
        $html = (string) $this->actingAs($this->member())->get('/dashboard')->getContent();
        $switcher = substr($html, (int) strpos($html, 'class="dsw dsw--header"'));
        $switcher = substr($switcher, 0, (int) strpos($switcher, 'livewire:navigated') ?: 12000);

        $this->assertStringNotContainsString('Not switched on', $switcher);
        $this->assertStringNotContainsString('>Active<', $switcher);
        $this->assertStringNotContainsString('Each opens already signed in', $switcher);
        $this->assertStringNotContainsString('company=', $switcher);

        preg_match_all('#class="dsw-bay" href="([^"]+)"#', $switcher, $hrefs);
        $this->assertNotEmpty($hrefs[1]);
        foreach ($hrefs[1] as $href) {
            $this->assertMatchesRegularExpression('#^https://infodot\.co\.za/(go/[a-z]+|home)$#', $href);
        }
    }

    public function test_the_platform_list_comes_from_the_central_registry_not_the_local_copy(): void
    {
        config(['ecosystem.platforms' => ['bogus' => ['name' => 'Dot.Bogus', 'url' => 'https://bogus.example', 'active' => true]]]);

        $model = PlainSwitcherModel::build();
        $keys = [];
        foreach ($model['groups'] as $group) {
            foreach ($group['tiles'] as $tile) {
                $keys[] = $tile['key'];
            }
        }

        $this->assertSame('degraded', $model['state']);
        $this->assertSame('mines', $model['current']);
        $this->assertNull($model['company']);
        $this->assertEqualsCanonicalizing([...PlatformSwitcher::core(), 'mines'], $keys);
        $this->assertNotContains('bogus', $keys);
        foreach (['plug', 'billing', 'farms'] as $discontinued) {
            $this->assertNotContains($discontinued, $keys);
        }
    }

    public function test_every_team_sees_the_same_company_free_switcher(): void
    {
        $a = $this->actingAs($this->member('Alpha Quarry'))->get('/dashboard')->getContent();
        $b = $this->actingAs($this->member('Bravo Pit'))->get('/dashboard')->getContent();

        foreach ([$a, $b] as $html) {
            $start = (int) strpos($html, 'class="dsw dsw--header"');
            $end = (int) strpos($html, 'data-dsw-panel', $start);
            $this->assertStringNotContainsString('Alpha Quarry', substr($html, $start, 14000));
            $this->assertStringNotContainsString('Bravo Pit', substr($html, $start, 14000));
            $this->assertGreaterThan($start, $end);
        }
    }

    public function test_no_secret_or_token_reaches_the_switcher_markup(): void
    {
        config(['ecosystem.infodot.secret' => 'sekret-should-never-print']);
        $html = (string) $this->actingAs($this->member())->get('/dashboard')->getContent();

        $this->assertStringNotContainsString('sekret-should-never-print', $html);
        $this->assertDoesNotMatchRegularExpression('#/go/[a-z]+\?[^"]*(token|secret)#i', $html);
    }

    public function test_shared_assets_are_served_from_the_apps_own_origin_with_no_inline_style_or_script_from_the_package(): void
    {
        $html = (string) $this->actingAs($this->member())->get('/dashboard')->getContent();

        foreach (['dot-fonts', 'dot-tokens', 'dot-search', 'dot-switcher-fonts', 'dot-switcher'] as $css) {
            $this->assertStringContainsString('/dot-ui/css/'.$css.'.css', $html);
            $this->assertFileExists(public_path('dot-ui/css/'.$css.'.css'));
        }
        $this->assertStringContainsString('/dot-ui-host.css', $html);
        $this->assertFileExists(public_path('dot-ui-host.css'));
        $this->assertFileExists(public_path('dot-ui/js/dot-switcher.js'));
        $this->assertMatchesRegularExpression('#<script src="[^"]*/dot-ui/js/dot-switcher\.js" nonce="[0-9a-f]{24}" defer>#', $html);
        $this->assertDoesNotMatchRegularExpression('#https?://[^"\']*dot-ui#', str_replace(url('/'), '/', $html));

        $start = (int) strpos($html, 'class="dsw dsw--header"');
        $panel = substr($html, $start, 16000);
        $this->assertStringNotContainsString(' style=', substr($panel, 0, (int) strpos($panel, '<livewire') ?: 9000));
    }

    public function test_the_logos_are_the_packaged_originals(): void
    {
        $manifest = json_decode((string) file_get_contents(public_path('dot-ui/img/platforms/manifest.json')), true);
        $this->assertNotEmpty($manifest);
        $this->assertFileExists(public_path('dot-ui/img/platforms/mines.png'));
    }

    public function test_every_in_page_search_is_the_shared_filter_with_its_livewire_binding_intact(): void
    {
        $expected = [
            'alerts' => ['livewire-alerts-search', 'search'],
            'fleet' => ['livewire-fleet-search', 'search'],
            'geofence-manager' => ['livewire-geofence-manager-search', 'search'],
            'machine-assignment-manager/assign' => ['livewire-machine-assignment-manager-assign-searchterm', 'searchTerm'],
            'machine-assignment-manager/manage' => ['livewire-machine-assignment-manager-manage-searchterm', 'searchTerm'],
            'mine-area-manager' => ['livewire-mine-area-manager-search', 'search'],
            'operations-feed' => ['livewire-operations-feed-search', 'search'],
            'operator-manager' => ['livewire-operator-manager-search', 'search'],
            'reports' => ['livewire-reports-search', 'search'],
        ];

        foreach ($expected as $view => [$id, $model]) {
            $source = (string) file_get_contents(resource_path('views/livewire/'.$view.'.blade.php'));
            $this->assertMatchesRegularExpression('#<x-dot\.filter-search id="'.$id.'" wire:model\.live(\.debounce\.\d+ms)?="'.$model.'"#', $source, $view);
            // No hand-rolled search input is left behind.
            $this->assertDoesNotMatchRegularExpression('#<input[^>]*(type="search"|placeholder="Search)#i', $source, $view);
        }
        $this->assertStringContainsString('wire:model.live.debounce.300ms="operatorSearch"', (string) file_get_contents(resource_path('views/livewire/fleet.blade.php')));
    }

    public function test_a_filter_page_renders_the_shared_filter_unchanged_in_behaviour(): void
    {
        $html = (string) $this->actingAs($this->member())->get('/fleet')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<input[^>]*id="livewire-fleet-search"[^>]*class="dsr-filter"|<input[^>]*class="dsr-filter"[^>]*id="livewire-fleet-search"#s', $html);
        $this->assertStringContainsString('wire:model.live="search"', $html);
    }

    public function test_there_is_no_header_search_so_none_was_added(): void
    {
        $html = (string) $this->actingAs($this->member())->get('/dashboard')->getContent();

        $this->assertStringNotContainsString('class="dsr ', $html);
        $this->assertStringNotContainsString('dsr-pill', $html);
    }
}

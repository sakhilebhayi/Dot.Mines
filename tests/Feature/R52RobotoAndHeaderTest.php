<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use App\Services\TeamRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * R-52 rollout: Roboto is the one face (self-hosted), the shared dot-ui header carries the brand, the live state, the search,
 * the switcher, the bell and the account, and the sidebar is the phone menu (dot-ui adopt mode).
 */
class R52RobotoAndHeaderTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $role = 'fleet_manager'): User
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id, 'name' => 'Kopano Colliery']);
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user->id);
        TeamRoleProvisioner::assignRole($user, $team, $role);

        return $user;
    }

    /** @return list<string> one page of every layout the signed-in app renders (the two layouts and a plain page) */
    private static function appPages(): array
    {
        return ['/dashboard', '/fleet', '/map', '/alerts', '/operators', '/maintenance', '/mine-areas', '/geofences', '/reports', '/production', '/settings', '/search?q=zulu', '/documentation'];
    }

    public function test_every_signed_in_page_type_carries_the_header_and_the_search(): void
    {
        $this->actingAs($this->member());

        foreach (self::appPages() as $path) {
            $html = (string) $this->get($path)->assertOk()->getContent();
            $this->assertSame(1, substr_count($html, '<header'), "$path: exactly one header");
            $this->assertStringContainsString('class="dh dh--nonav dh--adopt"', $html, "$path: the shared header, no primary nav, adopt mode");
            $this->assertStringContainsString('class="dsr-input"', $html, "$path: the search pill");
            $this->assertStringContainsString('name="q"', $html, "$path: the search input");
            $this->assertStringContainsString('data-dsw', $html, "$path: the switcher");
            $this->assertStringContainsString('dh-account', $html, "$path: the account menu");
            $this->assertStringContainsString('id="mines-sidebar"', $html, "$path: the sidebar");
        }
    }

    public function test_the_header_has_no_inline_style_and_every_inline_script_carries_the_nonce(): void
    {
        $this->actingAs($this->member());
        $html = (string) $this->get('/dashboard')->assertOk()->getContent();

        $header = substr($html, (int) strpos($html, '<header'), (int) strpos($html, '</header>') - (int) strpos($html, '<header'));
        $this->assertStringNotContainsString('style=', $header);

        preg_match_all('#<script(?![^>]*\bsrc=)([^>]*)>#', $html, $inline);
        foreach ($inline[1] as $attrs) {
            $this->assertStringContainsString('nonce=', $attrs, 'an inline script without a nonce: <script'.$attrs.'>');
        }
    }

    public function test_there_is_one_skip_link_not_two(): void
    {
        $this->actingAs($this->member());

        foreach (['/dashboard', '/alerts'] as $path) {
            $html = (string) $this->get($path)->assertOk()->getContent();
            $this->assertSame(1, substr_count($html, 'Skip to'), "$path: the layout's skip link only; the header prints none");
            $this->assertStringContainsString('href="#main-content"', $html);
        }
    }

    public function test_the_phone_menu_is_the_sidebar_in_adopt_mode(): void
    {
        $this->actingAs($this->member());
        $html = (string) $this->get('/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('data-dh-menu-button', $html);
        $this->assertStringContainsString('aria-controls="mines-sidebar"', $html);
        $this->assertStringContainsString('<button type="button" data-dh-close', $html, 'a close button inside the sidebar');
        $this->assertStringNotContainsString('id="dh-drawer"', $html, 'no second dot-ui drawer');
        $this->assertStringNotContainsString('window.mobileNav', $html, 'the old hand-written toggle is gone');
        $this->assertStringNotContainsString('id="mobile-sidebar"', $html);
    }

    public function test_the_sidebar_groups_the_fourteen_areas_and_keeps_every_route(): void
    {
        $this->actingAs($this->member());
        $html = (string) $this->get('/dashboard')->assertOk()->getContent();

        foreach (['Operate', 'Sites', 'Crew', 'Resources', 'Insight'] as $group) {
            $this->assertStringContainsString('role="group" aria-label="'.$group.'"', $html);
        }
        $nav = substr($html, (int) strpos($html, 'class="mines-nav"'));
        $nav = substr($nav, 0, (int) strpos($nav, '</nav>'));
        $this->assertSame(14, substr_count($nav, 'class="mines-nav__link"'));
        foreach (['dashboard', 'fleet', 'map', 'geofences', 'mine-areas', 'reports', 'production', 'alerts', 'feed', 'operators', 'operator-fatigue', 'fuel', 'maintenance', 'ai-optimization'] as $path) {
            $this->assertStringContainsString('href="'.url('/'.$path).'"', $nav, "sidebar link to /$path");
        }
        $this->assertStringContainsString('aria-current="page"', $nav);
    }

    public function test_the_account_menu_groups_its_links_and_the_connectivity_pill_lives_in_the_bar(): void
    {
        $this->actingAs($this->member());
        $html = (string) $this->get('/dashboard')->assertOk()->getContent();

        foreach (['My profile', 'Team', 'Billing', 'Settings', 'Integrations', 'API tokens', 'Documentation', 'Log out'] as $label) {
            $this->assertStringContainsString($label, $html);
        }
        $header = substr($html, (int) strpos($html, '<header'), (int) strpos($html, '</header>') - (int) strpos($html, '<header'));
        $this->assertStringContainsString('data-connectivity-pill', $header);
        $this->assertSame(1, substr_count($html, 'data-connectivity-pill'), 'one pill, in the bar, not floating over the page');
        $this->assertStringNotContainsString('fixed bottom-4 left-4', $html);
    }

    public function test_roboto_is_declared_and_the_files_are_served_from_this_origin(): void
    {
        $this->actingAs($this->member());
        $html = (string) $this->get('/dashboard')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<html[^>]*class="[^"]*dot-roboto#', $html);
        $this->assertMatchesRegularExpression('#<link rel="preload" href="[^"]*/build/assets/roboto-latin-wght-normal-[^"]+\.woff2" as="font" type="font/woff2" crossorigin>#', $html);

        $manifest = json_decode((string) file_get_contents(public_path('build/manifest.json')), true);
        $css = File::get(public_path('build/'.$manifest['resources/css/app.css']['file']));
        $this->assertStringContainsString('font-family:Roboto', str_replace([' ', "'", '"'], '', $css));
        $this->assertStringContainsString('font-display:swap', str_replace(' ', '', $css));
        $this->assertStringContainsString('--dot-font-sans', $css);
        foreach (['latin', 'latin-ext'] as $subset) {
            $this->assertFileExists(public_path("dot-ui/fonts/roboto/roboto-{$subset}-wght-normal.woff2"), 'the hash-pinned source');
            $this->assertNotEmpty(glob(public_path("build/assets/roboto-{$subset}-wght-normal-*.woff2")), "$subset is emitted into the build");
        }
        $this->assertDoesNotMatchRegularExpression('#url\(\s*[\'"]?https?://#', $css, 'no stylesheet url() leaves this origin');
        $this->assertFileDoesNotExist(public_path('dot-ui/css/dot-fonts.css'), 'the legacy Archivo files are not shipped by dot-ui 0.6.0');
    }

    public function test_no_view_links_a_font_host_or_names_a_removed_face(): void
    {
        foreach (File::allFiles(resource_path('views')) as $file) {
            $source = $file->getContents();
            $this->assertStringNotContainsString('fonts.googleapis.com', $source, $file->getRelativePathname());
            $this->assertStringNotContainsString('fonts.gstatic.com', $source, $file->getRelativePathname());
            $this->assertStringNotContainsString('fonts.bunny.net', $source, $file->getRelativePathname());
            $this->assertStringNotContainsString('IBM Plex', $source, $file->getRelativePathname());
        }
        $css = File::get(resource_path('css/app.css')).File::get(public_path('dot-ui-host.css'));
        $this->assertStringNotContainsString('IBM Plex', $css);
        $this->assertStringNotContainsString('Archivo', $css);
        $tailwind = File::get(base_path('tailwind.config.js'));
        $this->assertStringNotContainsString('IBM Plex', $tailwind);
    }

    public function test_public_and_error_pages_are_roboto_too_and_link_no_font_host(): void
    {
        foreach (['/', '/features', '/pricing', '/login', '/offline'] as $path) {
            $html = (string) $this->get($path)->assertOk()->getContent();
            $this->assertStringContainsString('dot-roboto', $html, $path);
            $this->assertMatchesRegularExpression('#/build/assets/app-[^"]+\.css#', $html, "$path: Roboto comes with the bundled stylesheet");
            $this->assertStringNotContainsString('fonts.googleapis', $html, $path);
            $this->assertStringNotContainsString('IBM Plex', $html, $path);
        }
        $error = (string) $this->get('/no-such-page')->assertNotFound()->getContent();
        $this->assertStringContainsString('dot-roboto', $error);
        $this->assertStringNotContainsString('fonts.googleapis', $error);
        $this->assertStringContainsString('/dot-ui/css/dot-roboto.min.css', $error, 'the error page has no Vite bundle, so it links the package file itself');
    }

    public function test_the_layout_shell_is_written_once(): void
    {
        $a = File::get(resource_path('views/layouts/app.blade.php'));
        $b = File::get(resource_path('views/components/layouts/app.blade.php'));

        foreach ([$a, $b] as $layout) {
            $this->assertStringContainsString('<x-shell', $layout);
            $this->assertStringContainsString("@livewire('navbar')", $layout);
            $this->assertStringNotContainsString("@livewire('sidebar')", $layout, 'the sidebar is mounted by the shell component only');
            $this->assertStringNotContainsString('mobile-nav-changed', $layout);
        }
        $this->assertStringContainsString("@livewire('sidebar')", File::get(resource_path('views/components/shell.blade.php')));
    }

    public function test_the_csp_names_no_font_host_for_the_pages_it_serves(): void
    {
        $response = $this->actingAs($this->member())->get('/dashboard');
        $csp = (string) ($response->headers->get('Content-Security-Policy') ?? $response->headers->get('Content-Security-Policy-Report-Only'));

        $this->assertStringContainsString("font-src 'self'", $csp);
        $this->assertStringNotContainsString('fonts.googleapis.com', $csp);
        $this->assertStringNotContainsString('fonts.gstatic.com', $csp);
        $this->assertStringNotContainsString('fonts.bunny.net', $csp);
    }
}

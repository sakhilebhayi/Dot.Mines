<?php

namespace Tests\Feature;

use App\Livewire\AIAnalytics;
use App\Livewire\AIOptimizationDashboard;
use App\Livewire\Dashboard;
use App\Livewire\Fleet;
use App\Livewire\FuelManagement;
use App\Livewire\MachineDetail;
use App\Livewire\MaintenanceDashboard;
use App\Livewire\ProductionDashboard;
use App\Livewire\Reports;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use ReflectionClass;
use Tests\TestCase;

/**
 * Slice 2 of the live-operations UX program: heavy page components are
 * lazy-loaded so the page shell paints immediately, with shaped skeleton
 * placeholders instead of a blank screen. The base TestCase disables lazy
 * globally so the rest of the suite keeps asserting real page content;
 * this test covers the lazy side explicitly.
 */
class LazyPagePlaceholdersTest extends TestCase
{
    /** @var list<class-string> */
    private const LAZY_PAGES = [
        Dashboard::class,
        ProductionDashboard::class,
        FuelManagement::class,
        MaintenanceDashboard::class,
        AIAnalytics::class,
        AIOptimizationDashboard::class,
        Fleet::class,
        MachineDetail::class,
        Reports::class,
    ];

    public function test_heavy_page_components_are_lazy(): void
    {
        foreach (self::LAZY_PAGES as $class) {
            $attributes = (new ReflectionClass($class))->getAttributes(Lazy::class);

            $this->assertNotEmpty($attributes, "{$class} must carry #[Lazy] so its mount() queries never block first paint.");
        }
    }

    public function test_placeholders_render_shaped_skeletons_not_blank_divs(): void
    {
        foreach (self::LAZY_PAGES as $class) {
            /** @var Component $component */
            $component = new $class;

            $html = $component->placeholder()->render();

            $this->assertStringContainsString('animate-pulse', $html, "{$class} placeholder");
            $this->assertStringContainsString('role="status"', $html, "{$class} placeholder");
        }
    }
}

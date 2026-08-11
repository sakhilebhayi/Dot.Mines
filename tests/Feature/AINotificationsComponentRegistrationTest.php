<?php

namespace Tests\Feature;

use App\Livewire\AINotifications;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regression test: Livewire auto-derives a component's name from its class
 * via Str::studly(), which turns "ai-notifications" into "AiNotifications"
 * (single-cap "Ai"), not the actual class "AINotifications". On a
 * case-insensitive filesystem (macOS, this repo's dev environment) the
 * autoloader silently finds the file anyway, masking the bug — on
 * production's case-sensitive filesystem it doesn't, and
 * <livewire:ai-notifications /> in navbar.blade.php threw
 * ComponentNotFoundException on every authenticated page. This test can't
 * reproduce the filesystem-level failure directly (it also runs on a
 * case-insensitive filesystem), but it does pin the explicit
 * Livewire::component() registration in AppServiceProvider that made the
 * name resolution independent of that studly/kebab round-trip.
 */
class AINotificationsComponentRegistrationTest extends TestCase
{
    public function test_ai_notifications_tag_name_resolves_to_the_correct_component_class(): void
    {
        $this->assertInstanceOf(AINotifications::class, Livewire::new('ai-notifications'));
    }
}

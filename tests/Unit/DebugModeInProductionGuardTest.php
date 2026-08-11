<?php

namespace Tests\Unit;

use App\Providers\AppServiceProvider;
use PHPUnit\Framework\TestCase;

class DebugModeInProductionGuardTest extends TestCase
{
    public function test_refuses_boot_only_when_production_and_debug_are_both_true(): void
    {
        $this->assertTrue(AppServiceProvider::shouldRefuseBoot(isProductionEnvironment: true, debugEnabled: true));

        $this->assertFalse(AppServiceProvider::shouldRefuseBoot(isProductionEnvironment: true, debugEnabled: false));
        $this->assertFalse(AppServiceProvider::shouldRefuseBoot(isProductionEnvironment: false, debugEnabled: true));
        $this->assertFalse(AppServiceProvider::shouldRefuseBoot(isProductionEnvironment: false, debugEnabled: false));
    }
}

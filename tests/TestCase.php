<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Livewire\Livewire;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Page components are lazy-loaded in production (#[Lazy] +
        // skeleton placeholders); with lazy active, an HTTP test would see
        // only the placeholder and every assertSee() against page content
        // would silently test nothing. Disable globally so the suite keeps
        // asserting the real rendered output; LazyPagePlaceholdersTest
        // covers the placeholder side explicitly.
        Livewire::withoutLazyLoading();
    }
}

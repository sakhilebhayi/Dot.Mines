<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Operational data program P7 (brief §25): the ThreeUI-inspired welcome
 * page upgrade -- a real-time operations section whose claims are the
 * platform's actual shipped capabilities, and a canvas illustration that
 * is clearly labelled as stylised, never passed off as live data.
 */
class WelcomeRealtimeSectionTest extends TestCase
{
    public function test_guests_see_the_realtime_section_with_honest_labelling(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Watch the pit move, live');
        $response->assertSee('Production from the source.');
        $response->assertSee('Stylised illustration');
        $response->assertSee('pit-canvas');
        $response->assertSee('prefers-reduced-motion', false);
    }
}

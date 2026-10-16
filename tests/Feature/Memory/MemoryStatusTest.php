<?php

namespace Tests\Feature\Memory;

use App\Models\MemoryOutboxRow;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemoryStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reports_configuration_counts_and_the_last_error_without_the_token(): void
    {
        config()->set('services.memory.url', 'http://memory.test');
        config()->set('services.memory.token', 'memory-token-value');
        $team = Team::factory()->create();
        MemoryOutboxRow::create(['team_id' => $team->id, 'stage' => 'event', 'event_id' => 'a', 'payload' => [], 'sent_at' => now()->subMinute()]);
        MemoryOutboxRow::create(['team_id' => $team->id, 'stage' => 'action', 'event_id' => 'b', 'payload' => [], 'attempts' => 2, 'last_error' => 'Dot.Memory returned HTTP 503.']);

        $this->artisan('memory:status')
            ->expectsOutputToContain('Configured: yes')
            ->expectsOutputToContain('Rows: pending 1, stuck 0, sent 1')
            ->expectsOutputToContain('Newest row: action b')
            ->expectsOutputToContain('Last error: Dot.Memory returned HTTP 503.')
            ->doesntExpectOutputToContain('memory-token-value')
            ->assertSuccessful();

        config()->set('services.memory.token', null);
        $this->artisan('memory:status')->expectsOutputToContain('Configured: no')->assertSuccessful();
    }
}

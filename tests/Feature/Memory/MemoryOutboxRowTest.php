<?php

namespace Tests\Feature\Memory;

use App\Models\MemoryOutboxRow;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemoryOutboxRowTest extends TestCase
{
    use RefreshDatabase;

    public function test_due_scope_excludes_sent_future_and_exhausted_rows_and_backoff_grows(): void
    {
        $team = Team::factory()->create();
        $base = ['team_id' => $team->id, 'stage' => 'event', 'payload' => ['x' => 1]];
        MemoryOutboxRow::create($base + ['event_id' => 'a']);
        MemoryOutboxRow::create($base + ['event_id' => 'b', 'sent_at' => now()]);
        MemoryOutboxRow::create($base + ['event_id' => 'c', 'next_attempt_at' => now()->addHour()]);
        MemoryOutboxRow::create($base + ['event_id' => 'd', 'attempts' => MemoryOutboxRow::MAX_ATTEMPTS]);

        $this->assertSame(['a'], MemoryOutboxRow::query()->due()->pluck('event_id')->all());

        $row = MemoryOutboxRow::create($base + ['event_id' => 'e', 'attempts' => 1]);
        $this->assertEqualsWithDelta(1, now()->diffInMinutes($row->backoff()), 0.1);
        $row->attempts = 4;
        $this->assertEqualsWithDelta(60, now()->diffInMinutes($row->backoff()), 0.1);
        $row->attempts = 9;
        $this->assertEqualsWithDelta(60, now()->diffInMinutes($row->backoff()), 0.1);
    }
}

<?php

namespace Tests\Feature\Memory;

use App\Models\MemoryOutboxRow;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MemoryFlushTest extends TestCase
{
    use RefreshDatabase;

    private int $teamId;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.memory.url', 'http://memory.test');
        config()->set('services.memory.token', 'memory-token-value');
        $this->teamId = Team::factory()->create()->id;
    }

    private function row(string $eventId, string $stage = 'event', array $extra = []): MemoryOutboxRow
    {
        return MemoryOutboxRow::create(['team_id' => $this->teamId, 'stage' => $stage, 'event_id' => $eventId, 'payload' => ['event_id' => $eventId]] + $extra);
    }

    public function test_sends_in_order_and_marks_sent(): void
    {
        Http::fake(['memory.test/api/intelligence/*' => Http::response(['data' => ['id' => 1]], 201)]);
        $this->row('a');
        $this->row('b', 'outcome');

        $this->artisan('memory:flush')->expectsOutputToContain('Sent 2 record(s)')->assertSuccessful();

        $this->assertSame(0, MemoryOutboxRow::query()->whereNull('sent_at')->count());
        Http::assertSentCount(2);
    }

    public function test_backs_off_when_unreachable_and_stops_the_tick(): void
    {
        Http::fake(['memory.test/*' => Http::response('', 503)]);
        $this->row('a');
        $this->row('b');

        $this->artisan('memory:flush')->assertSuccessful();

        $first = MemoryOutboxRow::query()->where('event_id', 'a')->firstOrFail();
        $this->assertSame(1, $first->attempts);
        $this->assertTrue($first->next_attempt_at?->gt(now()) ?? false);
        $this->assertSame(0, MemoryOutboxRow::query()->where('event_id', 'b')->value('attempts'));
        Http::assertSentCount(1);
    }

    public function test_gives_up_on_a_refused_payload_and_pauses_on_a_rejected_credential(): void
    {
        Http::fake([
            'memory.test/api/intelligence/actions' => Http::response(['errors' => ['execution_status' => ['invalid']]], 422),
            'memory.test/api/intelligence/events' => Http::response(['message' => 'Unauthenticated.'], 401),
        ]);
        $this->row('a', 'action');
        $this->row('b', 'event');
        $this->row('c', 'event');

        $this->artisan('memory:flush')->assertFailed();

        $this->assertSame(MemoryOutboxRow::MAX_ATTEMPTS, MemoryOutboxRow::query()->where('event_id', 'a')->value('attempts'));
        $this->assertSame(1, MemoryOutboxRow::query()->where('event_id', 'b')->value('attempts'));
        $this->assertSame(0, MemoryOutboxRow::query()->where('event_id', 'c')->value('attempts'));
    }

    public function test_inert_without_configuration_and_prune_keeps_unsent_rows(): void
    {
        config()->set('services.memory.token', null);
        $this->row('a');
        $this->artisan('memory:flush')->expectsOutputToContain('not configured')->assertSuccessful();
        $this->assertNull(MemoryOutboxRow::query()->where('event_id', 'a')->value('sent_at'));

        $this->row('old', 'event', ['sent_at' => now()->subDays(40)]);
        $this->row('new', 'event', ['sent_at' => now()->subDay()]);
        $this->artisan('memory:prune')->assertSuccessful();
        $this->assertEqualsCanonicalizing(['a', 'new'], MemoryOutboxRow::query()->pluck('event_id')->all());
    }
}

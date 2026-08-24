<?php

namespace Tests\Feature\Feed;

use App\Events\AlertTriggered;
use App\Events\FeedItemPosted;
use App\Events\GeofenceExitDetected;
use App\Events\MachineOffline;
use App\Models\Alert;
use App\Models\FeedItem;
use App\Models\Geofence;
use App\Models\GeofenceEntry;
use App\Models\Machine;
use App\Models\MineArea;
use App\Models\Operator;
use App\Models\Team;
use App\Models\User;
use App\Services\Feed\FeedPublisher;
use App\Services\Operators\ComplianceAlertService;
use App\Services\Operators\OperatorAssignmentService;
use App\Services\TeamRoleProvisioner;
use App\Support\EquipmentType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * How things enter the feed: normalised from real domain events, deduplicated
 * by the database, timestamped by when they happened.
 */
class FeedPublishingTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->team = Team::factory()->create();
    }

    private function machine(): Machine
    {
        $area = MineArea::factory()->create(['team_id' => $this->team->id]);

        return Machine::factory()->create([
            'team_id' => $this->team->id,
            'mine_area_id' => $area->id,
            'machine_type' => 'adt',
            'name' => 'ADT-07',
        ]);
    }

    public function test_the_same_dedupe_key_yields_exactly_one_item(): void
    {
        $publisher = app(FeedPublisher::class);

        $first = $publisher->publish([
            'team_id' => $this->team->id,
            'category' => FeedItem::CATEGORY_FLEET,
            'type' => 'machine.offline',
            'title' => 'ADT-07 went offline',
            'dedupe_key' => 'offline:7:123',
        ]);

        $second = $publisher->publish([
            'team_id' => $this->team->id,
            'category' => FeedItem::CATEGORY_FLEET,
            'type' => 'machine.offline',
            'title' => 'ADT-07 went offline',
            'dedupe_key' => 'offline:7:123',
        ]);

        $this->assertNotNull($first);
        $this->assertNull($second, 'Integrations deliver events twice; the feed must not show them twice.');
        $this->assertSame(1, FeedItem::withoutTeamFilter()->count());
    }

    public function test_the_same_key_in_a_different_team_is_a_different_event(): void
    {
        $publisher = app(FeedPublisher::class);
        $otherTeam = Team::factory()->create();

        foreach ([$this->team->id, $otherTeam->id] as $teamId) {
            $publisher->publish([
                'team_id' => $teamId,
                'category' => FeedItem::CATEGORY_FLEET,
                'type' => 'machine.offline',
                'title' => 'Machine offline',
                'dedupe_key' => 'offline:1:1',
            ]);
        }

        $this->assertSame(2, FeedItem::withoutTeamFilter()->count());
    }

    public function test_publishing_broadcasts_on_the_team_channel(): void
    {
        Event::fake([FeedItemPosted::class]);

        app(FeedPublisher::class)->publish([
            'team_id' => $this->team->id,
            'category' => FeedItem::CATEGORY_ANNOUNCEMENT,
            'type' => 'announcement',
            'title' => 'Blast at 15:00',
        ]);

        Event::assertDispatched(FeedItemPosted::class, function (FeedItemPosted $event): bool {
            $channels = collect($event->broadcastOn())->map(fn ($c) => (string) $c->name);

            return $channels->contains('private-team.'.$this->team->id)
                && $event->broadcastAs() === 'feed.item.posted';
        });
    }

    public function test_an_alert_event_lands_in_the_feed_with_its_own_timestamp(): void
    {
        $machine = $this->machine();
        $triggeredAt = now()->subMinutes(42);

        $alert = Alert::factory()->create([
            'team_id' => $this->team->id,
            'machine_id' => $machine->id,
            'title' => 'Excessive idle time',
            'triggered_at' => $triggeredAt,
        ]);

        event(new AlertTriggered($alert));

        $item = FeedItem::withoutTeamFilter()->where('type', 'alert.triggered')->firstOrFail();
        $this->assertSame('Excessive idle time', $item->title);
        $this->assertSame(FeedItem::CATEGORY_ALERTS, $item->category);
        $this->assertSame($machine->id, $item->machine_id);

        // occurred_at is when the alert fired, not when the feed heard.
        // (Compared to the second -- the column's own precision.)
        $this->assertSame($triggeredAt->toDateTimeString(), $item->occurred_at->toDateTimeString());

        // Same event delivered again: still one item.
        event(new AlertTriggered($alert));
        $this->assertSame(1, FeedItem::withoutTeamFilter()->where('type', 'alert.triggered')->count());
    }

    public function test_a_geofence_exit_carries_the_recorded_tonnage_not_a_recalculation(): void
    {
        $machine = $this->machine();
        $geofence = Geofence::factory()->create(['team_id' => $this->team->id, 'name' => 'Dump Area A']);

        $entry = GeofenceEntry::create([
            'team_id' => $this->team->id,
            'geofence_id' => $geofence->id,
            'machine_id' => $machine->id,
            'entry_time' => now()->subMinutes(30),
            'exit_time' => now()->subMinutes(5),
            'entry_latitude' => -26.05,
            'entry_longitude' => 28.95,
            'tonnage_loaded' => 38.2,
            'material_type' => 'ore',
        ]);

        event(new GeofenceExitDetected($entry));

        $item = FeedItem::withoutTeamFilter()->where('type', 'geofence.exited')->firstOrFail();
        $this->assertStringContainsString('ADT-07 exited Dump Area A', $item->title);
        $this->assertStringContainsString('38.2 t', (string) $item->body);
        $this->assertSame($entry->exit_time?->toDateTimeString(), $item->occurred_at->toDateTimeString());
    }

    public function test_redetecting_the_same_offline_episode_does_not_repost(): void
    {
        $machine = $this->machine();
        $machine->forceFill(['last_seen_at' => now()->subHour()])->save();

        event(new MachineOffline($machine->fresh()));
        event(new MachineOffline($machine->fresh())); // monitoring re-detects

        $this->assertSame(1, FeedItem::withoutTeamFilter()->where('type', 'machine.offline')->count());
    }

    public function test_an_assignment_publishes_an_operator_feed_item(): void
    {
        $admin = User::factory()->create();
        $this->team->update(['user_id' => $admin->id]);
        $admin->update(['current_team_id' => $this->team->id]);
        TeamRoleProvisioner::assignRole($admin, $this->team, 'admin');

        $machine = $this->machine();
        $operator = Operator::factory()->compliantFor(EquipmentType::ADT)->create(['team_id' => $this->team->id]);

        app(OperatorAssignmentService::class)->assign($operator, $machine, $admin->fresh(), 'day');

        $item = FeedItem::withoutTeamFilter()->where('type', 'operator.assigned')->firstOrFail();
        $this->assertStringContainsString('assigned to ADT-07', $item->title);
        $this->assertSame($operator->id, $item->operator_id);
        $this->assertSame(FeedItem::CATEGORY_OPERATORS, $item->category);
    }

    public function test_compliance_alerts_reach_the_feed_except_medical_ones(): void
    {
        $operator = Operator::factory()->compliantFor(EquipmentType::ADT)->create(['team_id' => $this->team->id]);

        // Licence at 20 days out, medical at 10 days out: both alert, but
        // only the licence may appear in the feed.
        $operator->qualifications()->update(['expires_on' => now()->addDays(20)->toDateString()]);
        $operator->medicals()->update(['expires_on' => now()->addDays(10)->toDateString()]);
        $operator->trainings()->update(['expires_on' => now()->addYears(2)->toDateString()]);

        app(ComplianceAlertService::class)->sweepTeam($this->team->id);

        $feedTypes = FeedItem::withoutTeamFilter()->pluck('type');

        $this->assertTrue($feedTypes->contains(fn (string $t): bool => str_starts_with($t, 'operator.compliance.')));
        $this->assertFalse(
            FeedItem::withoutTeamFilter()->get()
                ->contains(fn (FeedItem $i): bool => str_contains(strtolower($i->title), 'medical')),
            'Medical compliance stays notification-only; the feed audience is wider than the medical roles.'
        );

        // The sweep stays idempotent with the feed attached.
        app(ComplianceAlertService::class)->sweepTeam($this->team->id);
        $this->assertSame(1, FeedItem::withoutTeamFilter()->where('type', 'like', 'operator.compliance.%')->count());
    }
}

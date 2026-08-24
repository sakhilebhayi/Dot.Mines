<?php

namespace Tests\Feature\Feed;

use App\Livewire\OperationsFeed;
use App\Models\FeedItem;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The feed page: visibility, filters, posting, pinning, tenancy.
 */
class FeedPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    private function actingAs2FA(string $role): User
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->forceFill(['current_team_id' => $team->id, 'two_factor_confirmed_at' => now()])->save();
        TeamRoleProvisioner::assignRole($user, $team, $role);
        $this->actingAs($user->fresh());

        return $user->fresh();
    }

    private function seedItem(int $teamId, array $overrides = []): FeedItem
    {
        return FeedItem::withoutTeamFilter()->create([
            'team_id' => $teamId,
            'source' => FeedItem::SOURCE_SYSTEM,
            'category' => FeedItem::CATEGORY_FLEET,
            'type' => 'machine.offline',
            'title' => 'ADT-01 went offline',
            'occurred_at' => now(),
            ...$overrides,
        ]);
    }

    public function test_every_role_can_read_the_feed_including_viewer(): void
    {
        $user = $this->actingAs2FA('viewer');
        $this->seedItem($user->current_team_id);

        $this->get(route('feed'))->assertOk()->assertSeeLivewire(OperationsFeed::class);
        Livewire::test(OperationsFeed::class)->assertSee('ADT-01 went offline');
    }

    public function test_another_teams_items_are_invisible(): void
    {
        $this->actingAs2FA('admin');
        $otherTeam = Team::factory()->create();
        $this->seedItem($otherTeam->id, ['title' => 'Their secret event']);

        Livewire::test(OperationsFeed::class)->assertDontSee('Their secret event');
    }

    public function test_category_time_and_search_filters_narrow_the_stream(): void
    {
        $user = $this->actingAs2FA('admin');
        $teamId = $user->current_team_id;

        $this->seedItem($teamId, ['title' => 'ADT-01 went offline', 'category' => FeedItem::CATEGORY_FLEET]);
        $this->seedItem($teamId, [
            'title' => 'Dump Area B closed',
            'category' => FeedItem::CATEGORY_ANNOUNCEMENT,
            'type' => 'announcement',
            'dedupe_key' => null,
        ]);
        $this->seedItem($teamId, [
            'title' => 'Old maintenance note',
            'category' => FeedItem::CATEGORY_MAINTENANCE,
            'type' => 'maintenance.predicted',
            'occurred_at' => now()->subDays(10),
            'dedupe_key' => 'old:1',
        ]);

        $component = Livewire::test(OperationsFeed::class);

        $component->set('category', FeedItem::CATEGORY_ANNOUNCEMENT)
            ->assertSee('Dump Area B closed')
            ->assertDontSee('ADT-01 went offline');

        $component->set('category', '')->set('timeWindow', '24h')
            ->assertSee('ADT-01 went offline')
            ->assertDontSee('Old maintenance note');

        $component->set('timeWindow', '')->set('search', 'dump area')
            ->assertSee('Dump Area B closed')
            ->assertDontSee('ADT-01 went offline');
    }

    public function test_load_more_pages_through_history(): void
    {
        $user = $this->actingAs2FA('admin');

        for ($i = 1; $i <= 30; $i++) {
            $this->seedItem($user->current_team_id, [
                'title' => 'Event number '.$i,
                'occurred_at' => now()->subMinutes($i),
                'dedupe_key' => 'event:'.$i,
            ]);
        }

        $component = Livewire::test(OperationsFeed::class);

        // Newest first, capped at 25, with more available.
        $component->assertSee('Event number 1')->assertDontSee('Event number 30');
        $this->assertTrue($component->instance()->getStreamProperty()['hasMore']);

        $component->call('loadMore')->assertSee('Event number 30');
        $this->assertFalse($component->instance()->getStreamProperty()['hasMore']);
    }

    public function test_a_fleet_manager_can_post_and_the_post_reads_as_user_sourced(): void
    {
        $user = $this->actingAs2FA('fleet_manager');

        Livewire::test(OperationsFeed::class)
            ->set('postTitle', 'Dump Area B temporarily closed')
            ->set('postBody', 'Redirect ADTs to Dump Area A.')
            ->set('postCategory', FeedItem::CATEGORY_ANNOUNCEMENT)
            ->call('post')
            ->assertHasNoErrors();

        $item = FeedItem::withoutTeamFilter()->where('source', FeedItem::SOURCE_USER)->firstOrFail();
        $this->assertSame($user->id, $item->user_id);
        $this->assertSame($user->current_team_id, $item->team_id);
        $this->assertFalse($item->isSystem());
    }

    public function test_a_viewer_cannot_post(): void
    {
        $this->actingAs2FA('viewer');

        Livewire::test(OperationsFeed::class)
            ->set('postTitle', 'Should not land')
            ->call('post')
            ->assertForbidden();

        $this->assertSame(0, FeedItem::withoutTeamFilter()->count());
    }

    public function test_pinning_is_admin_only_and_pinned_items_surface(): void
    {
        $user = $this->actingAs2FA('admin');
        $item = $this->seedItem($user->current_team_id, [
            'title' => 'Blast scheduled at 15:00',
            'category' => FeedItem::CATEGORY_ANNOUNCEMENT,
            'source' => FeedItem::SOURCE_USER,
            'user_id' => $user->id,
        ]);

        Livewire::test(OperationsFeed::class)->call('pin', $item->id);
        $this->assertTrue($item->fresh()->isPinned());

        // A fleet manager on the same team may post but not pin.
        $manager = User::factory()->create();
        $manager->forceFill(['current_team_id' => $user->current_team_id, 'two_factor_confirmed_at' => now()])->save();
        TeamRoleProvisioner::assignRole($manager, Team::findOrFail($user->current_team_id), 'fleet_manager');
        $this->actingAs($manager->fresh());

        Livewire::test(OperationsFeed::class)
            ->call('unpin', $item->id)
            ->assertForbidden();

        $this->assertTrue($item->fresh()->isPinned());
    }

    public function test_an_expired_pin_no_longer_counts_as_pinned(): void
    {
        $user = $this->actingAs2FA('admin');
        $item = $this->seedItem($user->current_team_id, [
            'pinned_until' => now()->subHour(),
            'pinned_by' => $user->id,
        ]);

        $this->assertFalse($item->fresh()->isPinned());
        $this->assertTrue(Livewire::test(OperationsFeed::class)->instance()->getPinnedProperty()->isEmpty());
    }

    public function test_system_items_cannot_be_deleted_by_anyone(): void
    {
        $user = $this->actingAs2FA('admin');
        $item = $this->seedItem($user->current_team_id);

        Livewire::test(OperationsFeed::class)
            ->call('deleteItem', $item->id)
            ->assertForbidden();

        $this->assertNotSoftDeleted('feed_items', ['id' => $item->id]);
    }

    public function test_an_author_can_delete_their_own_post_but_not_someone_elses(): void
    {
        $author = $this->actingAs2FA('fleet_manager');
        $mine = $this->seedItem($author->current_team_id, [
            'source' => FeedItem::SOURCE_USER,
            'user_id' => $author->id,
            'type' => 'announcement',
            'category' => FeedItem::CATEGORY_ANNOUNCEMENT,
            'title' => 'My own note',
        ]);

        $colleague = User::factory()->create();
        $colleague->forceFill(['current_team_id' => $author->current_team_id, 'two_factor_confirmed_at' => now()])->save();
        TeamRoleProvisioner::assignRole($colleague, Team::findOrFail($author->current_team_id), 'fleet_manager');

        $theirs = $this->seedItem($author->current_team_id, [
            'source' => FeedItem::SOURCE_USER,
            'user_id' => $colleague->id,
            'type' => 'announcement',
            'category' => FeedItem::CATEGORY_ANNOUNCEMENT,
            'title' => 'A colleague note',
            'dedupe_key' => 'colleague:1',
        ]);

        $component = Livewire::test(OperationsFeed::class);
        $component->call('deleteItem', $mine->id);
        $this->assertSoftDeleted('feed_items', ['id' => $mine->id]);

        $component->call('deleteItem', $theirs->id)->assertForbidden();
        $this->assertNotSoftDeleted('feed_items', ['id' => $theirs->id]);
    }
}

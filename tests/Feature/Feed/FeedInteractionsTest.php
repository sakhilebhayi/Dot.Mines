<?php

namespace Tests\Feature\Feed;

use App\Livewire\OperationsFeed;
use App\Models\FeedComment;
use App\Models\FeedItem;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamRoleProvisioner;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Conversation on the feed: comments and the closed reaction vocabulary.
 */
class FeedInteractionsTest extends TestCase
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

    private function item(int $teamId): FeedItem
    {
        return FeedItem::withoutTeamFilter()->create([
            'team_id' => $teamId,
            'source' => FeedItem::SOURCE_SYSTEM,
            'category' => FeedItem::CATEGORY_FLEET,
            'type' => 'machine.offline',
            'title' => 'ADT-01 went offline',
            'occurred_at' => now(),
        ]);
    }

    public function test_an_operator_role_user_can_comment_and_it_renders(): void
    {
        $user = $this->actingAs2FA('operator');
        $item = $this->item($user->current_team_id);

        Livewire::test(OperationsFeed::class)
            ->call('toggleComments', $item->id)
            ->set('commentBody', 'This outage is planned — generator swap.')
            ->call('addComment')
            ->assertHasNoErrors()
            ->assertSee('This outage is planned');

        $this->assertDatabaseHas('feed_comments', [
            'feed_item_id' => $item->id,
            'user_id' => $user->id,
            'team_id' => $user->current_team_id,
        ]);
    }

    public function test_a_viewer_cannot_comment_or_react(): void
    {
        $user = $this->actingAs2FA('viewer');
        $item = $this->item($user->current_team_id);

        Livewire::test(OperationsFeed::class)
            ->call('toggleComments', $item->id)
            ->set('commentBody', 'should fail')
            ->call('addComment')
            ->assertForbidden();

        // A fresh component: after a 403 the previous snapshot is spent.
        Livewire::test(OperationsFeed::class)
            ->call('toggleReaction', $item->id, '👍')
            ->assertForbidden();

        $this->assertSame(0, FeedComment::withoutTeamFilter()->count());
        $this->assertSame(0, $item->reactions()->count());
    }

    public function test_commenting_on_another_teams_item_is_a_404(): void
    {
        $this->actingAs2FA('admin');
        $otherTeam = Team::factory()->create();
        $theirs = $this->item($otherTeam->id);

        // Inside a Livewire action the scope's miss surfaces as the
        // exception itself rather than an HTTP response.
        try {
            Livewire::test(OperationsFeed::class)
                ->call('toggleComments', $theirs->id)
                ->set('commentBody', 'cross-team')
                ->call('addComment');
            $this->fail('Another team\'s item must be unreachable.');
        } catch (ModelNotFoundException) {
        }

        $this->assertSame(0, FeedComment::withoutTeamFilter()->count());
    }

    public function test_a_reaction_toggles_and_the_vocabulary_is_closed(): void
    {
        $user = $this->actingAs2FA('admin');
        $item = $this->item($user->current_team_id);

        $component = Livewire::test(OperationsFeed::class);

        $component->call('toggleReaction', $item->id, '👍');
        $this->assertSame(1, $item->reactions()->count());

        // Same emoji again removes it -- a toggle, not a counter.
        $component->call('toggleReaction', $item->id, '👍');
        $this->assertSame(0, $item->reactions()->count());

        // Outside the vocabulary: refused.
        $component->call('toggleReaction', $item->id, '🎉')->assertForbidden();
    }

    public function test_comment_deletion_follows_the_same_rules_as_posts(): void
    {
        $admin = $this->actingAs2FA('admin');
        $item = $this->item($admin->current_team_id);

        $colleague = User::factory()->create();
        $colleague->forceFill(['current_team_id' => $admin->current_team_id, 'two_factor_confirmed_at' => now()])->save();
        TeamRoleProvisioner::assignRole($colleague, Team::findOrFail($admin->current_team_id), 'operator');

        $theirs = $item->comments()->create([
            'team_id' => $item->team_id,
            'user_id' => $colleague->id,
            'body' => 'their comment',
        ]);

        // The pin_feed holder may curate any comment...
        Livewire::test(OperationsFeed::class)->call('deleteComment', $theirs->id);
        $this->assertSoftDeleted('feed_comments', ['id' => $theirs->id]);

        // ...but a plain commenter may only delete their own.
        $mine = $item->comments()->create([
            'team_id' => $item->team_id,
            'user_id' => $admin->id,
            'body' => 'admin comment',
        ]);

        $this->actingAs($colleague->fresh());
        Livewire::test(OperationsFeed::class)
            ->call('deleteComment', $mine->id)
            ->assertForbidden();
    }
}

<?php

namespace Tests\Feature;

use App\Livewire\NotificationBell;
use App\Models\Notification;
use App\Models\User;
use App\Services\TeamRoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NotificationBellComponentTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<mixed> */
    private function makeTeamWithAdmin(): array
    {
        $admin = User::factory()->withPersonalTeam()->create();
        $team = $admin->currentTeam;
        TeamRoleService::provisionTeam($team, $admin);

        return [$admin, $team];
    }

    // ===================== Rendering =====================

    #[Test]
    public function component_mounts_and_renders_for_authenticated_user(): void
    {
        [$admin, $team] = $this->makeTeamWithAdmin();
        $this->actingAs($admin);

        Livewire::test(NotificationBell::class)
            ->assertOk()
            ->assertSet('open', false)
            ->assertSet('teamId', $team->id);
    }

    #[Test]
    public function component_shows_zero_unread_when_no_notifications_exist(): void
    {
        [$admin] = $this->makeTeamWithAdmin();
        $this->actingAs($admin);

        Livewire::test(NotificationBell::class)
            ->assertSet('unreadCount', 0);
    }

    #[Test]
    public function component_shows_unread_count_for_unread_notifications(): void
    {
        [$admin, $team] = $this->makeTeamWithAdmin();

        Notification::factory()->count(3)->create(['team_id' => $team->id]);

        $this->actingAs($admin);

        Livewire::test(NotificationBell::class)
            ->assertSet('unreadCount', 3);
    }

    #[Test]
    public function toggle_opens_and_closes_panel(): void
    {
        [$admin] = $this->makeTeamWithAdmin();
        $this->actingAs($admin);

        Livewire::test(NotificationBell::class)
            ->assertSet('open', false)
            ->call('toggle')
            ->assertSet('open', true)
            ->call('toggle')
            ->assertSet('open', false);
    }

    // ===================== Mark as read =====================

    #[Test]
    public function mark_as_read_reduces_unread_count(): void
    {
        [$admin, $team] = $this->makeTeamWithAdmin();

        $notification = Notification::factory()->create(['team_id' => $team->id]);

        $this->actingAs($admin);

        Livewire::test(NotificationBell::class)
            ->assertSet('unreadCount', 1)
            ->call('markAsRead', $notification->id)
            ->assertSet('unreadCount', 0);
    }

    #[Test]
    public function mark_all_as_read_clears_unread_count(): void
    {
        [$admin, $team] = $this->makeTeamWithAdmin();

        Notification::factory()->count(5)->create(['team_id' => $team->id]);

        $this->actingAs($admin);

        Livewire::test(NotificationBell::class)
            ->assertSet('unreadCount', 5)
            ->call('markAllAsRead')
            ->assertSet('unreadCount', 0);
    }

    // ===================== Cross-team security =====================

    #[Test]
    public function mark_as_read_cannot_mark_notification_from_another_team(): void
    {
        [$adminA] = $this->makeTeamWithAdmin();

        $adminB = User::factory()->withPersonalTeam()->create();
        $teamB = $adminB->currentTeam;
        $foreignNotification = Notification::factory()->create(['team_id' => $teamB->id]);

        $this->actingAs($adminA);

        Livewire::test(NotificationBell::class)
            ->call('markAsRead', $foreignNotification->id);

        $this->assertDatabaseMissing('notification_read', [
            'notification_id' => $foreignNotification->id,
            'user_id' => $adminA->id,
        ]);
    }

    #[Test]
    public function notifications_list_only_contains_own_team_notifications(): void
    {
        [$adminA, $teamA] = $this->makeTeamWithAdmin();

        $adminB = User::factory()->withPersonalTeam()->create();
        $teamB = $adminB->currentTeam;

        Notification::factory()->create(['team_id' => $teamA->id, 'title' => 'Team A notification']);
        Notification::factory()->create(['team_id' => $teamB->id, 'title' => 'Team B notification']);

        $this->actingAs($adminA);

        $notifications = Livewire::test(NotificationBell::class)->get('notifications');

        $titles = array_column($notifications, 'title');
        $this->assertContains('Team A notification', $titles);
        $this->assertNotContains('Team B notification', $titles);
    }

    // ===================== Limit =====================

    #[Test]
    public function notifications_list_is_capped_at_15_items(): void
    {
        [$admin, $team] = $this->makeTeamWithAdmin();

        Notification::factory()->count(20)->create(['team_id' => $team->id]);

        $this->actingAs($admin);

        $this->assertCount(15, Livewire::test(NotificationBell::class)->get('notifications'));
    }
}

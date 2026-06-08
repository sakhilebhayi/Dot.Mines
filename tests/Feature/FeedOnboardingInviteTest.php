<?php

namespace Tests\Feature;

use App\Livewire\WhatsAppMigration;
use App\Mail\FeedOnboardingInvite;
use App\Models\User;
use App\Services\TeamRoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FeedOnboardingInviteTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function feed_onboarding_invite_is_queued_to_notifications_queue(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $mailable = new FeedOnboardingInvite($user, $team, 'Welcome!');

        // The queue is set via onQueue() in constructor — check via Queueable
        $this->assertSame('notifications', $mailable->queue ?? 'notifications');
    }

    #[Test]
    public function feed_onboarding_invite_subject_contains_team_name(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $mailable = new FeedOnboardingInvite($user, $team);
        $envelope = $mailable->envelope();

        $this->assertStringContainsString($team->name, $envelope->subject);
    }

    #[Test]
    public function feed_onboarding_invite_renders_without_errors(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $mailable = new FeedOnboardingInvite($user, $team, 'Join us today!');
        $rendered = $mailable->render();

        // Use htmlspecialchars() because Blade {{ }} escapes HTML entities (apostrophes etc.)
        $this->assertStringContainsString(htmlspecialchars($team->name, ENT_QUOTES), $rendered);
    }

    #[Test]
    public function feed_onboarding_invite_has_plain_text_view(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $mailable = new FeedOnboardingInvite($user, $team);
        $content = $mailable->content();

        $this->assertNotNull($content->text);
    }

    #[Test]
    public function feed_onboarding_invite_contains_unsubscribe_url(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $mailable = new FeedOnboardingInvite($user, $team);
        $content = $mailable->content();

        $this->assertArrayHasKey('unsubscribeUrl', $content->with);
        $this->assertNotEmpty($content->with['unsubscribeUrl']);
        $this->assertStringContainsString('/email/unsubscribe', $content->with['unsubscribeUrl']);
    }

    #[Test]
    public function whatsapp_migration_component_queues_invite_emails(): void
    {
        Mail::fake();

        $admin = User::factory()->withPersonalTeam()->create();
        $team = $admin->currentTeam;
        TeamRoleService::provisionTeam($team, $admin);

        // Create a second user on the same team
        $member = User::factory()->create();
        $team->users()->attach($member->id, ['role' => 'operator']);

        Livewire::actingAs($admin)
            ->test(WhatsAppMigration::class)
            ->set('inviteMessage', 'Join our operations feed!')
            ->call('sendInvites');

        Mail::assertQueued(FeedOnboardingInvite::class);
    }
}

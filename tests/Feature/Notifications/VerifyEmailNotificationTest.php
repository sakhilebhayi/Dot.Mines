<?php

namespace Tests\Feature\Notifications;

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class VerifyEmailNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_verify_email_notification_implements_should_queue(): void
    {
        $notification = new VerifyEmailNotification;

        $this->assertInstanceOf(ShouldQueue::class, $notification);
    }

    public function test_send_email_verification_notification_dispatches_queued_notification(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();

        $user->sendEmailVerificationNotification();

        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_verification_send_route_does_not_throw_smtp_error_to_user(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)
            ->withSession(['_token' => 'test'])
            ->post('/email/verification-notification', ['_token' => 'test']);

        $response->assertRedirect();
        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }
}

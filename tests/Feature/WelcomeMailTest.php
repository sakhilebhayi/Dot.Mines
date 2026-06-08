<?php

namespace Tests\Feature;

use App\Mail\WelcomeMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WelcomeMailTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function welcome_mail_is_queued_on_registration(): void
    {
        Mail::fake();

        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'welcometest@example.com',
            'password' => 'Password1!Password1!',
            'password_confirmation' => 'Password1!Password1!',
            'terms' => true,
        ]);

        Mail::assertQueued(WelcomeMail::class, function (WelcomeMail $mail) {
            return $mail->user->email === 'welcometest@example.com';
        });
    }

    #[Test]
    public function welcome_mail_is_queued_to_notifications_queue(): void
    {
        Mail::fake();

        $user = User::factory()->withPersonalTeam()->create();

        $mailable = new WelcomeMail($user);

        $this->assertSame('notifications', $mailable->queue);
    }

    #[Test]
    public function welcome_mail_has_correct_subject(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $mailable = new WelcomeMail($user);
        $envelope = $mailable->envelope();

        $this->assertStringContainsString(config('app.name'), $envelope->subject);
    }

    #[Test]
    public function welcome_mail_renders_without_errors(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $mailable = new WelcomeMail($user);

        $rendered = $mailable->render();

        $this->assertStringContainsString($user->name, $rendered);
        $this->assertStringContainsString(config('app.name'), $rendered);
    }

    #[Test]
    public function welcome_mail_has_plain_text_view(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $mailable = new WelcomeMail($user);
        $content = $mailable->content();

        $this->assertNotNull($content->text);
    }

    #[Test]
    public function welcome_mail_adds_tracking_header(): void
    {
        Mail::fake();

        $user = User::factory()->withPersonalTeam()->create();
        $mailable = new WelcomeMail($user);
        $envelope = $mailable->envelope();

        $this->assertNotEmpty($envelope->using);
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_unverified_user_cannot_access_dashboard(): void
    {
        $user = User::factory()->unverified()->withPersonalTeam()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect();
    }

    #[Test]
    public function test_verified_user_can_access_dashboard(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertStatus(200);
    }

    #[Test]
    public function test_email_verification_notification_is_sent_on_registration(): void
    {
        Notification::fake();

        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'testuser@example.com',
            'password' => 'Password1!Password1!',
            'password_confirmation' => 'Password1!Password1!',
            'terms' => true,
        ]);

        $user = User::where('email', 'testuser@example.com')->firstOrFail();
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    #[Test]
    public function test_verified_email_link_verifies_user(): void
    {
        $user = User::factory()->unverified()->withPersonalTeam()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $this->actingAs($user)->get($verificationUrl);

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    #[Test]
    public function test_invalid_verification_link_does_not_verify_user(): void
    {
        $user = User::factory()->unverified()->withPersonalTeam()->create();

        $tamperedUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1('wrong@email.com')]
        );

        $this->actingAs($user)->get($tamperedUrl);

        $this->assertNull($user->fresh()->email_verified_at);
    }

    #[Test]
    public function test_user_implements_must_verify_email(): void
    {
        $user = new User;
        $this->assertInstanceOf(\Illuminate\Contracts\Auth\MustVerifyEmail::class, $user);
    }
}

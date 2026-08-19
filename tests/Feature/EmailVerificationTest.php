<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * C3b slice of the #27 split: email verification is now actually enforced.
 * User implements MustVerifyEmail (previously commented out, which made the
 * 'verified' middleware on the whole authenticated group a silent no-op),
 * Fortify's emailVerification feature is enabled, and the verification
 * email is queued with a synchronous fallback when the queue driver is
 * down -- registration must never 500 because Redis is unavailable.
 */
class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function userWithTeam(?string $verifiedAt): User
    {
        $user = User::factory()->create(['email_verified_at' => $verifiedAt]);
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        return $user;
    }

    public function test_registration_sends_a_queued_verification_email(): void
    {
        Notification::fake();

        $response = $this->post('/register', [
            'name' => 'New Miner',
            'email' => 'miner@example.com',
            'password' => 'S3cure-password!',
            'password_confirmation' => 'S3cure-password!',
            'terms' => true,
        ]);

        $response->assertRedirect();
        $user = User::where('email', 'miner@example.com')->firstOrFail();
        Notification::assertSentTo($user, VerifyEmailNotification::class);
        $this->assertContains(
            ShouldQueue::class,
            class_implements(VerifyEmailNotification::class),
            'The verification email must go through the queue so SMTP failures never 500 the registration request.'
        );
    }

    public function test_unverified_users_are_redirected_to_the_verification_notice(): void
    {
        $user = $this->userWithTeam(null);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertRedirect(route('verification.notice'));
    }

    public function test_verified_users_reach_the_app(): void
    {
        $user = $this->userWithTeam(now()->toDateTimeString());

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
    }

    public function test_verification_email_falls_back_to_synchronous_delivery_when_the_queue_driver_is_down(): void
    {
        Notification::fake();

        $user = new class extends User
        {
            protected $table = 'users';

            public function notify($instance): void
            {
                // Stands in for RedisException/Predis connection failures --
                // phpredis isn't installed locally or on CI runners.
                throw new \RuntimeException('Connection refused');
            }
        };
        $user->forceFill([
            'name' => 'Fallback Fred',
            'email' => 'fred@example.com',
            'password' => bcrypt('irrelevant'),
        ])->save();

        $user->sendEmailVerificationNotification();

        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }
}

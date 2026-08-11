<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Regression coverage for two real gaps found while auditing auth security:
 *
 * 1. `password.email` (forgot-password) and `password.update` (reset-password
 *    submission) had zero rate limiting of any kind -- no throttle
 *    middleware, and (unlike the "send reset link" step) no PasswordBroker-
 *    level cooldown either. Fixed by adding a general 'fortify' limiter to
 *    every Fortify-owned route via config('fortify.middleware').
 * 2. AppServiceProvider defined a second, dead 'login' rate limiter that
 *    FortifyServiceProvider's own (later-booting) registration silently
 *    overwrote on every request -- confirmed via reflection on
 *    RateLimiter's internal $limiters array before removing it.
 */
class FortifyRateLimitingTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_reset_submission_is_rate_limited(): void
    {
        RateLimiter::clear('fortify|127.0.0.1');

        $user = User::factory()->create();

        for ($i = 0; $i < 15; $i++) {
            $response = $this->post('/reset-password', [
                'token' => 'not-a-real-token',
                'email' => $user->email,
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

            if ($response->status() === 429) {
                $this->assertLessThanOrEqual(11, $i + 1, 'Should throttle within the configured 10/minute window.');

                return;
            }
        }

        $this->fail('Expected the password reset endpoint to be rate limited after repeated submissions.');
    }

    public function test_forgot_password_request_is_rate_limited(): void
    {
        RateLimiter::clear('fortify|127.0.0.1');

        $user = User::factory()->create();

        for ($i = 0; $i < 15; $i++) {
            $response = $this->post('/forgot-password', ['email' => $user->email]);

            if ($response->status() === 429) {
                $this->assertLessThanOrEqual(11, $i + 1);

                return;
            }
        }

        $this->fail('Expected the forgot-password endpoint to be rate limited after repeated submissions.');
    }

    public function test_the_login_route_still_uses_fortifys_real_limiter_not_the_removed_dead_one(): void
    {
        RateLimiter::clear('login');

        $user = User::factory()->create();

        for ($i = 0; $i < 10; $i++) {
            $response = $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);

            if ($response->status() === 429) {
                $this->assertLessThanOrEqual(6, $i + 1, 'Fortify\'s own limiter allows 5/minute; a much later throttle would suggest the wrong limiter is active.');

                return;
            }
        }

        $this->fail('Expected the login endpoint to be rate limited after repeated failed attempts.');
    }
}

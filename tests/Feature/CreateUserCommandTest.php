<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** With registration closed, this is how a person gets an account. */
class CreateUserCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_the_account_with_a_personal_team_and_emails_a_link(): void
    {
        Notification::fake();

        $this->artisan('user:create', ['email' => 'new.person@example.test', 'name' => 'New Person'])
            ->expectsOutputToContain('Set-password link emailed')
            ->assertSuccessful();

        $user = User::where('email', 'new.person@example.test')->firstOrFail();
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame(1, $user->ownedTeams()->where('personal_team', true)->count());
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_no_mail_creates_the_account_and_sends_nothing(): void
    {
        Notification::fake();

        $this->artisan('user:create', ['email' => 'quiet@example.test', 'name' => 'Quiet Person', '--no-mail' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('users', ['email' => 'quiet@example.test']);
        Notification::assertNothingSent();
    }

    public function test_a_duplicate_or_invalid_address_is_refused(): void
    {
        $existing = User::factory()->create();

        $this->artisan('user:create', ['email' => $existing->email, 'name' => 'Copy'])->assertFailed();
        $this->artisan('user:create', ['email' => 'not-an-email', 'name' => 'Bad'])->assertFailed();

        $this->assertSame(1, User::count());
    }
}

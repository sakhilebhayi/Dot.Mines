<?php

namespace Tests\Feature;

use App\Actions\Fortify\CreateNewUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Password::default() (used by every Fortify password flow -- registration,
 * password update, password reset, all via PasswordValidationRules) fell
 * back to Laravel's own bare minimum of "at least 8 characters" with no
 * composition requirement and no check against known-leaked passwords,
 * unless a default was explicitly registered. It never was here.
 *
 * The uncompromised() (HaveIBeenPwned) check is skipped in tests (see
 * AppServiceProvider::boot()) since it's a real network call -- these tests
 * cover the composition rules, which are the part that runs everywhere.
 */
class PasswordPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_rejects_a_password_with_no_uppercase_or_symbol(): void
    {
        $this->expectException(ValidationException::class);

        (new CreateNewUser)->create([
            'name' => 'Weak Password',
            'email' => 'weak@example.com',
            'password' => 'password12345', // all lowercase, no symbol
            'password_confirmation' => 'password12345',
            'terms' => true,
        ]);
    }

    public function test_registration_rejects_a_password_shorter_than_eight_characters(): void
    {
        $this->expectException(ValidationException::class);

        (new CreateNewUser)->create([
            'name' => 'Too Short',
            'email' => 'short@example.com',
            'password' => 'Ab1!Ab1',
            'password_confirmation' => 'Ab1!Ab1',
            'terms' => true,
        ]);
    }

    public function test_registration_accepts_a_password_meeting_the_full_policy(): void
    {
        $user = (new CreateNewUser)->create([
            'name' => 'Strong Password',
            'email' => 'strong@example.com',
            'password' => 'Correct-Horse-9',
            'password_confirmation' => 'Correct-Horse-9',
            'terms' => true,
        ]);

        $this->assertInstanceOf(User::class, $user);
        $this->assertDatabaseHas('users', ['email' => 'strong@example.com']);
    }
}

<?php

namespace App\Actions\Users;

use App\Actions\Fortify\CreateNewUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * An account made by an operator rather than by sign-up: the same user and
 * personal team an ordinary registration makes, with a random password nobody
 * sees (the person sets their own through a reset link) and the address marked
 * verified because the operator vouched for it.
 */
class CreateAccount
{
    public function __construct(private readonly CreateNewUser $registration) {}

    public function execute(string $name, string $email): User
    {
        return DB::transaction(function () use ($name, $email): User {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make(Str::random(64)),
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();
            $this->registration->createTeam($user);

            return $user;
        });
    }
}

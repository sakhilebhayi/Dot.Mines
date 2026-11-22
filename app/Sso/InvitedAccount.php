<?php

namespace App\Sso;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Jetstream\Jetstream;

/**
 * Dot.Mines accounts are made by invitation. This is the one door a central sign-in has for a person with no Mines
 * account yet: an unexpired Jetstream team invitation addressed to the exact verified email. The account it makes is bare:
 * a verified user with a random password and NO team, role or permission. The invitation itself (the signed link the person
 * is returned to) is what adds them to the team the inviter chose, so central sign-in never picks or creates a team.
 *
 * Anybody else gets the "no account yet" page and no row is created. Off entirely with DOTSSO_ACCEPT_INVITED=false.
 */
final class InvitedAccount
{
    public static function allowed(string $email): bool
    {
        $email = Str::lower(trim($email));

        if ($email === '' || ! config('dotsso.accept_invited', true)) {
            return false;
        }

        /** @var class-string<Model> $model */
        $model = Jetstream::teamInvitationModel();

        return $model::query()->whereRaw('lower(email) = ?', [$email])->exists();
    }

    public static function create(string $email, string $name, string $uuid): User
    {
        $email = Str::lower(trim($email));

        return DB::transaction(function () use ($email, $name, $uuid): User {
            $user = User::create([
                'name' => $name !== '' ? Str::limit($name, 255, '') : Str::before($email, '@'),
                'email' => $email,
                'password' => Hash::make(Str::random(64)),
            ]);
            $user->forceFill(['email_verified_at' => now(), 'infodot_user_uuid' => $uuid])->save();

            return $user;
        });
    }
}

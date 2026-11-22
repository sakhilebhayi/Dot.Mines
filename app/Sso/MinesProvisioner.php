<?php

namespace App\Sso;

use App\Mail\SsoClaimCodeMail;
use App\Models\User;
use Dot\SsoClient\Contracts\UserProvisioner;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * What the shared sign-in package needs to know about Mines' users table. Matching order and the claim flow live in the
 * package; this class reads and writes users and enforces Mines' rules at the two points that matter:
 *
 *  - create(): only for an invited person (InvitedAccount). Never team, role or permission.
 *  - hasVerifiedEmail(): an account that carries authority (an admin or owner role anywhere, an owner of a company team,
 *    or any account with two-step sign-in set up) is never linked silently by email, even when both sides say verified.
 *    It proves the mailbox with the emailed code once; after that its stored InfoDot id is the identity.
 *
 * It never touches a password, two-factor settings, tokens, sessions, roles or team memberships.
 */
class MinesProvisioner implements UserProvisioner
{
    #[\Override]
    public function findByUuid(string $uuid): ?Authenticatable
    {
        return User::query()->where('infodot_user_uuid', $uuid)->first();
    }

    #[\Override]
    public function findByEmail(string $email): ?Authenticatable
    {
        $user = User::query()->whereRaw('lower(email) = ?', [mb_strtolower(trim($email))])->first();

        return $user instanceof User ? $user : null;
    }

    #[\Override]
    public function uuidOf(Authenticatable $user): ?string
    {
        return $user instanceof User ? $user->infodot_user_uuid : null;
    }

    #[\Override]
    public function hasVerifiedEmail(Authenticatable $user): bool
    {
        return $user instanceof User && $user->email_verified_at !== null && ! self::carriesAuthority($user);
    }

    public static function carriesAuthority(User $user): bool
    {
        return filled($user->two_factor_secret)
            || $user->roles()->whereIn('name', ['admin', 'owner'])->exists()
            || $user->ownedTeams()->where('personal_team', false)->exists();
    }

    #[\Override]
    public function link(Authenticatable $user, string $uuid): void
    {
        if ($user instanceof User) {
            $user->forceFill(['infodot_user_uuid' => $uuid])->save();
        }
    }

    #[\Override]
    public function confirmEmail(Authenticatable $user): void
    {
        if ($user instanceof User && $user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }
    }

    #[\Override]
    public function create(array $claims): ?Authenticatable
    {
        if (! InvitedAccount::allowed($claims['email'])) {
            Log::notice('Central sign-in refused: no Mines account and no pending invitation for this address.');

            return null;
        }

        return InvitedAccount::create($claims['email'], $claims['name'], $claims['sub']);
    }

    #[\Override]
    public function afterSignIn(Authenticatable $user, array $claims): void
    {
        // Nothing. The team is never chosen here: EnsureTeamContext and the person's own memberships decide.
    }

    #[\Override]
    public function sendClaimCode(Authenticatable $user, string $code, int $minutes): void
    {
        if ($user instanceof User) {
            Mail::to($user->email)->send(new SsoClaimCodeMail($user->name, $code, $minutes));
        }
    }
}

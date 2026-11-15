<?php

namespace App\Actions\Ecosystem;

use App\Models\Team;
use App\Models\User;
use App\Support\ApiPayload;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Turns an InfoDot identity (already proven by InfoDot's redeem endpoint) into an EXISTING Dot.Mines user.
 *
 * It links; it does not provision. Public sign-up is closed in Mines, so unless INFODOT_PROVISION_USERS is explicitly
 * true an unknown person is refused and nothing is created. It never changes a role, a permission or a membership:
 * Mines stays the authority on who may work in which mine team.
 *
 * 1. A user already linked to this InfoDot uuid wins (an email address can change or be reused).
 * 2. Otherwise a user with the same email is linked and stamped, but only when BOTH sides verified that email and the
 *    Mines user is not already linked to a different InfoDot identity.
 * 3. A uuid linked to one Mines user is never re-pointed at another.
 * 4. No match: refused (or, only with the provision flag, a role-less account is created).
 */
class LinkUserFromInfoDot
{
    /** @param array<string, mixed> $identity the InfoDot redeem answer */
    public function resolve(array $identity): LinkOutcome
    {
        $info = ApiPayload::assoc($identity['user'] ?? null);
        $uuid = Str::lower(trim(ApiPayload::str($info['uuid'] ?? null)));
        $email = Str::lower(trim(ApiPayload::str($info['email'] ?? null)));

        if (! Str::isUuid($uuid) || $email === '' || ($info['email_verified'] ?? false) !== true) {
            return LinkOutcome::refused(LinkOutcome::BAD_IDENTITY);
        }

        $linked = User::query()->where('infodot_user_uuid', $uuid)->first();

        if ($linked) {
            return LinkOutcome::found($linked);
        }

        $user = User::query()->where(DB::raw('lower(email)'), $email)->first();

        if (! $user) {
            return $this->provisionEnabled()
                ? $this->provision($uuid, $email, ApiPayload::str($info['name'] ?? null))
                : LinkOutcome::refused(LinkOutcome::NO_ACCOUNT);
        }

        if ($user->email_verified_at === null) {
            return LinkOutcome::refused(LinkOutcome::MINES_EMAIL_UNVERIFIED);
        }

        if (filled($user->infodot_user_uuid)) {
            return LinkOutcome::refused(LinkOutcome::LINKED_ELSEWHERE);
        }

        try {
            $user->forceFill(['infodot_user_uuid' => $uuid])->save();
        } catch (QueryException) {
            // Another request linked this uuid between our lookup and the write (unique index): treat as linked elsewhere.
            return LinkOutcome::refused(LinkOutcome::LINKED_ELSEWHERE);
        }

        return LinkOutcome::found($user);
    }

    /**
     * Opens the Mines team linked to the InfoDot company the person was working in, and only when they already belong
     * to it. Anything else (no company, an unlinked company, a team they are not in) leaves their current team alone.
     *
     * @param  array<string, mixed>  $identity
     */
    public function selectTeam(User $user, array $identity): void
    {
        $uuid = Str::lower(ApiPayload::str($identity['current_team_uuid'] ?? null));

        if (! Str::isUuid($uuid)) {
            return;
        }

        $team = Team::query()->where('infodot_team_uuid', $uuid)->first();

        if ($team && $user->belongsToTeam($team) && $user->current_team_id !== $team->id) {
            $user->switchTeam($team);
        }
    }

    private function provisionEnabled(): bool
    {
        return config('ecosystem.infodot.provision') === true;
    }

    /** Only reachable with INFODOT_PROVISION_USERS=true: a verified user and personal team with NO role or permission. */
    private function provision(string $uuid, string $email, string $name): LinkOutcome
    {
        /** @var User $user */
        $user = DB::transaction(function () use ($uuid, $email, $name): User {
            $user = User::create([
                'name' => $name !== '' ? Str::limit($name, 255, '') : Str::before($email, '@'),
                'email' => $email,
                'password' => Hash::make(Str::random(64)),
            ]);
            $user->forceFill(['email_verified_at' => now(), 'infodot_user_uuid' => $uuid])->save();

            $team = Team::forceCreate([
                'user_id' => $user->id,
                'name' => explode(' ', $user->name, 2)[0]."'s Team",
                'personal_team' => true,
            ]);
            $user->ownedTeams()->save($team);
            $user->forceFill(['current_team_id' => $team->id])->save();

            return $user;
        });

        return LinkOutcome::found($user);
    }
}

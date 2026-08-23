<?php

namespace App\Actions\Fortify;

use App\Models\Team;
use App\Models\User;
use App\Services\TeamRoleProvisioner;
use App\Support\ApiPayload;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Jetstream\Jetstream;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Create a newly registered user.
     *
     * @param  array<array-key, mixed>  $input
     */
    #[\Override]
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => $this->passwordRules(),
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature() ? ['accepted', 'required'] : '',
        ])->validate();

        return DB::transaction(function () use ($input) {
            $user = User::create([
                'name' => ApiPayload::str($input['name'] ?? null),
                'email' => ApiPayload::str($input['email'] ?? null),
                'password' => Hash::make(ApiPayload::str($input['password'] ?? null)),
            ]);

            $this->createTeam($user);

            return $user;
        });
    }

    /**
     * Create a personal team for the user.
     */
    protected function createTeam(User $user): void
    {
        $team = Team::forceCreate([
            'user_id' => $user->id,
            'name' => explode(' ', $user->name, 2)[0]."'s Team",
            'personal_team' => true,
        ]);

        $user->ownedTeams()->save($team);

        // Give the team's creator full access -- without this, hasPermission()
        // returns false for everyone including the owner, since no role/permission
        // rows exist for a team until something provisions them.
        TeamRoleProvisioner::assignRole($user, $team, 'admin');
    }
}

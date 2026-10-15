<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * The documented way to give an integrator (a control-room platform, a
 * reporting job) an API token: minted for a named member of a team, with
 * the abilities the API's verb mapping needs, and printed exactly once.
 *
 * Tokens are still personal (there is no service account yet): the token
 * works only while this person is a member of the team and their current
 * team is this one, and alert actions are gated by the person's team role,
 * not by the token's abilities -- so the command says what the role can
 * and cannot do before the token leaves the terminal.
 */
class MintIntegrationToken extends Command
{
    protected $signature = 'integration:mint-token
        {email : The team member the token belongs to}
        {team : Team ID}
        {--name=integration : Token name shown on the API tokens page}
        {--abilities=read,create,update,delete : Comma-separated Sanctum abilities}';

    protected $description = 'Mint a personal API token for an integrator and report what the member\'s role allows';

    /** @var list<array{0: string, 1: string}> permission => what the integrator loses without it */
    private const CHECKS = [
        ['view_machines', 'list the fleet'],
        ['update_machines', 'assign or unassign operators'],
        ['view_operators', 'read the operator register'],
        ['acknowledge_alerts', 'acknowledge alerts through the API'],
        ['resolve_alerts', 'resolve alerts through the API'],
    ];

    public function handle(): int
    {
        $emailArg = $this->argument('email');
        /** @psalm-suppress RedundantCondition, TypeDoesNotContainType -- phpstan types argument() as array|string|null */
        $email = is_string($emailArg) ? $emailArg : '';
        $team = Team::find((int) $this->argument('team'));
        $user = User::query()->where('email', $email)->first();

        if (! $team instanceof Team || ! $user instanceof User) {
            $this->error('Team or user not found.');

            return self::FAILURE;
        }

        if (! $user->belongsToTeam($team)) {
            $this->error($user->email.' is not a member of team '.$team->id.' ('.$team->name.').');

            return self::FAILURE;
        }

        if ($user->current_team_id !== $team->id) {
            $this->warn('Current team of '.$user->email.' is #'.($user->current_team_id ?? 0).', not #'.$team->id.'. The API scopes every call to the current team: switch it (POST /api/v1/user/team/'.$team->id.') or the integrator will see the wrong fleet.');
        }

        foreach (self::CHECKS as [$permission, $consequence]) {
            if (! $user->hasPermission($permission)) {
                $this->warn('Role lacks '.$permission.': the integrator cannot '.$consequence.'.');
            }
        }

        $abilitiesOpt = $this->option('abilities');
        $abilities = array_values(array_filter(array_map('trim', explode(',', is_string($abilitiesOpt) ? $abilitiesOpt : 'read'))));
        $nameOpt = $this->option('name');
        $name = is_string($nameOpt) && $nameOpt !== '' ? $nameOpt : 'integration';

        $token = $user->createToken($name, $abilities);

        $this->info('Token for '.$user->email.' on team #'.$team->id.' ('.implode(', ', $abilities).'). Shown once; store it in the integrator now:');
        $this->line($token->plainTextToken);

        return self::SUCCESS;
    }
}

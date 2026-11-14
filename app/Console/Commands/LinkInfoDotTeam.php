<?php

namespace App\Console\Commands;

use App\Models\Team;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Administrative link between a Dot.Mines team (a billing and data tenant created before InfoDot existed) and the
 * InfoDot company it belongs to. There is no automatic mapping and none is ever guessed from names: the owner states
 * it here, once. After it, a person arriving from InfoDot while working in that company lands in this team, but only
 * if they already belong to it in Mines. Nothing about membership, roles or data changes.
 */
class LinkInfoDotTeam extends Command
{
    protected $signature = 'mines:link-infodot-team
        {team : The Mines team id}
        {companyUuid : The InfoDot company (team) uuid}
        {--unlink : Remove the link from this team instead}
        {--force : Replace a different uuid already linked to this team}';

    protected $description = 'Link a Dot.Mines team to its InfoDot company (by uuid) so SSO opens the right business';

    private function text(string $argument): string
    {
        $value = $this->argument($argument);

        return is_scalar($value) ? (string) $value : '';
    }

    public function handle(): int
    {
        $team = Team::query()->find($this->text('team'));

        if (! $team) {
            $this->error('No Mines team with that id.');

            return self::FAILURE;
        }

        if ($this->option('unlink')) {
            $team->forceFill(['infodot_team_uuid' => null])->save();
            $this->info("Team {$team->id} ({$team->name}) is no longer linked to an InfoDot company.");

            return self::SUCCESS;
        }

        $uuid = Str::lower(trim($this->text('companyUuid')));

        if (! Str::isUuid($uuid)) {
            $this->error('That is not a valid InfoDot company uuid.');

            return self::FAILURE;
        }

        $owner = Team::query()->where('infodot_team_uuid', $uuid)->where('id', '!=', $team->id)->first();

        if ($owner) {
            $this->error("That InfoDot company is already linked to Mines team {$owner->id} ({$owner->name}). Unlink it there first.");

            return self::FAILURE;
        }

        if (filled($team->infodot_team_uuid) && $team->infodot_team_uuid !== $uuid && ! $this->option('force')) {
            $this->error("Team {$team->id} is already linked to a different InfoDot company. Use --force to replace it.");

            return self::FAILURE;
        }

        $team->forceFill(['infodot_team_uuid' => $uuid])->save();
        $this->info("Team {$team->id} ({$team->name}) is now linked to InfoDot company {$uuid}.");

        return self::SUCCESS;
    }
}

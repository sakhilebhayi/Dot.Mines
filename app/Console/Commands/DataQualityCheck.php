<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Services\DataQuality\DataQualityService;
use Illuminate\Console\Command;

class DataQualityCheck extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'data-quality:check {team? : Team ID to check; omit to check every team}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check a team\'s real stored data (production, fuel, machine telemetry) for missing/impossible/stale/duplicate values';

    public function handle(DataQualityService $service): int
    {
        $teams = $this->argument('team')
            ? Team::where('id', $this->argument('team'))->get()
            : Team::all();

        if ($teams->isEmpty()) {
            $this->error('No matching team found.');

            return self::FAILURE;
        }

        $totalIssues = 0;

        foreach ($teams as $team) {
            $results = $service->checkTeam($team);
            $teamIssueCount = collect($results)->sum(fn ($issues) => $issues->count());

            if ($teamIssueCount === 0) {
                continue;
            }

            $this->line("<fg=yellow;options=bold>Team #{$team->id} ({$team->name})</> — {$teamIssueCount} issue(s)");

            foreach ($results as $source => $issues) {
                foreach ($issues as $issue) {
                    $this->line("  [{$source}] {$issue['category']}: {$issue['model']}#{$issue['id']} — {$issue['description']}");
                }
            }

            $totalIssues += $teamIssueCount;
        }

        if ($totalIssues === 0) {
            $this->info('No data quality issues found.');
        } else {
            $this->warn("Total: {$totalIssues} issue(s) across {$teams->count()} team(s).");
        }

        return self::SUCCESS;
    }
}

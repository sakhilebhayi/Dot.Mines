<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Services\AI\AIOptimizationService;
use Illuminate\Console\Command;

class RunAIAnalysis extends Command
{
    protected $signature = 'ai:analyze {--team=all : The team ID to analyze, or "all" for all teams}';

    protected $description = 'Run AI analysis to generate optimization recommendations';

    public function handle(AIOptimizationService $aiService): int
    {
        $this->info('🤖 Starting AI Analysis...');

        $teams = $this->option('team') === 'all'
            ? Team::all()
            : Team::where('id', $this->option('team'))->get();

        if ($teams->isEmpty()) {
            $this->error('No teams found to analyze.');

            return self::FAILURE;
        }

        $this->info("Analyzing {$teams->count()} team(s)...\n");

        $totalRecommendations = 0;
        $totalInsights = 0;
        $totalSavings = 0;

        foreach ($teams as $team) {
            $this->line("📊 Analyzing: <fg=cyan>{$team->name}</>");

            // Ensure team scoping for models using HasTeamFilters in non-request contexts
            app()->instance('current_team_id', $team->id);

            try {
                $result = $aiService->runComprehensiveAnalysis($team);

                $recommendations = $result['recommendations']->count();
                $insights = $result['insights']->count();
                $savings = $result['summary']['total_estimated_savings'] ?? 0;

                $totalRecommendations += $recommendations;
                $totalInsights += $insights;
                $totalSavings += $savings;

                $this->line("  ✓ Generated {$recommendations} recommendations");
                $this->line("  ✓ Discovered {$insights} insights");

                if ($savings > 0) {
                    $this->line('  ✓ Potential savings: R'.number_format($savings, 2));
                }

                // Show top 3 critical recommendations
                $critical = $result['recommendations']
                    ->where('priority', 'critical')
                    ->take(3);

                if ($critical->count() > 0) {
                    $this->newLine();
                    $this->warn('  ⚠️  Critical Recommendations:');
                    foreach ($critical as $rec) {
                        $this->line("    • {$rec->title}");
                    }
                }

                $this->newLine();

            } catch (\Exception $e) {
                $this->error("  ✗ Failed: {$e->getMessage()}");
                $this->newLine();

                continue;
            } finally {
                // Remove the team instance so subsequent iterations or other code are not affected
                if (app()->bound('current_team_id')) {
                    app()->forgetInstance('current_team_id');
                }
            }
        }

        // Summary
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('📈 Analysis Complete!');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->line("Total Recommendations: <fg=green>{$totalRecommendations}</>");
        $this->line("Total Insights: <fg=blue>{$totalInsights}</>");

        if ($totalSavings > 0) {
            $this->line('Total Potential Savings: <fg=yellow>R'.number_format($totalSavings, 2).'</>');
        }

        $this->newLine();
        $this->info('✓ View recommendations at: /ai-optimization');

        return self::SUCCESS;
    }
}

<?php

namespace Database\Seeders;

use App\Models\Machine;
use App\Models\OperatorFatigue;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class OperatorFatigueSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get the first team
        $team = Team::first();

        if (! $team) {
            $this->command->warn('No team found. Please create a team first.');

            return;
        }

        // Get users (operators) - filter by team or create some if none exist
        $users = User::whereHas('teams', function ($query) use ($team) {
            $query->where('teams.id', $team->id);
        })->take(10)->get();

        if ($users->isEmpty()) {
            $this->command->warn('No users found in the team. Using first available users.');
            $users = User::take(10)->get();
        }

        if ($users->isEmpty()) {
            $this->command->warn('No users found in the database. Cannot seed fatigue data.');

            return;
        }

        // Get machines
        $machines = Machine::where('team_id', $team->id)->get();

        if ($machines->isEmpty()) {
            $this->command->warn('No machines found for the team. Fatigue records will not have associated machines.');
        }

        $shiftTypes = ['morning', 'afternoon', 'night'];
        $alertLevels = ['none', 'low', 'medium', 'high', 'critical'];

        // Generate fatigue data for the last 14 days
        $startDate = Carbon::now()->subDays(14);
        $endDate = Carbon::now();

        $this->command->info('Generating operator fatigue data...');

        $recordsCreated = 0;

        // Loop through each day
        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            // Create 3-8 fatigue records per day (simulating different shifts)
            $recordsPerDay = rand(3, 8);

            for ($i = 0; $i < $recordsPerDay; $i++) {
                $user = $users->random();
                $machine = $machines->isNotEmpty() ? $machines->random() : null;
                $shiftType = $shiftTypes[array_rand($shiftTypes)];

                // Determine shift times based on shift type
                [$shiftStart, $shiftEnd, $hoursWorked] = $this->getShiftDetails($shiftType);

                // Calculate consecutive days (1-7)
                $consecutiveDays = rand(1, 7);

                // Calculate break time (realistic: 30-90 minutes)
                $breakTime = rand(30, 90);

                // Calculate realistic fatigue score based on various factors
                $fatigueScore = $this->calculateRealisticFatigueScore(
                    $hoursWorked,
                    $consecutiveDays,
                    $breakTime,
                    $shiftType
                );

                // Determine alert level based on fatigue score
                $alertLevel = $this->determineAlertLevel($fatigueScore);

                // Determine if operator is rested
                $isRested = $fatigueScore < 60 && $consecutiveDays < 6 && $hoursWorked < 12;

                // Random incidents (0-2, weighted towards 0)
                $incidents = rand(1, 100) > 90 ? rand(1, 2) : 0;

                // Generate notes for high-risk cases
                $notes = null;
                if ($fatigueScore >= 60) {
                    $noteOptions = [
                        'Operator showing signs of fatigue. Recommend shorter shift tomorrow.',
                        'Extended hours this week. Consider scheduling rest day.',
                        'Multiple consecutive days. Monitor closely for safety.',
                        'High fatigue score. Scheduled for mandatory rest period.',
                        'Night shift fatigue accumulation. Rest day implemented.',
                    ];
                    $notes = $noteOptions[array_rand($noteOptions)];
                }

                OperatorFatigue::create([
                    'user_id' => $user->id,
                    'team_id' => $team->id,
                    'machine_id' => $machine?->id,
                    'shift_date' => $date->toDateString(),
                    'shift_type' => $shiftType,
                    'shift_start' => $shiftStart,
                    'shift_end' => $shiftEnd,
                    'hours_worked' => $hoursWorked,
                    'consecutive_days' => $consecutiveDays,
                    'fatigue_score' => $fatigueScore,
                    'alert_level' => $alertLevel,
                    'break_time_minutes' => $breakTime,
                    'incidents_count' => $incidents,
                    'is_rested' => $isRested,
                    'notes' => $notes,
                    'metadata' => [
                        'weather_conditions' => ['clear', 'rainy', 'dusty'][array_rand(['clear', 'rainy', 'dusty'])],
                        'temperature' => rand(15, 35),
                        'workload_intensity' => ['light', 'moderate', 'heavy'][array_rand(['light', 'moderate', 'heavy'])],
                    ],
                ]);

                $recordsCreated++;
            }
        }

        $this->command->info("Created {$recordsCreated} operator fatigue records for team: {$team->name}");
    }

    /**
     * Get shift details based on shift type
     *
     * @return array<mixed>
     */
    private function getShiftDetails(string $shiftType): array
    {
        switch ($shiftType) {
            case 'morning':
                return [
                    '06:00:00',
                    rand(13, 15).':00:00',
                    rand(7, 9) + (rand(0, 5) / 10), // 7-9.5 hours
                ];
            case 'afternoon':
                return [
                    '14:00:00',
                    rand(21, 23).':00:00',
                    rand(7, 9) + (rand(0, 5) / 10), // 7-9.5 hours
                ];
            case 'night':
                return [
                    '22:00:00',
                    '06:00:00',
                    rand(8, 10) + (rand(0, 5) / 10), // 8-10.5 hours (night shifts often longer)
                ];
            default:
                return ['08:00:00', '16:00:00', 8.0];
        }
    }

    /**
     * Calculate realistic fatigue score
     */
    private function calculateRealisticFatigueScore(
        float $hoursWorked,
        int $consecutiveDays,
        float $breakTime,
        string $shiftType
    ): int {
        $score = 0;

        // Hours worked contribution (0-30 points)
        if ($hoursWorked > 12) {
            $score += 30;
        } elseif ($hoursWorked > 10) {
            $score += 25;
        } elseif ($hoursWorked > 8) {
            $score += 15;
        } elseif ($hoursWorked > 7) {
            $score += 10;
        }

        // Consecutive days contribution (0-30 points)
        if ($consecutiveDays >= 7) {
            $score += 30;
        } elseif ($consecutiveDays >= 6) {
            $score += 25;
        } elseif ($consecutiveDays >= 5) {
            $score += 20;
        } elseif ($consecutiveDays >= 4) {
            $score += 15;
        } elseif ($consecutiveDays >= 3) {
            $score += 10;
        }

        // Break time contribution (0-20 points) - inverse relationship
        if ($breakTime < 30) {
            $score += 20;
        } elseif ($breakTime < 45) {
            $score += 15;
        } elseif ($breakTime < 60) {
            $score += 10;
        } elseif ($breakTime < 75) {
            $score += 5;
        }

        // Night shift contribution (0-15 points)
        if ($shiftType === 'night') {
            $score += rand(10, 15);
        } elseif ($shiftType === 'afternoon') {
            $score += rand(3, 7);
        }

        // Add some randomness for realism (0-5 points)
        $score += rand(0, 5);

        return min($score, 100);
    }

    /**
     * Determine alert level based on fatigue score
     */
    private function determineAlertLevel(int $score): string
    {
        if ($score >= 80) {
            return 'critical';
        } elseif ($score >= 60) {
            return 'high';
        } elseif ($score >= 40) {
            return 'medium';
        } elseif ($score >= 20) {
            return 'low';
        }

        return 'none';
    }
}

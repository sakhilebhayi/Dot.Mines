<?php

namespace Database\Seeders;

use App\Models\FeedApproval;
use App\Models\FeedComment;
use App\Models\FeedPost;
use App\Models\FuelTank;
use App\Models\HaulDispatch;
use App\Models\Incident;
use App\Models\Machine;
use App\Models\MapEvent;
use App\Models\MineArea;
use App\Models\Report;
use App\Models\Shift;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🌱 Seeding demo data for all pages...');

        $teams = Team::all();

        if ($teams->isEmpty()) {
            $this->command->warn('No teams found — run ComprehensiveDataSeeder first.');
            return;
        }

        foreach ($teams as $team) {
            $this->command->info("  → Team: {$team->name}");

            $users    = $team->users()->get();
            $machines = Machine::where('team_id', $team->id)->get();
            $areas    = MineArea::where('team_id', $team->id)->get();

            if ($users->isEmpty() || $machines->isEmpty()) {
                continue;
            }

            $this->seedFuelTanks($team, $areas);
            $this->seedShifts($team);
            $this->seedFeedPosts($team, $users, $areas, $machines);
            $this->seedHaulDispatches($team, $machines, $areas);
            $this->seedMapEvents($team, $machines, $areas);
            $this->seedIncidents($team, $machines, $areas, $users);
            $this->seedReports($team);
        }

        $this->command->info('✅ Demo data seeding complete.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Fuel Tanks
    // ─────────────────────────────────────────────────────────────────────────

    private function seedFuelTanks(Team $team, $areas): void
    {
        if (FuelTank::where('team_id', $team->id)->exists()) {
            return;
        }

        $tankNames = ['Main Diesel Tank', 'Emergency Reserve', 'Workshop Tank', 'North Pit Tank', 'Plant Room Tank'];
        $fuelTypes = ['diesel', 'diesel', 'diesel', 'petrol', 'diesel'];

        foreach ($tankNames as $i => $name) {
            $capacity = fake()->randomElement([10000, 20000, 30000, 50000]);
            $level    = fake()->numberBetween((int)($capacity * 0.15), (int)($capacity * 0.95));
            $area     = $areas->isNotEmpty() ? $areas->random() : null;

            FuelTank::create([
                'team_id'              => $team->id,
                'mine_area_id'         => $area?->id,
                'name'                 => $name,
                'tank_number'          => 'T-' . str_pad($i + 1, 3, '0', STR_PAD_LEFT),
                'location_description' => $area ? "Near {$area->name}" : 'Main yard',
                'location_latitude'    => fake()->latitude(-29, -27),
                'location_longitude'   => fake()->longitude(23, 26),
                'capacity_liters'      => $capacity,
                'current_level_liters' => $level,
                'minimum_level_liters' => (int)($capacity * 0.1),
                'fuel_type'            => $fuelTypes[$i],
                'status'               => fake()->randomElement(['active', 'active', 'active', 'maintenance']),
                'last_inspection_date' => now()->subDays(fake()->numberBetween(1, 60)),
                'next_inspection_date' => now()->addDays(fake()->numberBetween(10, 90)),
                'notes'                => fake()->optional(0.4)->sentence(),
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Shifts
    // ─────────────────────────────────────────────────────────────────────────

    private function seedShifts(Team $team): void
    {
        if (Shift::where('team_id', $team->id)->exists()) {
            return;
        }

        $shiftTypes = ['A', 'B', 'C'];

        // Create 30 days of shifts (3 shifts/day)
        for ($day = 30; $day >= 0; $day--) {
            $date = now()->subDays($day)->startOfDay();

            foreach ($shiftTypes as $idx => $type) {
                $startHour = $idx * 8; // A=00:00, B=08:00, C=16:00
                $startedAt = $date->copy()->addHours($startHour);
                $endedAt   = $day === 0 && $startHour >= now()->hour
                    ? null
                    : $startedAt->copy()->addHours(8);

                $loads         = fake()->numberBetween(40, 120);
                $tonnage       = $loads * fake()->randomFloat(1, 25, 45);
                $headcount     = fake()->numberBetween(8, 25);
                $fuelConsumed  = fake()->numberBetween(800, 2400);

                Shift::create([
                    'team_id'    => $team->id,
                    'shift_type' => $type,
                    'started_at' => $startedAt,
                    'ended_at'   => $endedAt,
                    'productivity_metrics' => [
                        'loads_completed'   => $loads,
                        'total_tonnage'     => round($tonnage, 1),
                        'fuel_consumed'     => $fuelConsumed,
                        'active_machines'   => fake()->numberBetween(5, 20),
                        'operator_count'    => $headcount,
                    ],
                    'performance_summary' => [
                        'efficiency_pct'    => fake()->numberBetween(72, 98),
                        'downtime_minutes'  => fake()->numberBetween(0, 90),
                        'target_achieved'   => fake()->boolean(70),
                    ],
                    'metadata' => [],
                ]);
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Feed Posts
    // ─────────────────────────────────────────────────────────────────────────

    private function seedFeedPosts(Team $team, $users, $areas, $machines): void
    {
        if (FeedPost::where('team_id', $team->id)->exists()) {
            return;
        }

        $admin    = $users->first();
        $approver = $users->count() > 1 ? $users->skip(1)->first() : $users->first();

        $posts = [
            // Breakdowns
            [
                'category' => 'breakdown',
                'priority' => 'high',
                'shift'    => 'A',
                'body'     => 'CAT 793F (TRK-012) has a hydraulic failure on the left front steering cylinder. Estimated downtime: 4 hours. Maintenance team notified.',
                'meta'     => ['machine_id' => $machines->random()->id, 'failure_type' => 'hydraulic', 'estimated_downtime_hours' => 4],
                'approval' => 'approved',
                'comments' => ['Maintenance crew on site now.', 'Parts ordered from depot.'],
                'days_ago' => 1,
            ],
            [
                'category' => 'breakdown',
                'priority' => 'critical',
                'shift'    => 'B',
                'body'     => 'Komatsu PC1250 excavator (EXC-003) lost power suddenly at pit floor, possible engine fault. Machine has been isolated. DO NOT approach.',
                'meta'     => ['machine_id' => $machines->random()->id, 'failure_type' => 'engine', 'estimated_downtime_hours' => 12],
                'approval' => 'approved',
                'comments' => ['Area cordoned off.', 'OEM on call — ETA 3 hours.'],
                'days_ago' => 2,
            ],
            [
                'category' => 'breakdown',
                'priority' => 'normal',
                'shift'    => 'C',
                'body'     => 'Grader (GRD-007) tyre blowout on haul road section 4B. Changed and back in service.',
                'meta'     => ['machine_id' => $machines->random()->id, 'failure_type' => 'tyre', 'estimated_downtime_hours' => 1],
                'approval' => 'approved',
                'comments' => [],
                'days_ago' => 3,
            ],

            // Shift Updates
            [
                'category' => 'shift_update',
                'priority' => 'normal',
                'shift'    => 'A',
                'body'     => 'Shift A handover — 87 loads completed, 3,480 tonnes moved. Equipment all operational except GRD-007 (tyre change completed). Heading targets met.',
                'meta'     => ['loads_per_hour' => 10.9, 'total_tonnage' => 3480, 'headcount' => 18],
                'approval' => 'approved',
                'comments' => ['Good shift team!'],
                'days_ago' => 1,
            ],
            [
                'category' => 'shift_update',
                'priority' => 'normal',
                'shift'    => 'B',
                'body'     => 'Shift B update mid-shift: 52 loads done, on track for 100+ by end of shift. Weather clear, haul roads good condition.',
                'meta'     => ['loads_per_hour' => 11.2, 'total_tonnage' => 2080, 'headcount' => 20],
                'approval' => 'approved',
                'comments' => [],
                'days_ago' => 0,
            ],
            [
                'category' => 'shift_update',
                'priority' => 'high',
                'shift'    => 'C',
                'body'     => 'Night shift (C) below target — only 68 loads due to EXC-003 breakdown. All other machines performed well. Pit dewatering pump running.',
                'meta'     => ['loads_per_hour' => 8.5, 'total_tonnage' => 2720, 'headcount' => 16],
                'approval' => 'approved',
                'comments' => ['Understood. Maintenance plan in place for morning.'],
                'days_ago' => 2,
            ],

            // Safety Alerts
            [
                'category' => 'safety_alert',
                'priority' => 'critical',
                'shift'    => 'A',
                'body'     => '⚠️ SAFETY ALERT: Near-miss incident at intersection of haul road 3 and service track. Haul truck failed to yield. Speed cameras reviewed. All operators must re-attend intersection awareness briefing.',
                'meta'     => ['hazard_type' => 'near_miss', 'location' => 'HR-3 / ST intersection'],
                'approval' => 'approved',
                'comments' => ['Briefing scheduled for 14:00 today.', 'Acknowledged by all A-shift operators.'],
                'days_ago' => 3,
                'is_pinned' => true,
            ],
            [
                'category' => 'safety_alert',
                'priority' => 'high',
                'shift'    => 'B',
                'body'     => 'Blasting scheduled for North Pit at 16:30 today. All personnel to evacuate blast exclusion zone by 16:15. Safety officer will clear the area.',
                'meta'     => ['hazard_type' => 'blasting', 'location' => 'North Pit'],
                'approval' => 'approved',
                'comments' => ['Blasting complete — all clear at 16:52.'],
                'days_ago' => 4,
            ],

            // Production
            [
                'category' => 'production',
                'priority' => 'normal',
                'shift'    => 'A',
                'body'     => 'Monthly production target of 180,000 tonnes ACHIEVED with 3 days to spare. Great effort by all teams! Total this month: 183,240 tonnes.',
                'meta'     => ['target_tonnes' => 180000, 'actual_tonnes' => 183240, 'period' => 'monthly'],
                'approval' => 'approved',
                'comments' => ['Congratulations team! 🏆', 'Best month this year!'],
                'days_ago' => 5,
                'is_pinned' => true,
            ],
            [
                'category' => 'production',
                'priority' => 'normal',
                'shift'    => 'C',
                'body'     => 'Waste stripping in progress at South Extension. Approx 12,000 m³ moved today. On schedule for ore exposure next week.',
                'meta'     => ['activity' => 'waste_stripping', 'volume_m3' => 12000],
                'approval' => 'approved',
                'comments' => [],
                'days_ago' => 1,
            ],

            // General
            [
                'category' => 'general',
                'priority' => 'normal',
                'shift'    => 'A',
                'body'     => 'New site induction for 3 contractor operators completed this morning. All passed competency assessment. They will be assisting with haul fleet this week.',
                'meta'     => [],
                'approval' => 'approved',
                'comments' => [],
                'days_ago' => 2,
            ],
            [
                'category' => 'general',
                'priority' => 'normal',
                'shift'    => 'B',
                'body'     => 'Haul road maintenance section 6A completed. Surface graded and compacted — speed limit raised back to 40 km/h on this section.',
                'meta'     => [],
                'approval' => 'approved',
                'comments' => ['Good news!'],
                'days_ago' => 6,
            ],
            // Pending post
            [
                'category' => 'breakdown',
                'priority' => 'normal',
                'shift'    => 'C',
                'body'     => 'TRK-021 showing unusual oil pressure readings. Pulling it for inspection now. Will update when diagnosed.',
                'meta'     => ['machine_id' => $machines->random()->id, 'failure_type' => 'engine', 'estimated_downtime_hours' => 2],
                'approval' => 'pending',
                'comments' => [],
                'days_ago' => 0,
            ],
        ];

        foreach ($posts as $pd) {
            $author = $users->random();
            $createdAt = now()->subDays($pd['days_ago'])->subMinutes(fake()->numberBetween(0, 420));

            $post = FeedPost::create([
                'team_id'               => $team->id,
                'author_id'             => $author->id,
                'mine_area_id'          => $areas->isNotEmpty() ? $areas->random()->id : null,
                'shift'                 => $pd['shift'],
                'category'              => $pd['category'],
                'priority'              => $pd['priority'],
                'body'                  => $pd['body'],
                'meta'                  => $pd['meta'] ?? [],
                'like_count'            => fake()->numberBetween(0, 12),
                'comment_count'         => count($pd['comments']),
                'acknowledgement_count' => fake()->numberBetween(0, 8),
                'is_pinned'             => $pd['is_pinned'] ?? false,
                'created_at'            => $createdAt,
                'updated_at'            => $createdAt,
            ]);

            // Approval record
            $approvalStatus = $pd['approval'];
            FeedApproval::create([
                'post_id'     => $post->id,
                'approver_id' => $approver->id,
                'status'      => $approvalStatus,
                'reason'      => $approvalStatus === 'rejected' ? 'Missing required fields.' : null,
                'reviewed_at' => $approvalStatus !== 'pending' ? $createdAt->copy()->addMinutes(fake()->numberBetween(5, 60)) : null,
            ]);

            // Comments
            foreach ($pd['comments'] as $commentBody) {
                FeedComment::create([
                    'post_id'           => $post->id,
                    'parent_comment_id' => null,
                    'author_id'         => $users->random()->id,
                    'body'              => $commentBody,
                    'is_edited'         => false,
                    'created_at'        => $createdAt->copy()->addMinutes(fake()->numberBetween(5, 120)),
                    'updated_at'        => $createdAt->copy()->addMinutes(fake()->numberBetween(5, 120)),
                ]);
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Haul Dispatches
    // ─────────────────────────────────────────────────────────────────────────

    private function seedHaulDispatches(Team $team, $machines, $areas): void
    {
        if (HaulDispatch::where('team_id', $team->id)->exists()) {
            return;
        }

        // South Africa mine area base coords (Kimberley region)
        $baseLatOrigin  = -28.73;
        $baseLngOrigin  = 24.77;
        $baseLatDest    = -28.68;
        $baseLngDest    = 24.82;

        $statuses = ['loading', 'hauling', 'dumping', 'returning', 'hauling', 'hauling'];
        $haulMachines = $machines->filter(fn ($m) => str_contains(strtolower($m->machine_type ?? ''), 'truck') || str_contains(strtolower($m->name ?? ''), 'truck'))
            ->take(8);

        if ($haulMachines->isEmpty()) {
            $haulMachines = $machines->take(8);
        }

        foreach ($haulMachines as $idx => $machine) {
            $status       = $statuses[$idx % count($statuses)];
            $capacity     = fake()->randomElement([120, 150, 180, 220]);
            $fuelCapacity = fake()->randomElement([3500, 4000, 5000]);
            $fuelLevel    = fake()->numberBetween((int)($fuelCapacity * 0.2), $fuelCapacity);
            $totalDist    = fake()->randomFloat(2, 2, 12);
            $distDone     = match ($status) {
                'loading'   => 0,
                'hauling'   => fake()->randomFloat(2, 0.5, $totalDist * 0.9),
                'dumping'   => $totalDist,
                'returning' => fake()->randomFloat(2, 0.2, $totalDist * 0.8),
                default     => fake()->randomFloat(2, 0, $totalDist),
            };
            $distRemaining = max(0, $totalDist - $distDone);
            $speedKmh      = in_array($status, ['loading', 'dumping']) ? 0 : fake()->randomFloat(1, 15, 42);
            $tonnage       = in_array($status, ['hauling', 'dumping'])
                ? fake()->randomFloat(1, $capacity * 0.7, $capacity)
                : fake()->randomFloat(1, 0, $capacity * 0.3);

            // Current position interpolated along route
            $pct        = $totalDist > 0 ? ($distDone / $totalDist) : 0;
            $currentLat = $baseLatOrigin + ($baseLatDest - $baseLatOrigin) * $pct + fake()->randomFloat(5, -0.003, 0.003);
            $currentLng = $baseLngOrigin + ($baseLngDest - $baseLngOrigin) * $pct + fake()->randomFloat(5, -0.003, 0.003);

            $startedAt = now()->subMinutes(fake()->numberBetween(10, 180));
            $etaMinutes = $distRemaining > 0 && $speedKmh > 0
                ? (int)(($distRemaining / $speedKmh) * 60)
                : fake()->numberBetween(5, 30);

            // Build path as short polyline
            $path = [];
            $steps = fake()->numberBetween(3, 8);
            for ($s = 0; $s <= $steps; $s++) {
                $t    = $s / $steps;
                $path[] = [
                    round($baseLatOrigin + ($currentLat - $baseLatOrigin) * $t, 6),
                    round($baseLngOrigin + ($currentLng - $baseLngOrigin) * $t, 6),
                ];
            }

            HaulDispatch::create([
                'team_id'                   => $team->id,
                'machine_id'                => $machine->id,
                'mine_area_id'              => $areas->isNotEmpty() ? $areas->random()->id : null,
                'status'                    => $status,
                'origin_name'               => fake()->randomElement(['North Pit Loading', 'South Pit Loading', 'ROM Pad A', 'Crusher Feed']),
                'origin_latitude'           => round($baseLatOrigin + fake()->randomFloat(4, -0.02, 0.02), 6),
                'origin_longitude'          => round($baseLngOrigin + fake()->randomFloat(4, -0.02, 0.02), 6),
                'destination_name'          => fake()->randomElement(['Waste Dump 1', 'Ore Stockpile', 'Crusher Hopper', 'ROM Pad B']),
                'destination_latitude'      => round($baseLatDest + fake()->randomFloat(4, -0.02, 0.02), 6),
                'destination_longitude'     => round($baseLngDest + fake()->randomFloat(4, -0.02, 0.02), 6),
                'current_latitude'          => round($currentLat, 6),
                'current_longitude'         => round($currentLng, 6),
                'current_heading'           => fake()->numberBetween(0, 359),
                'current_speed_kmh'         => round($speedKmh, 1),
                'current_tonnage'           => round($tonnage, 1),
                'current_fuel_level_litres' => round($fuelLevel, 0),
                'fuel_capacity_litres'      => $fuelCapacity,
                'total_distance_km'         => round($totalDist, 2),
                'distance_remaining_km'     => round($distRemaining, 2),
                'started_at'                => $startedAt,
                'estimated_arrival_at'      => now()->addMinutes($etaMinutes),
                'completed_at'              => null,
                'path_coordinates'          => $path,
                'metadata'                  => ['cycle_count' => fake()->numberBetween(1, 12)],
            ]);
        }

        // Add a few recently completed dispatches
        for ($i = 0; $i < 5; $i++) {
            $machine = $machines->random();
            $completedAt = now()->subHours(fake()->numberBetween(1, 6));

            HaulDispatch::create([
                'team_id'              => $team->id,
                'machine_id'           => $machine->id,
                'mine_area_id'         => $areas->isNotEmpty() ? $areas->random()->id : null,
                'status'               => 'completed',
                'origin_name'          => 'North Pit Loading',
                'origin_latitude'      => round($baseLatOrigin, 6),
                'origin_longitude'     => round($baseLngOrigin, 6),
                'destination_name'     => 'Waste Dump 1',
                'destination_latitude' => round($baseLatDest, 6),
                'destination_longitude'=> round($baseLngDest, 6),
                'current_latitude'     => round($baseLatDest, 6),
                'current_longitude'    => round($baseLngDest, 6),
                'current_heading'      => 0,
                'current_speed_kmh'    => 0,
                'current_tonnage'      => 0,
                'started_at'           => $completedAt->copy()->subMinutes(fake()->numberBetween(30, 90)),
                'completed_at'         => $completedAt,
                'path_coordinates'     => [],
                'metadata'             => [],
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Map Events
    // ─────────────────────────────────────────────────────────────────────────

    private function seedMapEvents(Team $team, $machines, $areas): void
    {
        if (MapEvent::where('team_id', $team->id)->exists()) {
            return;
        }

        $eventTypes = [
            'loading', 'dumping', 'breakdown', 'idling',
            'maintenance', 'fueling', 'geofence_entry', 'geofence_exit',
            'speed_violation', 'status_change',
        ];

        $eventTemplates = [
            'loading'         => ['Machine started loading cycle', 'Loading commenced at ROM pad'],
            'dumping'         => ['Dumping in progress at waste dump', 'Load discharged at crusher'],
            'breakdown'       => ['Machine reported mechanical fault', 'Unexpected breakdown — operator notified maintenance'],
            'idling'          => ['Extended idle detected (>15 min)', 'Machine idling — awaiting instructions'],
            'maintenance'     => ['Scheduled service commenced', 'Pre-shift inspection flagged issue'],
            'fueling'         => ['Refuelling commenced at bowser', 'Machine visiting fuel bay'],
            'geofence_entry'  => ['Entered geofenced zone', 'Machine entered restricted area'],
            'geofence_exit'   => ['Exited geofenced zone', 'Machine left operational area'],
            'speed_violation' => ['Speed limit exceeded on haul road', 'Over-speed detected near intersection'],
            'status_change'   => ['Machine status changed to active', 'Machine status updated'],
        ];

        // 60 events spread over last 24 hours
        for ($i = 0; $i < 60; $i++) {
            $machine  = $machines->random();
            $area     = $areas->isNotEmpty() ? $areas->random() : null;
            $type     = fake()->randomElement($eventTypes);
            $titles   = $eventTemplates[$type];
            $occurred = now()->subMinutes(fake()->numberBetween(0, 1440));
            $resolved = in_array($type, ['breakdown', 'maintenance', 'idling'])
                ? $occurred->copy()->addMinutes(fake()->numberBetween(30, 240))
                : null;

            MapEvent::create([
                'team_id'     => $team->id,
                'machine_id'  => $machine->id,
                'mine_area_id'=> $area?->id,
                'event_type'  => $type,
                'title'       => fake()->randomElement($titles),
                'notes'       => fake()->optional(0.4)->sentence(),
                'latitude'    => round(fake()->latitude(-29, -27), 6),
                'longitude'   => round(fake()->longitude(23, 26), 6),
                'occurred_at' => $occurred,
                'resolved_at' => $resolved,
                'metadata'    => [],
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Incidents
    // ─────────────────────────────────────────────────────────────────────────

    private function seedIncidents(Team $team, $machines, $areas, $users): void
    {
        if (Incident::where('team_id', $team->id)->exists()) {
            return;
        }

        $incidents = [
            ['category' => 'near_miss',        'severity' => 'high',     'title' => 'Near-miss at haul road intersection', 'status' => 'resolved', 'days_ago' => 5],
            ['category' => 'mechanical',       'severity' => 'medium',   'title' => 'Hydraulic hose burst on CAT 793F',    'status' => 'closed',   'days_ago' => 8],
            ['category' => 'safety',           'severity' => 'critical', 'title' => 'Worker proximity alarm triggered',    'status' => 'closed',   'days_ago' => 12],
            ['category' => 'environmental',    'severity' => 'low',      'title' => 'Minor diesel spill at fuel bay',      'status' => 'resolved', 'days_ago' => 14],
            ['category' => 'equipment_damage', 'severity' => 'medium',   'title' => 'Ramp barrier damaged by haul truck',  'status' => 'investigating', 'days_ago' => 2],
            ['category' => 'near_miss',        'severity' => 'medium',   'title' => 'Light vehicle entered blast zone',    'status' => 'closed',   'days_ago' => 20],
            ['category' => 'mechanical',       'severity' => 'low',      'title' => 'Tyre blowout — no injury',            'status' => 'closed',   'days_ago' => 18],
            ['category' => 'safety',           'severity' => 'high',     'title' => 'Operator fatigue event flagged',      'status' => 'investigating', 'days_ago' => 1],
            ['category' => 'delay',            'severity' => 'low',      'title' => 'Haul road blocked 45 mins by rockfall', 'status' => 'closed', 'days_ago' => 7],
            ['category' => 'other',            'severity' => 'low',      'title' => 'GPS unit malfunction on GRD-007',     'status' => 'open',     'days_ago' => 0],
        ];

        foreach ($incidents as $inc) {
            $reporter   = $users->random();
            $resolvedBy = in_array($inc['status'], ['resolved', 'closed']) ? $users->random()->id : null;
            $occurredAt = now()->subDays($inc['days_ago'])->subHours(fake()->numberBetween(0, 12));
            $resolvedAt = $resolvedBy ? $occurredAt->copy()->addHours(fake()->numberBetween(2, 48)) : null;

            Incident::create([
                'team_id'          => $team->id,
                'machine_id'       => $machines->random()->id,
                'mine_area_id'     => $areas->isNotEmpty() ? $areas->random()->id : null,
                'reported_by'      => $reporter->id,
                'resolved_by'      => $resolvedBy,
                'category'         => $inc['category'],
                'severity'         => $inc['severity'],
                'title'            => $inc['title'],
                'description'      => fake()->paragraph(2),
                'occurred_at'      => $occurredAt,
                'status'           => $inc['status'],
                'resolution_notes' => $resolvedBy ? fake()->sentence(10) : null,
                'resolved_at'      => $resolvedAt,
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Reports
    // ─────────────────────────────────────────────────────────────────────────

    private function seedReports(Team $team): void
    {
        if (Report::where('team_id', $team->id)->exists()) {
            return;
        }

        $user = User::whereHas('teams', fn ($q) => $q->where('teams.id', $team->id))->first();
        if (!$user) return;

        $reportDefs = [
            ['type' => 'load_cycle',    'title' => 'Monthly Load Cycle Summary — April 2026',   'status' => 'completed', 'days_ago' => 5],
            ['type' => 'truck_sensors', 'title' => 'Truck Sensor Diagnostics — Week 17',        'status' => 'completed', 'days_ago' => 7],
            ['type' => 'fuel',          'title' => 'Fuel Consumption Analysis — Q1 2026',        'status' => 'completed', 'days_ago' => 10],
            ['type' => 'maintenance',   'title' => 'Equipment Maintenance Report — April 2026',  'status' => 'completed', 'days_ago' => 12],
            ['type' => 'maintenance',   'title' => 'Maintenance Schedule Overview — May 2026',   'status' => 'completed', 'days_ago' => 2],
            ['type' => 'tire_condition','title' => 'Tyre Condition Assessment — Q1 2026',        'status' => 'completed', 'days_ago' => 15],
            ['type' => 'load_cycle',    'title' => 'Load Cycle Report — March 2026',              'status' => 'completed', 'days_ago' => 35],
            ['type' => 'engine_parts',  'title' => 'Engine Parts Health Report — Week 16',        'status' => 'completed', 'days_ago' => 14],
            ['type' => 'fuel',          'title' => 'Fuel Report — Week 18 (generating)',          'status' => 'processing', 'days_ago' => 0],
            ['type' => 'custom',        'title' => 'Custom Downtime Report — last 7 days',        'status' => 'pending',   'days_ago' => 0],
        ];

        foreach ($reportDefs as $rd) {
            $startDate = now()->subDays($rd['days_ago'] + 30)->format('Y-m-d');
            $endDate   = now()->subDays($rd['days_ago'])->format('Y-m-d');

            Report::create([
                'team_id'      => $team->id,
                'generated_by' => $user->id,
                'title'        => $rd['title'],
                'type'         => $rd['type'],
                'status'       => $rd['status'],
                'file_path'    => $rd['status'] === 'completed' ? "reports/{$team->id}/{$rd['type']}_demo.pdf" : null,
                'filters'      => [
                    'start_date'  => $startDate,
                    'end_date'    => $endDate,
                    'description' => "Demo report — {$rd['title']}",
                ],
                'created_at'   => now()->subDays($rd['days_ago']),
                'updated_at'   => now()->subDays($rd['days_ago']),
            ]);
        }
    }
}

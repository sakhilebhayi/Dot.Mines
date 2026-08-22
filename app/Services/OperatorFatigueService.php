<?php

namespace App\Services;

use App\Models\OperatorFatigue;
use App\Models\Team;
use App\Models\User;

/**
 * Wires up the OperatorFatigue model's existing scoring logic
 * (calculateFatigueScore()/determineAlertLevel(), both already implemented
 * on the model) to the rest of the app -- until now nothing created
 * OperatorFatigue records or acted on a high/critical score.
 */
class OperatorFatigueService
{
    /** @psalm-suppress PossiblyUnusedMethod -- instantiated by the container (app()/DI), which psalm cannot see */
    public function __construct(private RealTimeAlertService $alertService) {}

    /**
     * Record (or update) a shift's fatigue reading for an operator, score
     * it, and raise an alert if the resulting score is high enough.
     *
     * @param  array{shift_date: string, shift_type: string, shift_start: string, shift_end: string, hours_worked: float, consecutive_days: float, break_time_minutes: float, incidents_count?: int, machine_id?: int|null, notes?: string|null}  $data
     */
    public function recordShift(User $operator, Team $team, array $data): OperatorFatigue
    {
        $fatigue = OperatorFatigue::updateOrCreate(
            [
                'user_id' => $operator->id,
                'team_id' => $team->id,
                'shift_date' => $data['shift_date'],
                'shift_type' => $data['shift_type'],
            ],
            [
                'machine_id' => $data['machine_id'] ?? null,
                'shift_start' => $data['shift_start'],
                'shift_end' => $data['shift_end'],
                'hours_worked' => $data['hours_worked'],
                'consecutive_days' => $data['consecutive_days'],
                'break_time_minutes' => $data['break_time_minutes'],
                'incidents_count' => $data['incidents_count'] ?? 0,
                'notes' => $data['notes'] ?? null,
            ]
        );

        $fatigue->fatigue_score = $fatigue->calculateFatigueScore();
        $fatigue->alert_level = $fatigue->determineAlertLevel();
        $fatigue->is_rested = ! $fatigue->needsRest();
        $fatigue->save();

        if (in_array($fatigue->alert_level, ['high', 'critical'], true)) {
            $this->alertService->dispatchFatigueAlert($fatigue, $team);
        }

        return $fatigue;
    }
}

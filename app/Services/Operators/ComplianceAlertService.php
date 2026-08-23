<?php

namespace App\Services\Operators;

use App\Models\Notification;
use App\Models\Operator;
use App\Models\OperatorMedical;
use App\Models\OperatorQualification;
use App\Models\OperatorTraining;
use App\Services\NotificationService;
use App\Support\ApiPayload;
use Illuminate\Database\Eloquent\Model;

/**
 * Warns the right people before an operator credential lapses -- once.
 *
 * The scheduler runs this daily, and daily is exactly why idempotency is the
 * hard requirement here: without it, "your licence expires in 30 days" would
 * arrive thirty times. Every alert carries a dedupe key naming the credential
 * and the milestone that fired (qualification:12:30, medical:4:expired), and
 * a key that has already produced a notification never produces another --
 * however many times the scheduler runs, and even if the site edits the
 * warning windows in between.
 *
 * Milestones come from config/operators.php (30 is the mandatory business
 * ask; 14 and 7 are the configurable escalations), plus 'expired'. A daily
 * cadence also means a skipped day cannot skip an alert: the milestone fires
 * on the first run at or inside its window, not only on the exact day.
 */
class ComplianceAlertService
{
    public const TYPE = 'operator_compliance';

    /**
     * Sweep one team's operators. Returns how many alerts were created.
     */
    public function sweepTeam(int $teamId): int
    {
        $created = 0;

        $operators = Operator::withoutTeamFilter()
            ->where('team_id', $teamId)
            ->where('employment_status', Operator::STATUS_ACTIVE)
            ->with(['qualifications', 'medicals', 'trainings'])
            ->get();

        foreach ($operators as $operator) {
            foreach ($operator->qualifications as $qualification) {
                $created += $this->alertIfDue($operator, $qualification, 'qualification', $qualification->title);
            }

            $medical = $operator->currentMedical();

            if ($medical !== null) {
                $created += $this->alertIfDue($operator, $medical, 'medical', 'Medical certificate');
            }

            foreach ($operator->trainings as $training) {
                $created += $this->alertIfDue($operator, $training, 'training', $training->course);
            }
        }

        return $created;
    }

    /**
     * @param  OperatorQualification|OperatorMedical|OperatorTraining  $credential
     */
    private function alertIfDue(Operator $operator, Model $credential, string $kind, string $label): int
    {
        if ($credential->expires_on === null) {
            return 0;
        }

        $days = $credential->daysUntilExpiry();

        if ($days === null) {
            return 0;
        }

        $milestone = $this->milestoneFor($days);

        if ($milestone === null) {
            return 0;
        }

        $dedupeKey = $kind.':'.(string) $credential->id.':'.$milestone;

        // The idempotency line: one milestone, one notification, ever.
        $alreadySent = Notification::query()
            ->where('team_id', $operator->team_id)
            ->where('type', self::TYPE)
            ->where('data->dedupe_key', $dedupeKey)
            ->exists();

        if ($alreadySent) {
            return 0;
        }

        $expired = $milestone === 'expired';

        NotificationService::dispatch([
            'team_id' => $operator->team_id,
            'type' => self::TYPE,
            'title' => $expired
                ? 'Operator credential expired'
                : 'Operator credential expiring',
            'message' => $expired
                ? $operator->name."'s ".$label.' expired on '.$credential->expires_on->format('j F Y')
                    .'. They are no longer compliant.'
                : $operator->name."'s ".$label.' expires in '.(string) $days.' days ('
                    .$credential->expires_on->format('j F Y').'). Action: renew before it lapses.',
            'alert_level' => $this->levelFor($milestone),
            'data' => [
                'dedupe_key' => $dedupeKey,
                'operator_id' => $operator->id,
                'credential_kind' => $kind,
                'credential_id' => $credential->id,
                'milestone' => $milestone,
                'expires_on' => $credential->expires_on->toDateString(),
            ],
            'action_url' => route('operators.show', ['operator' => $operator->id]),
            'notify_roles' => ['admin', 'fleet_manager'],
        ]);

        return 1;
    }

    /**
     * The milestone this credential is at today, or null when none applies.
     *
     * The WIDEST window not yet passed fires first; each narrower window
     * fires as the date approaches, and 'expired' fires once it lapses. Only
     * one milestone per run per credential -- the nearest applicable one --
     * so a credential first seen at 5 days out gets the 7-day alert, not
     * three at once.
     */
    private function milestoneFor(int $days): ?string
    {
        if ($days < 0) {
            return 'expired';
        }

        $windows = ApiPayload::intList(config('operators.warning_days'));
        sort($windows);

        foreach ($windows as $window) {
            if ($days <= $window) {
                return (string) $window;
            }
        }

        return null;
    }

    private function levelFor(string $milestone): string
    {
        return match ($milestone) {
            'expired' => NotificationService::LEVEL_CRITICAL,
            '7' => NotificationService::LEVEL_HIGH,
            default => NotificationService::LEVEL_WARNING,
        };
    }
}

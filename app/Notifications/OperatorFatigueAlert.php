<?php

namespace App\Notifications;

use App\Models\OperatorFatigue;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to every member of a team when an operator's fatigue score reaches
 * "high" or "critical" (see RealTimeAlertService::dispatchFatigueAlert()).
 * Rendered through this platform's branded mail theme (resources/views/
 * vendor/mail/html/themes/default.css) automatically -- nothing here needs
 * to reference it directly.
 */
class OperatorFatigueAlert extends Notification
{
    use Queueable;

    public function __construct(public OperatorFatigue $fatigue) {}

    /**
     * Respects the recipient's Settings -> Notifications -> "Email Alerts"
     * preference and quiet hours (defaults to on for users who haven't
     * visited that page). A critical fatigue alert always sends -- an
     * operator who should be pulled off machinery isn't something quiet
     * hours should be able to silently swallow.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        if (! $notifiable->wantsEmailAlert($this->fatigue->alert_level)) {
            return [];
        }

        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $operatorName = $this->fatigue->user?->name ?? 'An operator';
        $isCritical = $this->fatigue->alert_level === 'critical';

        $message = (new MailMessage)
            ->subject(($isCritical ? '[Critical] ' : '').'Operator Fatigue Alert: '.$operatorName)
            ->greeting('Fatigue alert')
            ->line("{$operatorName} has a fatigue score of {$this->fatigue->fatigue_score}/100 ({$this->fatigue->alert_level}) on their current shift.")
            ->line("Hours worked: {$this->fatigue->hours_worked} | Consecutive days: {$this->fatigue->consecutive_days} | Break time: {$this->fatigue->break_time_minutes} min");

        if ($isCritical) {
            $message->line('This operator should be relieved and rested before continuing to operate machinery.');
        }

        return $message
            ->action('Review fatigue roster', url('/operator-fatigue'))
            ->line('You are receiving this because your team enabled operator fatigue monitoring on Dot.Mines.');
    }
}

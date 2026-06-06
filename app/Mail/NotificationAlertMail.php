<?php

namespace App\Mail;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NotificationAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Notification $notification,
        public readonly User $recipient,
    ) {}

    public function build(): self
    {
        $subject = match ($this->notification->alert_level) {
            'critical' => '[CRITICAL] '.$this->notification->title,
            'high' => '[URGENT] '.$this->notification->title,
            'warning' => '[Warning] '.$this->notification->title,
            default => $this->notification->title,
        };

        return $this->subject($subject)
            ->from(
                (string) config('mail.addresses.info', config('mail.from.address')),
                (string) config('app.name'),
            )
            ->view('emails.notification-alert')
            ->with([
                'notification' => $this->notification,
                'recipient' => $this->recipient,
            ]);
    }
}

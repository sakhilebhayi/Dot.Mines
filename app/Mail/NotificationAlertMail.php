<?php

namespace App\Mail;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Email;

class NotificationAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Notification $notification,
        public readonly User $recipient,
    ) {}

    public function envelope(): Envelope
    {
        $subject = match ($this->notification->alert_level) {
            'critical' => '[CRITICAL] '.$this->notification->title,
            'high' => '[URGENT] '.$this->notification->title,
            'warning' => '[Warning] '.$this->notification->title,
            default => $this->notification->title,
        };

        return new Envelope(
            from: new Address(
                (string) config('mail.addresses.info', config('mail.from.address')),
                (string) config('app.name'),
            ),
            subject: $subject,
            using: [
                fn (Email $message) => $message->getHeaders()->addTextHeader('X-Mines-Mailable', static::class),
            ],
        );
    }

    public function content(): Content
    {
        $unsubscribeUrl = \URL::signedRoute('email.unsubscribe', [
            'user' => $this->recipient->id,
            'type' => 'alert_notifications',
        ]);

        return new Content(
            view: 'emails.notification-alert',
            text: 'emails.text.notification-alert',
            with: [
                'notification' => $this->notification,
                'recipient' => $this->recipient,
                'unsubscribeUrl' => $unsubscribeUrl,
            ],
        );
    }
}

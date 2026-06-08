<?php

namespace App\Listeners;

use App\Models\SentEmail;
use Illuminate\Mail\Events\MessageSent;
use Symfony\Component\Mime\Email;

/**
 * Logs every outbound email to the sent_emails table.
 *
 * The mailable class is read from the custom X-Mines-Mailable header
 * that every Mailable adds via its envelope's `using` callback.
 */
class LogSentMailListener
{
    public function handle(MessageSent $event): void
    {
        // $event->message is a magic property returning \Symfony\Component\Mime\Email
        $message = $event->message;

        if (! $message instanceof Email) {
            return;
        }

        $headers = $message->getHeaders();
        $mailableClass = $headers->get('X-Mines-Mailable')?->getBody();

        $to = collect($message->getTo())
            ->map(fn ($addr) => $addr->getAddress())
            ->implode(', ');

        SentEmail::create([
            'mailable_class' => $mailableClass,
            'to_email' => $to ?: 'unknown',
            'subject' => $message->getSubject() ?? '',
            'sent_at' => now(),
        ]);
    }
}

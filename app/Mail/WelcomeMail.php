<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Email;

class WelcomeMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly User $user)
    {
        $this->onQueue('notifications');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to '.config('app.name'),
            using: [
                fn (Email $message) => $message->getHeaders()->addTextHeader('X-Mines-Mailable', static::class),
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.welcome',
            text: 'emails.text.welcome',
        );
    }
}

<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;
use Laravel\Jetstream\TeamInvitation;
use Symfony\Component\Mime\Email;

class TeamInvitationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly TeamInvitation $invitation)
    {
        $this->onQueue('notifications');
    }

    public function envelope(): Envelope
    {
        $teamName = $this->invitation->team?->name ?? config('app.name');
        /** @var string $subject */
        $subject = __('Team Invitation — :team', ['team' => $teamName]);

        return new Envelope(
            subject: $subject,
            using: [
                fn (Email $message) => $message->getHeaders()->addTextHeader('X-Mines-Mailable', static::class),
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.team-invitation',
            text: 'emails.text.team-invitation',
            with: [
                'acceptUrl' => URL::signedRoute('team-invitations.accept', [
                    'invitation' => $this->invitation,
                ]),
            ],
        );
    }
}

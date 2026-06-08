<?php

namespace App\Mail;

use App\Models\Team;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Email;

class FeedOnboardingInvite extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $invitee,
        public readonly Team $team,
        public readonly string $personalMessage = '',
    ) {
        $this->onQueue('notifications');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "You're invited to the {$this->team->name} Operations Feed",
            using: [
                fn (Email $message) => $message->getHeaders()->addTextHeader('X-Mines-Mailable', static::class),
            ],
        );
    }

    public function content(): Content
    {
        $unsubscribeUrl = \URL::signedRoute('email.unsubscribe', [
            'user' => $this->invitee->id,
            'type' => 'feed_onboarding',
        ]);

        return new Content(
            view: 'emails.feed-onboarding-invite',
            text: 'emails.text.feed-onboarding-invite',
            with: [
                'unsubscribeUrl' => $unsubscribeUrl,
            ],
        );
    }
}

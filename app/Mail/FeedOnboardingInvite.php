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

class FeedOnboardingInvite extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $invitee,
        public readonly Team $team,
        public readonly string $personalMessage = '',
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "You're invited to the {$this->team->name} Operations Feed",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.feed-onboarding-invite',
        );
    }
}

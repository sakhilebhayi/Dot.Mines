<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;
use Laravel\Jetstream\TeamInvitation;

class TeamInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly TeamInvitation $invitation) {}

    public function build(): self
    {
        $teamName = $this->invitation->team?->name ?? config('app.name');
        /** @var string $subject */
        $subject = __('Team Invitation — :team', ['team' => $teamName]);

        return $this->subject($subject)
            ->view('emails.team-invitation')
            ->with([
                'acceptUrl' => URL::signedRoute('team-invitations.accept', [
                    'invitation' => $this->invitation,
                ]),
            ]);
    }
}

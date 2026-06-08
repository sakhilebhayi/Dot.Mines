<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Email;

class ShiftDigestMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $stats
     * @param  array<mixed>  $topPosts
     * @param  array<mixed>  $pendingApprovals
     */
    public function __construct(
        public readonly string $shift,
        public readonly string $teamName,
        public readonly array $stats,
        public readonly array $topPosts,
        public readonly array $pendingApprovals,
        public readonly ?int $recipientUserId = null,
    ) {
        $this->onQueue('notifications');
    }

    public function envelope(): Envelope
    {
        $shiftLabel = $this->shiftLabel();

        return new Envelope(
            from: new Address(
                (string) config('mail.addresses.support'),
                (string) config('app.name'),
            ),
            subject: "[{$this->teamName}] Shift Digest — {$shiftLabel}",
            using: [
                fn (Email $message) => $message->getHeaders()->addTextHeader('X-Mines-Mailable', static::class),
            ],
        );
    }

    public function content(): Content
    {
        $unsubscribeUrl = $this->recipientUserId
            ? \URL::signedRoute('email.unsubscribe', [
                'user' => $this->recipientUserId,
                'type' => 'shift_digest',
            ])
            : null;

        return new Content(
            view: 'emails.shift-digest',
            text: 'emails.text.shift-digest',
            with: [
                'shift' => $this->shift,
                'shiftLabel' => $this->shiftLabel(),
                'teamName' => $this->teamName,
                'stats' => $this->stats,
                'topPosts' => $this->topPosts,
                'pendingApprovals' => $this->pendingApprovals,
                'unsubscribeUrl' => $unsubscribeUrl,
            ],
        );
    }

    private function shiftLabel(): string
    {
        return match ($this->shift) {
            'A' => 'Shift A (06:00 – 14:00)',
            'B' => 'Shift B (14:00 – 22:00)',
            'C' => 'Shift C (22:00 – 06:00)',
            default => "Shift {$this->shift}",
        };
    }
}

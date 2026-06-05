<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ShiftDigestMail extends Mailable
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
    ) {}

    public function build(): self
    {
        $shiftLabel = match ($this->shift) {
            'A' => 'Shift A (06:00 – 14:00)',
            'B' => 'Shift B (14:00 – 22:00)',
            'C' => 'Shift C (22:00 – 06:00)',
            default => "Shift {$this->shift}",
        };

        return $this->subject("[{$this->teamName}] Shift Digest — {$shiftLabel}")
            ->view('emails.shift-digest')
            ->with([
                'shift' => $this->shift,
                'shiftLabel' => $shiftLabel,
                'teamName' => $this->teamName,
                'stats' => $this->stats,
                'topPosts' => $this->topPosts,
                'pendingApprovals' => $this->pendingApprovals,
            ]);
    }
}

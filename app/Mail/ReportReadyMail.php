<?php

namespace App\Mail;

use App\Models\Report;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Email;

class ReportReadyMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Report $report)
    {
        $this->onQueue('notifications');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                (string) config('mail.addresses.support'),
                (string) config('app.name'),
            ),
            subject: 'Your report is ready — '.$this->report->title,
            using: [
                fn (Email $message) => $message->getHeaders()->addTextHeader('X-Mines-Mailable', static::class),
            ],
        );
    }

    public function content(): Content
    {
        $downloadUrl = '#';
        try {
            if ($this->report->status === 'completed') {
                $downloadUrl = \URL::temporarySignedRoute(
                    'reports.signed-download',
                    now()->addHours(24),
                    ['report' => $this->report->id]
                );
            }
        } catch (\Exception) {
            // fallback to placeholder
        }

        return new Content(
            view: 'emails.report-ready',
            text: 'emails.text.report-ready',
            with: [
                'report' => $this->report,
                'downloadUrl' => $downloadUrl,
            ],
        );
    }
}

<?php

namespace App\Mail;

use App\Models\Report;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ReportReadyMail extends Mailable
{
    use Queueable, SerializesModels;

    public Report $report;

    public function __construct(Report $report)
    {
        $this->report = $report;
    }

    public function build(): static
    {
        // Use a signed download route so file paths are not exposed in email
        $downloadUrl = '#';
        try {
            if ($this->report->status === 'completed') {
                $downloadUrl = \URL::temporarySignedRoute(
                    'reports.signed-download',
                    now()->addHours(24),
                    ['report' => $this->report->id]
                );
            }
        } catch (\Exception $e) {
            // fallback to placeholder
            $downloadUrl = '#';
        }

        return $this->subject('Your report is ready — '.$this->report->title)
            ->view('emails.report-ready')
            ->with([
                'report' => $this->report,
                'downloadUrl' => $downloadUrl,
            ]);
    }
}

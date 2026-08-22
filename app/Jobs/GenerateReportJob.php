<?php

namespace App\Jobs;

use App\Models\Report;
use App\Services\Reports\ReportDataService;
use App\Services\Reports\Writers\CsvReportWriter;
use App\Services\Reports\Writers\PdfReportWriter;
use App\Services\Reports\Writers\XlsxReportWriter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Actually generates a Report's file from real, queried data. Report::create()
 * used to leave every report permanently stuck at status='pending' -- this
 * job, dispatched right after creation, is what was missing.
 */
class GenerateReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public Report $report) {}

    public function handle(ReportDataService $dataService): void
    {
        // Another worker/attempt already finished this report.
        if ($this->report->status !== 'pending') {
            return;
        }

        try {
            $data = $dataService->build($this->report);

            $localPath = tempnam(sys_get_temp_dir(), 'report_').'.'.$this->report->format;

            match ($this->report->format) {
                'csv' => (new CsvReportWriter)->write($data, $localPath),
                'xlsx' => (new XlsxReportWriter)->write($data, $localPath),
                'pdf' => (new PdfReportWriter)->write($this->report, $data, $localPath),
                default => throw new \InvalidArgumentException("Unsupported report format: {$this->report->format}"),
            };

            $storagePath = "reports/{$this->report->team_id}/".Str::uuid().'.'.$this->report->format;
            $contents = file_get_contents($localPath);

            if ($contents === false) {
                throw new \RuntimeException("Report file vanished before upload: {$localPath}");
            }

            Storage::put($storagePath, $contents);
            $fileSize = filesize($localPath) ?: null;
            @unlink($localPath);

            $this->report->markCompleted($storagePath, $fileSize);
        } catch (\Throwable $e) {
            Log::error('Report generation failed', [
                'report_id' => $this->report->id,
                'team_id' => $this->report->team_id,
                'type' => $this->report->type,
                'format' => $this->report->format,
                'error' => $e->getMessage(),
            ]);

            // InvalidArgumentException here is a deliberate, developer-authored
            // validation message ("Unsupported report type/format: X") -- safe
            // to show verbatim, it only echoes back the report's own type/format
            // and helps the user understand what to fix. Anything else (a raw
            // DB, storage, or PDF/spreadsheet-library exception) used to be
            // stored as-is and shown via a tooltip on the Failed badge, which
            // could leak paths or internals; that gets a generic message
            // instead. The real error is already logged above either way.
            $this->report->markFailed(
                $e instanceof \InvalidArgumentException
                    ? $e->getMessage()
                    : 'Report generation failed. Please try again, or contact support if this keeps happening.'
            );
        }
    }
}

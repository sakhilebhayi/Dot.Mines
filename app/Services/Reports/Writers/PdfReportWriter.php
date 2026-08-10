<?php

namespace App\Services\Reports\Writers;

use App\Models\Report;
use Barryvdh\DomPDF\Facade\Pdf;

class PdfReportWriter
{
    /**
     * @param  array{headers: list<string>, rows: list<array<int, mixed>>, summary: array<string, mixed>}  $data
     */
    public function write(Report $report, array $data, string $localPath): void
    {
        $pdf = Pdf::loadView('reports.pdf.generated', [
            'report' => $report,
            'data' => $data,
        ]);

        file_put_contents($localPath, $pdf->output());
    }
}

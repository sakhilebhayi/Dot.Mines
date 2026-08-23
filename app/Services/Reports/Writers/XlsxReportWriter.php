<?php

namespace App\Services\Reports\Writers;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class XlsxReportWriter
{
    /**
     * @param  array{headers: list<string>, rows: list<array<int, mixed>>, summary: array<string, mixed>}  $data
     */
    public function write(array $data, string $localPath): void
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Report');

        // Header row
        $sheet->fromArray($data['headers'], null, 'A1');
        $lastColumn = Coordinate::stringFromColumnIndex(count($data['headers']));
        $headerRange = "A1:{$lastColumn}1";
        $sheet->getStyle($headerRange)->getFont()->setBold(true);
        $sheet->getStyle($headerRange)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('D99E2B');

        // Data rows
        $sheet->fromArray($data['rows'], null, 'A2');

        foreach (range('A', $sheet->getHighestColumn()) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        // Summary block below the data
        if (is_array($data['summary'] ?? null) && $data['summary'] !== []) {
            $summaryStartRow = count($data['rows']) + 4;
            $sheet->setCellValue("A{$summaryStartRow}", 'Summary');
            $sheet->getStyle("A{$summaryStartRow}")->getFont()->setBold(true);

            $row = $summaryStartRow + 1;
            /** @psalm-suppress MixedAssignment */
            foreach ($data['summary'] as $label => $value) {
                $sheet->setCellValue("A{$row}", $label);
                $sheet->setCellValue("B{$row}", is_scalar($value) || $value === null ? $value : json_encode($value));
                $row++;
            }
        }

        (new Xlsx($spreadsheet))->save($localPath);
    }
}

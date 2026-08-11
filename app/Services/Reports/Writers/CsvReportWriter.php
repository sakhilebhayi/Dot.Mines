<?php

namespace App\Services\Reports\Writers;

class CsvReportWriter
{
    /**
     * @param  array{headers: list<string>, rows: list<array<int, mixed>>, summary: array<string, mixed>}  $data
     */
    public function write(array $data, string $localPath): void
    {
        $handle = fopen($localPath, 'w');

        fputcsv($handle, $data['headers']);
        foreach ($data['rows'] as $row) {
            fputcsv($handle, $row);
        }

        if (! empty($data['summary'])) {
            fputcsv($handle, []);
            fputcsv($handle, ['Summary']);
            foreach ($data['summary'] as $label => $value) {
                fputcsv($handle, [$label, $value]);
            }
        }

        fclose($handle);
    }
}

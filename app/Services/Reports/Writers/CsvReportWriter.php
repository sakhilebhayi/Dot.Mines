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

        if ($handle === false) {
            throw new \RuntimeException("Cannot open report file for writing: {$localPath}");
        }

        fputcsv($handle, $data['headers']);
        foreach ($data['rows'] as $row) {
            fputcsv($handle, $row);
        }

        if (is_array($data['summary'] ?? null) && $data['summary'] !== []) {
            fputcsv($handle, []);
            fputcsv($handle, ['Summary']);
            foreach ($data['summary'] as $label => $value) {
                fputcsv($handle, [$label, $value]);
            }
        }

        fclose($handle);
    }
}

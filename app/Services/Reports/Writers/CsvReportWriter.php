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
            fputcsv($handle, array_map(
                static fn (mixed $cell) => is_scalar($cell) || $cell === null || $cell instanceof \Stringable ? $cell : json_encode($cell),
                $row
            ));
        }

        if ($data['summary'] !== []) {
            fputcsv($handle, []);
            fputcsv($handle, ['Summary']);
            /** @psalm-suppress MixedAssignment */
            foreach ($data['summary'] as $label => $value) {
                fputcsv($handle, [$label, is_scalar($value) || $value === null || $value instanceof \Stringable ? $value : json_encode($value)]);
            }
        }

        fclose($handle);
    }
}

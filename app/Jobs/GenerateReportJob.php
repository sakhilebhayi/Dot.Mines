<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\FuelTransaction;
use App\Models\Machine;
use App\Models\MachineMetric;
use App\Models\MaintenanceRecord;
use App\Models\ProductionRecord;
use App\Models\Report;
use App\Services\AuditService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Shuchkin\SimpleXLSXGen;

class GenerateReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Maximum number of attempts.
     */
    public int $tries = 3;

    /**
     * Timeout in seconds.
     */
    public int $timeout = 300;

    public function __construct(
        protected Report $report,
    ) {}

    public function handle(): void
    {
        try {
            $this->report->refresh();

            if ($this->report->status !== 'processing') {
                $this->report->markProcessing();
            }

            $format = $this->report->format ?? 'csv';
            $type = $this->report->type;

            $data = $this->queryData($type, $this->report->filters ?? []);

            $filePath = match ($format) {
                'pdf' => $this->generatePdf($data, $type),
                'xlsx' => $this->generateXlsx($data, $type),
                default => $this->generateCsv($data, $type, 'csv'),
            };

            $fullPath = Storage::disk($this->reportDisk())->path($filePath);
            $fileSize = file_exists($fullPath) ? (int) filesize($fullPath) : 0;

            $this->report->markCompleted($filePath, $fileSize);

            AuditService::log(
                AuditLog::REPORT_GENERATED,
                "Report generated: {$this->report->title}",
                $this->report,
                ['report_id' => $this->report->id, 'type' => $type, 'format' => $format],
                (int) $this->report->generated_by,
                $this->report->team_id
            );
        } catch (\Throwable $e) {
            Log::error('GenerateReportJob failed', [
                'report_id' => $this->report->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->report->markFailed($e->getMessage());

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        $this->report->refresh();

        if ($this->report->status !== 'failed') {
            $this->report->markFailed($exception->getMessage());
        }
    }

    // -----------------------------------------------------------------------
    // Data query
    // -----------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $filters
     * @return array<mixed>
     */
    private function queryData(string $type, array $filters): array
    {
        $teamId = $this->report->team_id;
        $startDate = isset($filters['start_date']) ? Carbon::parse($filters['start_date']) : null;
        $endDate = isset($filters['end_date']) ? Carbon::parse($filters['end_date']) : null;
        $machineIds = ! empty($filters['machine_ids']) ? (array) $filters['machine_ids'] : null;

        return match ($type) {
            'production', 'load_cycle' => $this->queryProduction($teamId, $startDate, $endDate, $machineIds),

            'fleet_utilization', 'truck_sensors' => $this->queryFleetUtilization($teamId, $startDate, $endDate, $machineIds),

            'tire_condition' => $this->queryTireCondition($teamId, $startDate, $endDate, $machineIds),

            'maintenance_schedule', 'maintenance', 'engine_parts' => $this->queryMaintenance($teamId, $startDate, $endDate, $machineIds, $type),

            'fuel_consumption', 'fuel' => $this->queryFuelConsumption($teamId, $startDate, $endDate, $machineIds),

            'downtime_analysis' => $this->queryDowntime($teamId, $startDate, $endDate, $machineIds),

            default /* material_tracking, custom */ => $this->queryProduction($teamId, $startDate, $endDate, $machineIds),
        };
    }

    /**
     * @param  array<int>|null  $machineIds
     * @return array<mixed>
     */
    private function queryProduction(
        int $teamId,
        ?Carbon $start,
        ?Carbon $end,
        ?array $machineIds
    ): array {
        $query = ProductionRecord::where('team_id', $teamId)
            ->with(['machine:id,name,registration_number', 'mineArea:id,name']);

        if ($start) {
            $query->whereDate('record_date', '>=', $start);
        }
        if ($end) {
            $query->whereDate('record_date', '<=', $end);
        }
        if ($machineIds) {
            $query->whereIn('machine_id', $machineIds);
        }

        $records = $query->lazyById(500, 'id');

        $headers = [
            'Date', 'Shift', 'Mine Area', 'Machine', 'Unit',
            'Quantity Produced', 'Target Quantity', 'System Quantity',
            'System Variance %', 'Status', 'Notes',
        ];

        $rows = $records->map(fn ($r) => [
            $r->record_date?->toDateString(),
            $r->shift,
            $r->mineArea?->name ?? '',
            $r->machine?->name ?? '',
            $r->unit,
            $r->quantity_produced,
            $r->target_quantity,
            $r->system_quantity,
            $r->system_variance_percentage !== null ? round($r->system_variance_percentage, 2).'%' : '',
            $r->status,
            $r->notes,
        ])->toArray();

        return compact('headers', 'rows');
    }

    /**
     * @param  array<int>|null  $machineIds
     * @return array<mixed>
     */
    private function queryFleetUtilization(
        int $teamId,
        ?Carbon $start,
        ?Carbon $end,
        ?array $machineIds
    ): array {
        $machineQuery = Machine::where('team_id', $teamId);
        if ($machineIds) {
            $machineQuery->whereIn('id', $machineIds);
        }
        $machines = $machineQuery->get();

        $rows = [];
        foreach ($machines as $machine) {
            $metricQuery = MachineMetric::where('team_id', $teamId)
                ->where('machine_id', $machine->id);

            if ($start) {
                $metricQuery->where('recorded_at', '>=', $start);
            }
            if ($end) {
                $metricQuery->where('recorded_at', '<=', $end->endOfDay());
            }

            $metrics = $metricQuery->orderBy('recorded_at', 'desc')->first();

            $rows[] = [
                $machine->name,
                $machine->machine_type,
                $machine->registration_number ?? '',
                $machine->status,
                $metrics?->total_hours ?? $machine->hours_meter,
                $metrics?->idle_hours ?? '',
                $metrics?->operating_hours ?? '',
                $metrics?->fuel_level ?? '',
                $metrics?->engine_rpm ?? '',
                $metrics?->engine_temperature ?? '',
                $metrics?->recorded_at?->toDateTimeString() ?? '',
            ];
        }

        $headers = [
            'Machine', 'Type', 'Registration', 'Status',
            'Total Hours', 'Idle Hours', 'Operating Hours',
            'Fuel Level (%)', 'Engine RPM', 'Engine Temp (°C)',
            'Last Metric At',
        ];

        return compact('headers', 'rows');
    }

    /**
     * @param  array<int>|null  $machineIds
     * @return array<mixed>
     */
    private function queryTireCondition(
        int $teamId,
        ?Carbon $start,
        ?Carbon $end,
        ?array $machineIds
    ): array {
        $query = MachineMetric::where('team_id', $teamId)
            ->whereNotNull('tire_pressure_front_left')
            ->with('machine:id,name,registration_number');

        if ($start) {
            $query->where('recorded_at', '>=', $start);
        }
        if ($end) {
            $query->where('recorded_at', '<=', $end->endOfDay());
        }
        if ($machineIds) {
            $query->whereIn('machine_id', $machineIds);
        }

        $records = $query->lazyById(500, 'id');

        $headers = [
            'Machine', 'Registration', 'Recorded At',
            'FL (kPa)', 'FR (kPa)', 'RL (kPa)', 'RR (kPa)',
        ];

        $rows = $records->map(fn ($r) => [
            $r->machine?->name ?? '',
            $r->machine?->registration_number ?? '',
            $r->recorded_at?->toDateTimeString(),
            $r->tire_pressure_front_left,
            $r->tire_pressure_front_right,
            $r->tire_pressure_rear_left,
            $r->tire_pressure_rear_right,
        ])->toArray();

        return compact('headers', 'rows');
    }

    /**
     * @param  array<int>|null  $machineIds
     * @return array<mixed>
     */
    private function queryMaintenance(
        int $teamId,
        ?Carbon $start,
        ?Carbon $end,
        ?array $machineIds,
        string $type
    ): array {
        $query = MaintenanceRecord::where('team_id', $teamId)
            ->with('machine:id,name,registration_number');

        if ($type === 'engine_parts') {
            $query->where(function ($q) {
                $q->where('maintenance_type', 'like', '%engine%')
                    ->orWhere('title', 'like', '%engine%')
                    ->orWhere('description', 'like', '%engine%');
            });
        }

        if ($start) {
            $query->where('scheduled_date', '>=', $start);
        }
        if ($end) {
            $query->where('scheduled_date', '<=', $end->endOfDay());
        }
        if ($machineIds) {
            $query->whereIn('machine_id', $machineIds);
        }

        $records = $query->lazyById(500, 'id');

        $headers = [
            'Work Order', 'Machine', 'Registration', 'Type', 'Title',
            'Status', 'Priority', 'Scheduled Date', 'Started At',
            'Completed At', 'Labor Hours', 'Total Cost', 'Technician Notes',
        ];

        $rows = $records->map(fn ($r) => [
            $r->work_order_number ?? '',
            $r->machine?->name ?? '',
            $r->machine?->registration_number ?? '',
            $r->maintenance_type,
            $r->title,
            $r->status,
            $r->priority,
            $r->scheduled_date?->toDateTimeString(),
            $r->started_at?->toDateTimeString() ?? '',
            $r->completed_at?->toDateTimeString() ?? '',
            $r->labor_hours,
            $r->total_cost,
            $r->technician_notes ?? '',
        ])->toArray();

        return compact('headers', 'rows');
    }

    /**
     * @param  array<int>|null  $machineIds
     * @return array<mixed>
     */
    private function queryFuelConsumption(
        int $teamId,
        ?Carbon $start,
        ?Carbon $end,
        ?array $machineIds
    ): array {
        $query = FuelTransaction::where('team_id', $teamId)
            ->with('machine:id,name,registration_number');

        if ($start) {
            $query->where('transaction_date', '>=', $start);
        }
        if ($end) {
            $query->where('transaction_date', '<=', $end->endOfDay());
        }
        if ($machineIds) {
            $query->whereIn('machine_id', $machineIds);
        }

        $records = $query->lazyById(500, 'id');

        $headers = [
            'Date', 'Machine', 'Registration', 'Transaction Type', 'Fuel Type',
            'Quantity (L)', 'Unit Price', 'Total Cost', 'Odometer Reading',
            'Machine Hours', 'Supplier', 'Invoice Number', 'Notes',
        ];

        $rows = $records->map(fn ($r) => [
            $r->transaction_date?->toDateTimeString(),
            $r->machine?->name ?? '',
            $r->machine?->registration_number ?? '',
            $r->transaction_type,
            $r->fuel_type,
            $r->quantity_liters,
            $r->unit_price,
            $r->total_cost,
            $r->odometer_reading,
            $r->machine_hours,
            $r->supplier ?? '',
            $r->invoice_number ?? '',
            $r->notes ?? '',
        ])->toArray();

        return compact('headers', 'rows');
    }

    /**
     * @param  array<int>|null  $machineIds
     * @return array<mixed>
     */
    private function queryDowntime(
        int $teamId,
        ?Carbon $start,
        ?Carbon $end,
        ?array $machineIds
    ): array {
        $query = MaintenanceRecord::where('team_id', $teamId)
            ->where(function ($q) {
                $q->where('maintenance_type', 'breakdown')
                    ->orWhere('maintenance_type', 'corrective')
                    ->orWhere('machine_operational', false);
            })
            ->with('machine:id,name,registration_number');

        if ($start) {
            $query->where('started_at', '>=', $start);
        }
        if ($end) {
            $query->where('started_at', '<=', $end->endOfDay());
        }
        if ($machineIds) {
            $query->whereIn('machine_id', $machineIds);
        }

        $records = $query->lazyById(500, 'id');

        $headers = [
            'Machine', 'Registration', 'Type', 'Title', 'Priority',
            'Started At', 'Completed At', 'Downtime Hours', 'Total Cost',
            'Description', 'Work Performed',
        ];

        $rows = $records->map(function ($r) {
            $downtimeHours = '';
            if ($r->started_at && $r->completed_at) {
                $downtimeHours = round($r->started_at->diffInMinutes($r->completed_at) / 60, 2);
            }

            return [
                $r->machine?->name ?? '',
                $r->machine?->registration_number ?? '',
                $r->maintenance_type,
                $r->title,
                $r->priority,
                $r->started_at?->toDateTimeString() ?? '',
                $r->completed_at?->toDateTimeString() ?? '',
                $downtimeHours,
                $r->total_cost,
                $r->description ?? '',
                $r->work_performed ?? '',
            ];
        })->toArray();

        return compact('headers', 'rows');
    }

    // -----------------------------------------------------------------------
    // File generation
    // -----------------------------------------------------------------------

    /**
     * @param  array<mixed>  $data
     */
    private function generateCsv(array $data, string $type, string $extension = 'csv'): string
    {
        $dir = "reports/{$this->report->team_id}";
        Storage::disk($this->reportDisk())->makeDirectory($dir);

        $filename = "{$dir}/{$this->report->id}_{$type}_{$this->report->created_at->format('Ymd_His')}.{$extension}";
        $fullPath = Storage::disk($this->reportDisk())->path($filename);

        $handle = fopen($fullPath, 'w');

        if ($handle === false) {
            throw new \RuntimeException("Cannot write report file: {$fullPath}");
        }

        // BOM for UTF-8 Excel compatibility
        fwrite($handle, "\xEF\xBB\xBF");

        // Title row
        fputcsv($handle, ["Report: {$this->report->title}"]);
        fputcsv($handle, ['Generated: '.now()->toDateTimeString()]);
        fputcsv($handle, ["Type: {$type}"]);
        fputcsv($handle, []);

        // Headers + rows
        fputcsv($handle, $data['headers']);
        foreach ($data['rows'] as $row) {
            fputcsv($handle, $row);
        }

        // Summary row
        fputcsv($handle, []);
        fputcsv($handle, ['Total Records', count($data['rows'])]);

        fclose($handle);

        return $filename;
    }

    /**
     * @param  array<mixed>  $data
     */
    private function generatePdf(array $data, string $type): string
    {
        $dir = "reports/{$this->report->team_id}";
        Storage::disk($this->reportDisk())->makeDirectory($dir);

        $filename = "{$dir}/{$this->report->id}_{$type}_{$this->report->created_at->format('Ymd_His')}.pdf";

        $html = $this->buildReportHtml($data, $type);

        $pdf = Pdf::loadHTML($html)
            ->setPaper('a4', 'landscape')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => false,
                'defaultFont' => 'sans-serif',
            ]);

        Storage::disk($this->reportDisk())->put($filename, $pdf->output());

        return $filename;
    }

    /**
     * @param  array<mixed>  $data
     */
    private function generateXlsx(array $data, string $type): string
    {
        $dir = "reports/{$this->report->team_id}";
        Storage::disk($this->reportDisk())->makeDirectory($dir);

        $filename = "{$dir}/{$this->report->id}_{$type}_{$this->report->created_at->format('Ymd_His')}.xlsx";
        $fullPath = Storage::disk($this->reportDisk())->path($filename);

        SimpleXLSXGen::fromArray($this->xlsxRows($data, $type))
            ->setTitle($this->report->title)
            ->setCompany('Mines')
            ->saveAs($fullPath);

        return $filename;
    }

    /**
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    private function xlsxRows(array $data, string $type): array
    {
        $rows = [
            ["Report: {$this->report->title}"],
            ['Generated: '.now()->toDateTimeString()],
            ["Type: {$type}"],
            [],
            $data['headers'],
        ];

        foreach ($data['rows'] as $row) {
            $rows[] = array_map(fn ($value) => $value ?? '', $row);
        }

        $rows[] = [];
        $rows[] = ['Total Records', count($data['rows'])];

        return $rows;
    }

    private function reportDisk(): string
    {
        return (string) config('reports.disk', 'local');
    }

    /**
     * @param  array<mixed>  $data
     */
    private function buildReportHtml(array $data, string $type): string
    {
        $title = e($this->report->title);
        $genDate = now()->toDateTimeString();
        $count = count($data['rows']);

        $thCells = implode('', array_map(
            fn ($h) => '<th>'.e((string) $h).'</th>',
            $data['headers']
        ));

        $bodyRows = '';
        foreach ($data['rows'] as $i => $row) {
            $bg = ($i % 2 === 0) ? '#ffffff' : '#f1f5f9';
            $tdCells = implode('', array_map(
                fn ($v) => '<td>'.e((string) ($v ?? '')).'</td>',
                $row
            ));
            $bodyRows .= "<tr style=\"background:{$bg}\">{$tdCells}</tr>";
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
  body { font-family: sans-serif; font-size: 10px; margin: 20px; color: #1e293b; }
  h1   { font-size: 16px; margin-bottom: 4px; }
  .meta { font-size: 9px; color: #64748b; margin-bottom: 12px; }
  table { width: 100%; border-collapse: collapse; }
  th { background: #1e40af; color: #fff; padding: 5px 6px; text-align: left; font-size: 9px; }
  td { padding: 4px 6px; border-bottom: 1px solid #e2e8f0; font-size: 9px; }
  .footer { margin-top: 10px; font-size: 8px; color: #94a3b8; }
</style>
</head>
<body>
<h1>{$title}</h1>
<div class="meta">Generated: {$genDate} &nbsp;|&nbsp; Report type: {$type} &nbsp;|&nbsp; Total records: {$count}</div>
<table>
  <thead><tr>{$thCells}</tr></thead>
  <tbody>{$bodyRows}</tbody>
</table>
<div class="footer">This report was automatically generated.</div>
</body>
</html>
HTML;
    }
}

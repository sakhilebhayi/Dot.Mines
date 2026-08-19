<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ComplianceReport;
use App\Models\MineArea;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ComplianceReportController extends Controller
{
    /**
     * Generate compliance report
     */
    public function generate(Request $request, MineArea $mineArea)
    {
        $this->authorize('update', $mineArea);

        $validated = $request->validate([
            'report_type' => 'required|in:environmental,safety,production,equipment,custom',
            'report_date' => 'required|date_format:Y-m-d',
        ]);

        $report = ComplianceReport::create([
            'mine_area_id' => $mineArea->id,
            'report_type' => $validated['report_type'],
            'report_date' => $validated['report_date'],
            'generated_by' => auth()->id(),
            'status' => 'draft',
            'data' => $this->generateReportData($mineArea, $validated['report_type']),
            'compliance_score' => $this->calculateCompliance($mineArea, $validated['report_type']),
        ]);

        return response()->json($report, Response::HTTP_CREATED);
    }

    /**
     * Get all compliance reports
     */
    public function index(Request $request, MineArea $mineArea)
    {
        $this->authorize('view', $mineArea);

        $reports = ComplianceReport::where('mine_area_id', $mineArea->id)
            ->when($request->has('type'), fn ($q) => $q->where('report_type', $request->type))
            ->when($request->has('status'), fn ($q) => $q->where('status', $request->status))
            ->orderBy('report_date', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json($reports);
    }

    /**
     * Get report details
     */
    public function show(ComplianceReport $report)
    {
        $this->authorize('view', $report->mineArea);

        return response()->json($report);
    }

    /**
     * Submit report for review
     */
    public function submit(ComplianceReport $report)
    {
        $this->authorize('update', $report->mineArea);

        $report->update(['status' => 'pending_review']);

        return response()->json(['message' => 'Report submitted for review', 'report' => $report]);
    }

    /**
     * Approve report
     */
    public function approve(Request $request, ComplianceReport $report)
    {
        $this->authorize('update', $report->mineArea);

        $validated = $request->validate([
            'notes' => 'nullable|string',
        ]);

        $report->update([
            'status' => 'approved',
            'issues' => $validated['notes'] ? ['approval_notes' => $validated['notes']] : $report->issues,
        ]);

        return response()->json(['message' => 'Report approved', 'report' => $report]);
    }

    /**
     * Export report
     */
    public function export(Request $request, ComplianceReport $report)
    {
        $this->authorize('view', $report->mineArea);

        $format = $request->get('format', 'pdf');

        if ($format === 'csv') {
            $csv = 'Compliance Report - '.$report->report_type."\n";
            $csv .= 'Generated: '.$report->created_at->format('Y-m-d H:i:s')."\n\n";
            $csv .= 'Report Date,'.$report->report_date."\n";
            $csv .= 'Area,'.$report->mineArea->name."\n";
            $csv .= 'Type,'.$report->report_type."\n";
            $csv .= 'Status,'.$report->status."\n";
            $csv .= 'Compliance Score,'.$report->compliance_score."\n\n";

            if ($report->issues) {
                $csv .= "Issues\n";
                foreach ($report->issues as $issue) {
                    $csv .= '- '.$issue."\n";
                }
            }

            return response($csv, Response::HTTP_OK, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => "attachment; filename=\"compliance-{$report->id}.csv\"",
            ]);
        }

        return response()->json($report);
    }

    /**
     * Get compliance summary
     */
    public function summary(Request $request, MineArea $mineArea)
    {
        $this->authorize('view', $mineArea);

        $days = $request->get('days', 90);

        $reports = ComplianceReport::where('mine_area_id', $mineArea->id)
            ->whereDate('created_at', '>=', now()->subDays($days))
            ->get();

        $grouped = $reports->groupBy('report_type')->map(function ($group) {
            return [
                'count' => $group->count(),
                'approved' => $group->where('status', 'approved')->count(),
                'avg_score' => $group->avg('compliance_score'),
            ];
        });

        return response()->json([
            'total_reports' => $reports->count(),
            'by_type' => $grouped,
            'overall_compliance' => $reports->avg('compliance_score'),
        ]);
    }

    /**
     * Generate report data
     */
    private function generateReportData(MineArea $mineArea, string $type): array
    {
        $data = [
            'area_name' => $mineArea->name,
            'report_type' => $type,
            'generated_at' => now(),
        ];

        switch ($type) {
            case 'environmental':
                $data['sensors'] = $mineArea->sensors()
                    ->where('sensor_type', 'like', '%air%')
                    ->count();
                $data['recent_readings'] = $mineArea->sensors()
                    ->first()?->readings()
                    ->latest()
                    ->limit(10)
                    ->pluck('value')
                    ->toArray() ?? [];
                break;

            case 'safety':
                $data['active_machines'] = $mineArea->machines()
                    ->where('status', 'online')
                    ->count();
                $data['alerts'] = $mineArea->team->alerts()->count();
                break;

            case 'production':
                $data['daily_production'] = $mineArea->production()
                    ->whereDate('date', today())
                    ->sum('material_tonnage');
                $data['monthly_production'] = $mineArea->production()
                    ->whereYear('date', now()->year)
                    ->whereMonth('date', now()->month)
                    ->sum('material_tonnage');
                break;

            case 'equipment':
                $data['total_machines'] = $mineArea->machines()->count();
                $data['operational_machines'] = $mineArea->machines()
                    ->where('status', 'online')
                    ->count();
                break;
        }

        return $data;
    }

    /**
     * Calculate compliance score
     */
    private function calculateCompliance(MineArea $mineArea, string $type): float
    {
        $score = 100;

        // Deduct for issues
        if ($mineArea->team->alerts()->count() > 5) {
            $score -= 10;
        }

        if ($mineArea->machines()->where('status', 'offline')->count() > 3) {
            $score -= 5;
        }

        // Check production trends
        $avgProduction = $mineArea->production()->avg('material_tonnage') ?? 0;
        $recentProduction = $mineArea->production()
            ->whereDate('date', '>=', now()->subDays(7))
            ->avg('material_tonnage') ?? 0;

        if ($recentProduction < $avgProduction * 0.7) {
            $score -= 15;
        }

        return max(0, min(100, $score));
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Jobs\GenerateReportJob;
use App\Models\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/**
 * Report API Controller
 *
 * Handles report generation and management
 */
class ReportController extends Controller
{
    /**
     * List all reports for current team
     *
     * GET /api/reports
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'status' => 'nullable|string|in:pending,completed,failed',
            'type' => 'nullable|string',
        ]);

        $query = Report::where('team_id', auth()->user()?->current_team_id);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        $perPage = $request->input('per_page', 15);
        $reports = $query->with('generatedBy')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'data' => $reports->items(),
            'pagination' => [
                'total' => $reports->total(),
                'per_page' => $reports->perPage(),
                'current_page' => $reports->currentPage(),
                'last_page' => $reports->lastPage(),
            ],
        ]);
    }

    /**
     * Get a single report
     *
     * GET /api/reports/{id}
     */
    public function show(Report $report): JsonResponse
    {
        return response()->json([
            'data' => $report->load('generatedBy'),
        ]);
    }

    /**
     * Generate a new report
     *
     * POST /api/reports/generate
     */
    public function generate(Request $request): JsonResponse
    {
        $this->authorize('generate', Report::class);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'required|string|in:truck_sensors,tire_condition,load_cycle,fuel,engine_parts,maintenance,custom',
            'format' => 'nullable|string|in:pdf,csv,xlsx',
            'filters' => 'nullable|json',
        ]);

        $validated['team_id'] = auth()->user()?->current_team_id;
        $validated['status'] = 'pending';
        $validated['generated_by'] = auth()->id();
        $validated['format'] = $request->input('format', 'pdf');

        $report = Report::create($validated);

        // NOTE: this endpoint's 'type' values (truck_sensors, tire_condition,
        // load_cycle, fuel, engine_parts, maintenance, custom) predate and
        // don't match the 6 types the web UI (ReportGenerator) actually
        // offers and ReportDataService knows how to build (production,
        // fleet_utilization, maintenance_schedule, fuel_consumption,
        // material_tracking, downtime_analysis) -- this API isn't called
        // from anywhere in the app today. Dispatching it here means a
        // report created through this endpoint fails cleanly with a real
        // error_message instead of hanging at 'pending' forever, but none
        // of these type values will actually generate a file until
        // ReportDataService is extended to support them.
        GenerateReportJob::dispatch($report);

        return response()->json([
            'data' => $report,
            'message' => 'Report generation started',
        ], Response::HTTP_CREATED);
    }

    /**
     * Download report file
     *
     * GET /api/reports/{id}/download
     */
    public function download(Report $report): \Symfony\Component\HttpFoundation\Response
    {
        $this->authorize('view', $report);

        if (! $report->isAvailable()) {
            return response()->json([
                'message' => 'Report is not available for download',
            ], Response::HTTP_NOT_FOUND);
        }

        // Use Storage APIs where possible. Normalize and validate the stored path.
        $disk = config('filesystems.default');
        $relative = ltrim($report->file_path ?? '', '/');

        // Disallow traversal and require a safe prefix
        if (strpos($relative, '..') !== false) {
            return response()->json(['message' => 'Report file not found'], Response::HTTP_NOT_FOUND);
        }

        $allowedPrefix = 'reports/';
        if (! str_starts_with($relative, $allowedPrefix)) {
            // If stored paths don't use a prefix, this check can be adjusted.
            return response()->json(['message' => 'Report file not found'], Response::HTTP_NOT_FOUND);
        }

        if (! Storage::disk($disk)->exists($relative)) {
            return response()->json(['message' => 'Report file not found'], Response::HTTP_NOT_FOUND);
        }

        // Authorize was already called earlier; use Storage::download to serve safely and add security headers.
        $filename = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $report->title).'.'.$report->format;
        $mime = Storage::disk($disk)->mimeType($relative) ?? 'application/octet-stream';

        $securityHeaders = [
            'Content-Security-Policy' => "default-src 'none';",
            'X-Content-Type-Options' => 'nosniff',
        ];

        return Storage::disk($disk)->download($relative, $filename, array_merge($securityHeaders, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]));
    }

    /**
     * Delete a report
     *
     * DELETE /api/reports/{id}
     */
    public function destroy(Report $report): JsonResponse
    {
        $this->authorize('delete', $report);

        // Delete file if exists
        if (($report->file_path !== null && $report->file_path !== '' && $report->file_path !== '0')) {
            $disk = config('filesystems.default');
            $relative = ltrim($report->file_path, '/');
            if (! (strpos($relative, '..') !== false) && str_starts_with($relative, 'reports/') && Storage::disk($disk)->exists($relative)) {
                Storage::disk($disk)->delete($relative);
            }
        }

        $report->delete();

        return response()->json([
            'message' => 'Report deleted successfully',
        ]);
    }

    /**
     * Get available report templates
     *
     * GET /api/reports/templates
     */
    public function templates(): JsonResponse
    {
        $templates = [
            [
                'type' => 'truck_sensors',
                'name' => 'Truck Sensors Report',
                'description' => 'Engine RPM, temperature, and diagnostic data',
                'formats' => ['pdf', 'csv', 'xlsx'],
            ],
            [
                'type' => 'tire_condition',
                'name' => 'Tire Condition Report',
                'description' => 'Tire tread depth, pressure, and wear patterns',
                'formats' => ['pdf', 'csv', 'xlsx'],
            ],
            [
                'type' => 'load_cycle',
                'name' => 'Load & Cycle Report',
                'description' => 'Load weights and operational cycles',
                'formats' => ['pdf', 'csv', 'xlsx'],
            ],
            [
                'type' => 'fuel',
                'name' => 'Fuel Consumption Report',
                'description' => 'Fuel usage, costs, and consumption rates',
                'formats' => ['pdf', 'csv', 'xlsx'],
            ],
            [
                'type' => 'engine_parts',
                'name' => 'Engine Parts Report',
                'description' => 'Oil status, filter replacement, fluid levels',
                'formats' => ['pdf', 'csv', 'xlsx'],
            ],
            [
                'type' => 'maintenance',
                'name' => 'Maintenance Recommendations',
                'description' => 'Overdue and upcoming maintenance schedules',
                'formats' => ['pdf', 'csv', 'xlsx'],
            ],
        ];

        return response()->json([
            'data' => $templates,
        ]);
    }

    /**
     * Get report statistics
     *
     * GET /api/reports/stats
     */
    public function stats(): JsonResponse
    {
        $stats = [
            'total' => Report::where('team_id', auth()->user()?->current_team_id)->count(),
            'pending' => Report::where('team_id', auth()->user()?->current_team_id)
                ->where('status', 'pending')
                ->count(),
            'completed' => Report::where('team_id', auth()->user()?->current_team_id)
                ->where('status', 'completed')
                ->count(),
            'failed' => Report::where('team_id', auth()->user()?->current_team_id)
                ->where('status', 'failed')
                ->count(),
        ];

        return response()->json([
            'data' => $stats,
        ]);
    }
}

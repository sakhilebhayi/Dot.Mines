<?php

namespace App\Http\Controllers\Api;

use App\Models\Report;
use App\Support\Reports\ReportGeneration;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
    public function index(Request $request): mixed
    {
        $validated = $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'status' => 'nullable|string|in:pending,processing,completed,failed',
            'type' => 'nullable|string',
        ]);

        $query = Report::where('team_id', Auth::user()->current_team_id);

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
    public function show(Report $report): mixed
    {
        $this->authorize('view', $report);

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
            'type' => 'required|string|in:'.implode(',', ReportGeneration::supportedTypes()),
            'format' => 'nullable|string|in:pdf,csv,xlsx',
            'filters' => 'nullable',
        ]);

        $filters = ReportGeneration::normalizeFilters($request->input('filters'));

        $validated['team_id'] = Auth::user()->current_team_id;
        $validated['status'] = 'pending';
        $validated['generated_by'] = Auth::id();
        $validated['format'] = $request->input('format', 'pdf');
        $validated['filters'] = $filters;

        $report = Report::create($validated);

        ReportGeneration::dispatch($report);

        return response()->json([
            'data' => $report,
            'message' => 'Report generation started',
        ], Response::HTTP_ACCEPTED);
    }

    /**
     * Download report file
     *
     * GET /api/reports/{id}/download
     */
    public function download(Report $report): JsonResponse|StreamedResponse
    {
        $this->authorize('view', $report);

        if (! $report->isAvailable()) {
            return response()->json([
                'message' => 'Report is not available for download',
            ], Response::HTTP_NOT_FOUND);
        }

        // Use Storage APIs where possible. Normalize and validate the stored path.
        $disk = config('reports.disk', 'local');
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
        /** @var FilesystemAdapter $adapter */
        $adapter = Storage::disk($disk);
        $mime = $adapter->mimeType($relative) ?? 'application/octet-stream';

        $securityHeaders = [
            'Content-Security-Policy' => "default-src 'none';",
            'X-Content-Type-Options' => 'nosniff',
        ];

        return $adapter->download($relative, $filename, array_merge($securityHeaders, [
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
        if ($report->file_path) {
            $disk = config('reports.disk', 'local');
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
            'total' => Report::where('team_id', Auth::user()->current_team_id)->count(),
            'pending' => Report::where('team_id', Auth::user()->current_team_id)
                ->where('status', 'pending')
                ->count(),
            'completed' => Report::where('team_id', Auth::user()->current_team_id)
                ->where('status', 'completed')
                ->count(),
            'failed' => Report::where('team_id', Auth::user()->current_team_id)
                ->where('status', 'failed')
                ->count(),
        ];

        return response()->json([
            'data' => $stats,
        ]);
    }
}

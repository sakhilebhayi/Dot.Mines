<?php

namespace App\Livewire;

use App\Models\BellEquipment;
use App\Models\BellEquipmentCautionCode;
use App\Models\BellEquipmentDailyKpi;
use App\Models\FeedPost;
use App\Models\Geofence;
use App\Models\Machine;
use App\Models\MineArea;
use App\Models\Report;
use App\Models\User;
use App\Services\Integration\BellTeamInsightsService;
use App\Support\Reports\ReportGeneration;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Reports extends Component
{
    use WithPagination;

    // ── Generated Reports tab ──────────────────────────────────────────────────
    public string $search = '';

    public string $sortBy = 'created_at';

    public string $sortDirection = 'desc';

    public string $selectedType = 'all';

    public string $selectedStatus = 'all';

    public string $selectedMineAreaId = '';

    public string $selectedGeofenceId = '';

    public string $selectedMachineId = '';

    /** @var Collection<int, mixed>|null */
    public ?Collection $machinesList = null;

    public bool $showDeleteConfirm = false;

    public ?int $deleteReportId = null;

    // ── Tab navigation ─────────────────────────────────────────────────────────
    public string $activeTab = 'generated';

    // ── 3.1 Shift Reports ──────────────────────────────────────────────────────
    public string $shiftReportShift = '';

    public string $shiftReportDate = '';

    // ── 3.2 Breakdown Analytics ────────────────────────────────────────────────
    public string $breakdownDateFrom = '';

    public string $breakdownDateTo = '';

    // ── 3.3 Production Analytics ───────────────────────────────────────────────
    public string $productionShift = '';

    public string $productionDateFrom = '';

    public string $productionDateTo = '';

    public string $productionMineAreaId = '';

    // ── 3.4 Bell Operations ───────────────────────────────────────────────────
    public string $bellReportMonth = '';

    // ── 3.5 Historical Log ─────────────────────────────────────────────────────
    public string $historySearch = '';

    public string $historyCategory = '';

    public string $historyDateFrom = '';

    public string $historyDateTo = '';

    public string $historyAuthorId = '';

    public string $historyShift = '';

    public string $historyApproval = '';

    /** @var array<string, string> */
    protected $reportTypes = [
        'production' => 'Production Summary',
        'fleet_utilization' => 'Fleet Utilization',
        'maintenance_schedule' => 'Maintenance Schedule',
        'fuel_consumption' => 'Fuel Consumption',
        'material_tracking' => 'Material Tracking',
        'downtime_analysis' => 'Downtime Analysis',
    ];

    public function mount(): void
    {
        $this->sortBy = 'created_at';
        $this->sortDirection = 'desc';
        $this->shiftReportDate = now()->format('Y-m-d');
        $this->breakdownDateFrom = now()->subDays(29)->format('Y-m-d');
        $this->breakdownDateTo = now()->format('Y-m-d');
        $this->productionDateFrom = now()->subDays(13)->format('Y-m-d');
        $this->productionDateTo = now()->format('Y-m-d');
        $this->bellReportMonth = now()->format('Y-m');
    }

    // ── Pagination reset on filter change ──────────────────────────────────────
    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingHistorySearch(): void
    {
        $this->resetPage('history_page');
    }

    // ── Generated Reports ──────────────────────────────────────────────────────

    public function getReports(): mixed
    {
        $team = Auth::user()->currentTeam;

        if (! $team) {
            return collect();
        }

        $searchTerm = trim($this->search);

        return Report::where('team_id', $team->id)
            ->when($searchTerm, function ($query) use ($searchTerm) {
                $query->where(function ($searchQuery) use ($searchTerm) {
                    $searchQuery->where('title', 'like', '%'.$searchTerm.'%')
                        ->orWhere('filters->description', 'like', '%'.$searchTerm.'%');
                });
            })
            ->when($this->selectedMineAreaId, function ($query) {
                $query->where('filters->mine_area_id', $this->selectedMineAreaId);
            })
            ->when($this->selectedGeofenceId, function ($query) {
                $selectedGeofenceId = $this->selectedGeofenceId;

                $query->where(function ($geofenceQuery) use ($selectedGeofenceId) {
                    $geofenceQuery->where('filters->geofence_id', $selectedGeofenceId)
                        ->orWhereJsonContains('filters->geofence_ids', $selectedGeofenceId)
                        ->orWhereJsonContains('filters->geofence_ids', (int) $selectedGeofenceId);
                });
            })
            ->when($this->selectedMachineId, function ($query) {
                $selectedMachineId = $this->selectedMachineId;

                $query->where(function ($machineQuery) use ($selectedMachineId) {
                    $machineQuery->where('filters->machine_id', $selectedMachineId)
                        ->orWhereJsonContains('filters->machine_ids', $selectedMachineId)
                        ->orWhereJsonContains('filters->machine_ids', (int) $selectedMachineId);
                });
            })
            ->when($this->selectedType !== 'all', function ($query) {
                $query->where('type', $this->selectedType);
            })
            ->when($this->selectedStatus !== 'all', function ($query) {
                $query->where('status', $this->selectedStatus);
            })
            ->orderBy($this->sortBy, $this->sortDirection)
            ->paginate(10);
    }

    public function refreshReports(): void
    {
        // wire:poll keeps the generated reports table current while background jobs run.
    }

    public function setSortBy(string $column): void
    {
        $allowed = ['title', 'created_at', 'type'];
        if (! in_array($column, $allowed, true)) {
            return;
        }
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
    }

    public function deleteReport(int $reportId): void
    {
        $team = Auth::user()->currentTeam;
        $report = Report::where('team_id', $team->id)->find($reportId);

        if (! $report) {
            $this->dispatch('notify', type: 'error', message: 'Report not found or access denied');
            $this->showDeleteConfirm = false;

            return;
        }

        try {
            /** @var FilesystemAdapter $disk */
            $disk = Storage::disk(config('reports.disk', 'local'));

            if ($report->file_path && $disk->exists($report->file_path)) {
                $disk->delete($report->file_path);
            }

            $report->delete();

            Log::info('User deleted report', [
                'user_id' => Auth::id(),
                'report_id' => $reportId,
                'report_type' => $report->type,
            ]);

            $this->dispatch('notify', type: 'success', message: 'Report deleted successfully');
        } catch (\Exception $e) {
            Log::error('Failed to delete report', [
                'user_id' => Auth::id(),
                'report_id' => $reportId,
                'error' => $e->getMessage(),
            ]);

            $this->dispatch('notify', type: 'error', message: 'Failed to delete report');
        }

        $this->showDeleteConfirm = false;
        $this->deleteReportId = null;
    }

    public function confirmDelete(int $reportId): void
    {
        $this->deleteReportId = $reportId;
        $this->showDeleteConfirm = true;
    }

    public function cancelDelete(): void
    {
        $this->showDeleteConfirm = false;
        $this->deleteReportId = null;
    }

    public function downloadReport(int $reportId): mixed
    {
        $team = Auth::user()->currentTeam;
        $report = Report::where('team_id', $team->id)->find($reportId);

        if (! $report) {
            $this->dispatch('notify', type: 'error', message: 'Report not found or access denied');

            return null;
        }

        if ($report->status !== 'completed') {
            $this->dispatch('notify', type: 'warning', message: 'Report is not ready for download');

            return null;
        }

        if ($report->file_path && ! str_contains($report->file_path, '..')) {
            /** @var FilesystemAdapter $disk */
            $disk = Storage::disk(config('reports.disk', 'local'));

            if ($disk->exists($report->file_path)) {
                Log::info('User downloaded report', [
                    'user_id' => Auth::id(),
                    'report_id' => $reportId,
                ]);

                return $disk->download($report->file_path, $report->title.'.'.$report->format);
            }
        }

        $this->dispatch('notify', type: 'error', message: 'Report file not found');

        return null;
    }

    public function retryReport(int $reportId): void
    {
        $team = Auth::user()->currentTeam;
        /** @var Report|null $report */
        $report = Report::where('team_id', $team->id)->find($reportId);

        if (! $report) {
            $this->dispatch('notify', type: 'error', message: 'Report not found or access denied');

            return;
        }

        $report->update([
            'status' => 'pending',
            'file_path' => null,
            'file_size' => null,
            'generated_at' => null,
        ]);

        ReportGeneration::dispatch($report);

        $this->dispatch('notify', type: 'success', message: 'Report generation restarted');
    }

    public function hasInFlightReports(): bool
    {
        $team = Auth::user()->currentTeam;

        if (! $team) {
            return false;
        }

        return Report::where('team_id', $team->id)
            ->whereIn('status', ['pending', 'processing'])
            ->exists();
    }

    // ── 3.1 Shift Reports ──────────────────────────────────────────────────────

    /**
     * @return array<mixed>
     */
    public function getShiftReportData(): array
    {
        if (! $this->shiftReportShift || ! $this->shiftReportDate) {
            return [];
        }

        try {
            $date = Carbon::parse($this->shiftReportDate);
        } catch (\Exception $e) {
            return [];
        }

        $posts = FeedPost::where('shift', $this->shiftReportShift)
            ->whereDate('created_at', $date)
            ->with(['approval'])
            ->get();

        $categoryData = [];
        foreach (FeedPost::CATEGORIES as $cat) {
            $categoryData[$cat] = $posts->where('category', $cat)->count();
        }

        $unresolvedBreakdowns = $posts->where('category', 'breakdown')->filter(
            fn ($p) => ! $p->approval || $p->approval->status !== 'approved'
        )->count();

        $topPosts = $posts->sortByDesc(
            fn ($p) => $p->like_count + $p->comment_count
        )->take(5)->values()->map(fn ($p) => [
            'id' => $p->id,
            'body' => Str::limit($p->body, 80),
            'category' => $p->category,
            'likes' => $p->like_count,
            'comments' => $p->comment_count,
            'acks' => $p->acknowledgement_count,
        ])->toArray();

        $approvalItems = $posts->filter(fn ($p) => $p->approval);
        $approvalStats = [
            'approved' => $approvalItems->filter(fn ($p) => $p->approval->status === 'approved')->count(),
            'rejected' => $approvalItems->filter(fn ($p) => $p->approval->status === 'rejected')->count(),
            'pending' => $approvalItems->filter(fn ($p) => $p->approval->status === 'pending')->count(),
        ];

        return [
            'total' => $posts->count(),
            'by_category' => $categoryData,
            'total_likes' => $posts->sum('like_count'),
            'total_comments' => $posts->sum('comment_count'),
            'total_acks' => $posts->sum('acknowledgement_count'),
            'unresolved_breakdowns' => $unresolvedBreakdowns,
            'top_posts' => $topPosts,
            'approval_stats' => $approvalStats,
        ];
    }

    public function exportShiftReportCsv(): mixed
    {
        if (! $this->shiftReportShift || ! $this->shiftReportDate) {
            return null;
        }

        $data = $this->getShiftReportData();
        if (empty($data)) {
            return null;
        }

        $shift = $this->shiftReportShift;
        $date = $this->shiftReportDate;

        return response()->streamDownload(function () use ($data, $shift, $date) {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return null;
            }

            fputcsv($handle, ['Shift Report Summary']);
            fputcsv($handle, ['Shift', $shift, 'Date', $date]);
            fputcsv($handle, []);

            fputcsv($handle, ['Category Breakdown']);
            fputcsv($handle, ['Category', 'Post Count']);
            foreach ($data['by_category'] as $category => $count) {
                fputcsv($handle, [ucfirst(str_replace('_', ' ', $category)), $count]);
            }
            fputcsv($handle, []);

            fputcsv($handle, ['Engagement Metrics']);
            fputcsv($handle, ['Metric', 'Value']);
            fputcsv($handle, ['Total Posts', $data['total']]);
            fputcsv($handle, ['Total Likes', $data['total_likes']]);
            fputcsv($handle, ['Total Comments', $data['total_comments']]);
            fputcsv($handle, ['Total Acknowledgements', $data['total_acks']]);
            fputcsv($handle, ['Unresolved Breakdowns', $data['unresolved_breakdowns']]);
            fputcsv($handle, []);

            fputcsv($handle, ['Approval Statistics']);
            fputcsv($handle, ['Status', 'Count']);
            foreach ($data['approval_stats'] as $status => $count) {
                fputcsv($handle, [ucfirst($status), $count]);
            }
            fputcsv($handle, []);

            fputcsv($handle, ['Top Posts by Engagement']);
            fputcsv($handle, ['Body', 'Category', 'Likes', 'Comments', 'Acknowledgements']);
            foreach ($data['top_posts'] as $post) {
                fputcsv($handle, [$post['body'], $post['category'], $post['likes'], $post['comments'], $post['acks']]);
            }

            fclose($handle);
        }, "shift-report-{$shift}-{$date}.csv", ['Content-Type' => 'text/csv']);
    }

    // ── 3.2 Machine Breakdown Analytics ───────────────────────────────────────

    /**
     * @return array<mixed>
     */
    public function getBreakdownData(): array
    {
        $posts = FeedPost::where('category', 'breakdown')
            ->with(['mineArea:id,name', 'approval'])
            ->when($this->breakdownDateFrom, fn ($q) => $q->whereDate('created_at', '>=', $this->breakdownDateFrom))
            ->when($this->breakdownDateTo, fn ($q) => $q->whereDate('created_at', '<=', $this->breakdownDateTo))
            ->orderBy('created_at')
            ->get();

        // Frequency per machine
        $byMachine = $posts
            ->filter(fn ($p) => ! empty($p->meta['machine_id']))
            ->groupBy(fn ($p) => $p->meta['machine_id'])
            ->map(fn ($g) => $g->count())
            ->sortByDesc(fn ($v) => $v);

        // Frequency per section
        $bySection = $posts
            ->filter(fn ($p) => $p->mine_area_id)
            ->groupBy(fn ($p) => $p->mineArea?->name ?? 'Unknown')
            ->map(fn ($g) => $g->count())
            ->sortByDesc(fn ($v) => $v);

        // MTTR: diff from breakdown post created_at → approval reviewed_at
        $mttrValues = $posts
            ->filter(fn ($p) => $p->approval && $p->approval->status === 'approved' && $p->approval->reviewed_at)
            ->map(fn ($p) => max(0, $p->created_at->diffInMinutes($p->approval->reviewed_at)));

        $avgMttr = $mttrValues->isNotEmpty() ? round($mttrValues->avg()) : null;

        return [
            'total' => $posts->count(),
            'resolved_count' => $posts->filter(fn ($p) => $p->approval && $p->approval->status === 'approved')->count(),
            'unresolved_count' => $posts->filter(fn ($p) => ! $p->approval || $p->approval->status !== 'approved')->count(),
            'avg_mttr_minutes' => $avgMttr,
            'by_machine' => $byMachine->toArray(),
            'by_section' => $bySection->toArray(),
            'chart_labels' => $byMachine->keys()->values()->toArray(),
            'chart_values' => $byMachine->values()->toArray(),
            'section_labels' => $bySection->keys()->values()->toArray(),
            'section_values' => $bySection->values()->toArray(),
        ];
    }

    // ── 3.3 Production Analytics ───────────────────────────────────────────────

    /**
     * @return array<mixed>
     */
    public function getProductionData(): array
    {
        $posts = FeedPost::where('category', 'shift_update')
            ->with(['mineArea:id,name'])
            ->when($this->productionShift, fn ($q) => $q->where('shift', $this->productionShift))
            ->when($this->productionDateFrom, fn ($q) => $q->whereDate('created_at', '>=', $this->productionDateFrom))
            ->when($this->productionDateTo, fn ($q) => $q->whereDate('created_at', '<=', $this->productionDateTo))
            ->when($this->productionMineAreaId, fn ($q) => $q->where('mine_area_id', $this->productionMineAreaId))
            ->orderBy('created_at')
            ->get();

        // Per-shift aggregates
        $byShift = [];
        foreach (FeedPost::SHIFTS as $shift) {
            $sp = $posts->where('shift', $shift);
            $lph = $sp->filter(fn ($p) => isset($p->meta['loads_per_hour']))->map(fn ($p) => (float) $p->meta['loads_per_hour']);
            $ton = $sp->filter(fn ($p) => isset($p->meta['tonnage']))->map(fn ($p) => (float) $p->meta['tonnage']);
            $byShift[$shift] = [
                'count' => $sp->count(),
                'avg_loads_per_hour' => $lph->isNotEmpty() ? round($lph->avg(), 2) : null,
                'total_tonnage' => $ton->isNotEmpty() ? round($ton->sum(), 2) : null,
            ];
        }

        // Week-on-week
        $cwStart = now()->startOfWeek();
        $lwStart = now()->subWeek()->startOfWeek();
        $lwEnd = now()->subWeek()->endOfWeek();

        $cwPosts = FeedPost::where('category', 'shift_update')->whereDate('created_at', '>=', $cwStart)->get();
        $lwPosts = FeedPost::where('category', 'shift_update')->whereDate('created_at', '>=', $lwStart)->whereDate('created_at', '<=', $lwEnd)->get();

        $cwLph = $cwPosts->filter(fn ($p) => isset($p->meta['loads_per_hour']))->avg(fn ($p) => (float) $p->meta['loads_per_hour']) ?? 0;
        $lwLph = $lwPosts->filter(fn ($p) => isset($p->meta['loads_per_hour']))->avg(fn ($p) => (float) $p->meta['loads_per_hour']) ?? 0;

        // Month-on-month
        $cmStart = now()->startOfMonth();
        $lmStart = now()->subMonth()->startOfMonth();
        $lmEnd = now()->subMonth()->endOfMonth();

        $cmPosts = FeedPost::where('category', 'shift_update')->whereDate('created_at', '>=', $cmStart)->get();
        $lmPosts = FeedPost::where('category', 'shift_update')->whereDate('created_at', '>=', $lmStart)->whereDate('created_at', '<=', $lmEnd)->get();

        $cmLph = $cmPosts->filter(fn ($p) => isset($p->meta['loads_per_hour']))->avg(fn ($p) => (float) $p->meta['loads_per_hour']) ?? 0;
        $lmLph = $lmPosts->filter(fn ($p) => isset($p->meta['loads_per_hour']))->avg(fn ($p) => (float) $p->meta['loads_per_hour']) ?? 0;

        // Daily timeline for chart
        $rangeStart = $this->productionDateFrom ? Carbon::parse($this->productionDateFrom) : now()->subDays(13);
        $rangeEnd = $this->productionDateTo ? Carbon::parse($this->productionDateTo) : now();

        $timelinePosts = FeedPost::where('category', 'shift_update')
            ->when($this->productionShift, fn ($q) => $q->where('shift', $this->productionShift))
            ->when($this->productionMineAreaId, fn ($q) => $q->where('mine_area_id', $this->productionMineAreaId))
            ->whereDate('created_at', '>=', $rangeStart)
            ->whereDate('created_at', '<=', $rangeEnd)
            ->orderBy('created_at')
            ->get();

        $timelineLabels = [];
        $timelineValues = [];
        $day = $rangeStart->copy()->startOfDay();
        while ($day->lte($rangeEnd)) {
            $dp = $timelinePosts->filter(fn ($p) => $p->created_at->isSameDay($day));
            $lph = $dp->filter(fn ($p) => isset($p->meta['loads_per_hour']))->avg(fn ($p) => (float) $p->meta['loads_per_hour']);
            $timelineLabels[] = $day->format('M d');
            $timelineValues[] = $lph ? round($lph, 1) : 0;
            $day->addDay();
        }

        return [
            'total' => $posts->count(),
            'by_shift' => $byShift,
            'wow_current' => round($cwLph, 2),
            'wow_last' => round($lwLph, 2),
            'wow_change' => $lwLph > 0 ? round((($cwLph - $lwLph) / $lwLph) * 100, 1) : null,
            'mom_current' => round($cmLph, 2),
            'mom_last' => round($lmLph, 2),
            'mom_change' => $lmLph > 0 ? round((($cmLph - $lmLph) / $lmLph) * 100, 1) : null,
            'timeline_labels' => $timelineLabels,
            'timeline_values' => $timelineValues,
        ];
    }

    // ── 3.4 Bell Operations ───────────────────────────────────────────────────

    /** @return array<string, mixed> */
    public function getBellData(): array
    {
        $team = Auth::user()->currentTeam;

        if (! $team) {
            return [];
        }

        $parsed = $this->bellReportMonth
            ? Carbon::createFromFormat('Y-m', $this->bellReportMonth)
            : null;
        $monthStart = $parsed instanceof Carbon ? $parsed->startOfMonth() : now()->startOfMonth();

        $insights = app(BellTeamInsightsService::class);
        $current = $insights->getTeamOverview($team->id, Carbon::parse($monthStart));

        // Monthly comparison: May, June, July 2026
        $comparison = [];
        foreach (['2026-05', '2026-06', '2026-07'] as $ym) {
            $mParsed = Carbon::createFromFormat('Y-m', $ym);
            $mStart = $mParsed instanceof Carbon ? $mParsed->startOfMonth() : now()->startOfMonth();
            $data = $insights->getTeamOverview($team->id, Carbon::parse($mStart));
            $comparison[$ym] = $data['totals'] ?? [];
        }

        // Fleet-wide caution code frequency (active faults across all linked machines)
        $linkedKeys = BellEquipment::whereNotNull('machine_id')
            ->pluck('equipment_key');
        $cautionFrequency = BellEquipmentCautionCode::whereIn('equipment_key', $linkedKeys)
            ->select('fault_code', 'fault_description', 'severity')
            ->selectRaw('COUNT(*) as occurrences')
            ->selectRaw('SUM(CASE WHEN is_active THEN 1 ELSE 0 END) as active_count')
            ->groupBy('fault_code', 'fault_description', 'severity')
            ->orderByDesc('occurrences')
            ->limit(20)
            ->get()
            ->toArray();

        // Per-machine KPI aggregates (all time from May)
        $mayStart = Carbon::parse('2026-05-01')->startOfDay();
        $kpiSummary = BellEquipmentDailyKpi::whereIn('equipment_key', $linkedKeys)
            ->where('kpi_date', '>=', $mayStart)
            ->selectRaw('equipment_key, SUM(loads_moved) as total_loads, SUM(payload_moved) as total_payload, SUM(fuel_used) as total_fuel, SUM(operating_hours) as total_hours, AVG(utilization_percent) as avg_utilization')
            ->groupBy('equipment_key')
            ->get()
            ->keyBy('equipment_key')
            ->toArray();

        return array_merge($current, [
            'monthly_comparison' => $comparison,
            'caution_frequency' => $cautionFrequency,
            'kpi_summary' => $kpiSummary,
        ]);
    }

    /**
     * Export raw Bell telemetry KPIs as CSV download.
     */
    public function exportBellCsv(): StreamedResponse
    {
        $team = Auth::user()->currentTeam;
        $mayStart = Carbon::parse('2026-05-01')->startOfDay();

        $linkedKeys = BellEquipment::whereNotNull('machine_id')->pluck('equipment_key');

        $rows = BellEquipmentDailyKpi::whereIn('equipment_key', $linkedKeys)
            ->where('kpi_date', '>=', $mayStart)
            ->with('equipment:equipment_key,equipment_id,model,serial_number')
            ->orderBy('kpi_date')
            ->orderBy('equipment_key')
            ->get();

        $filename = 'bell-telemetry-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fputcsv($out, ['Date', 'Equipment ID', 'Model', 'Serial', 'Loads', 'Payload (t)', 'Fuel Used (L)', 'Operating Hours', 'Idle Hours', 'Distance (km)', 'Utilization %']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row->kpi_date instanceof Carbon ? $row->kpi_date->toDateString() : $row->kpi_date,
                    $row->equipment->equipment_id ?? '',
                    $row->equipment->model ?? '',
                    $row->equipment->serial_number ?? '',
                    $row->loads_moved,
                    $row->payload_moved,
                    $row->fuel_used,
                    $row->operating_hours,
                    $row->idle_hours,
                    $row->distance_travelled,
                    $row->utilization_percent,
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    // ── 3.5 Historical Log ─────────────────────────────────────────────────────

    public function getHistory(): mixed
    {
        $term = trim($this->historySearch);

        return FeedPost::withTrashed()
            ->with(['author:id,name', 'mineArea:id,name', 'approval'])
            ->when($term, function ($query) use ($term) {
                $safe = '%'.addcslashes($term, '%_\\').'%';
                $query->where(function ($q) use ($safe) {
                    $q->whereRaw('body ILIKE ?', [$safe])
                        ->orWhereHas('allComments', fn ($c) => $c->whereRaw('body ILIKE ?', [$safe]));
                });
            })
            ->when($this->historyCategory, fn ($q) => $q->where('category', $this->historyCategory))
            ->when($this->historyDateFrom, fn ($q) => $q->whereDate('created_at', '>=', $this->historyDateFrom))
            ->when($this->historyDateTo, fn ($q) => $q->whereDate('created_at', '<=', $this->historyDateTo))
            ->when($this->historyAuthorId, fn ($q) => $q->where('author_id', $this->historyAuthorId))
            ->when($this->historyShift, fn ($q) => $q->where('shift', $this->historyShift))
            ->when($this->historyApproval, function ($query) {
                if ($this->historyApproval === 'none') {
                    $query->doesntHave('approval');
                } else {
                    $query->whereHas('approval', fn ($q) => $q->where('status', $this->historyApproval));
                }
            })
            ->orderByDesc('created_at')
            ->paginate(20, ['*'], 'history_page');
    }

    // ── Render ─────────────────────────────────────────────────────────────────

    public function render(): View
    {
        $team = Auth::user()->currentTeam;

        $mineAreas = $team ? MineArea::where('team_id', $team->id)->get() : collect();
        $geofences = $team ? Geofence::where('team_id', $team->id)->get() : collect();
        $this->machinesList = $team ? Machine::where('team_id', $team->id)->select(['id', 'name'])->get() : collect();
        $teamUsers = $team ? User::whereHas('teams', fn ($q) => $q->where('teams.id', $team->id))->select(['id', 'name'])->orderBy('name')->get() : collect();

        return view('livewire.reports', [
            'reports' => $this->activeTab === 'generated' ? $this->getReports() : collect(),
            'reportTypes' => $this->reportTypes,
            'hasInFlightReports' => $this->activeTab === 'generated' ? $this->hasInFlightReports() : false,
            'mineAreas' => $mineAreas,
            'geofences' => $geofences,
            'machinesList' => $this->machinesList,
            'shiftReportData' => $this->activeTab === 'shift_reports' ? $this->getShiftReportData() : [],
            'breakdownData' => $this->activeTab === 'breakdown' ? $this->getBreakdownData() : [],
            'productionData' => $this->activeTab === 'production' ? $this->getProductionData() : [],
            'bellData' => $this->activeTab === 'bell' ? $this->getBellData() : [],
            'history' => $this->activeTab === 'history' ? $this->getHistory() : null,
            'teamUsers' => $teamUsers,
        ]);
    }
}

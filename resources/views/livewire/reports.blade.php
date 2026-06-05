<div class="min-h-screen bg-slate-900 p-6">
    <div class="max-w-7xl mx-auto">

        <!-- Header -->
        <div class="mb-6">
            <h1 class="text-4xl font-bold text-white mb-2">Reports & Analytics</h1>
            <p class="text-slate-400">Generate reports and explore feed analytics for your mine operations</p>
        </div>

        <!-- Tab Navigation -->
        <div class="flex gap-1 bg-slate-800 p-1 rounded-xl border border-slate-700 mb-6 overflow-x-auto">
            @foreach([
                ['generated',    'Generated Reports', 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
                ['shift_reports','Shift Reports',     'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
                ['breakdown',    'Breakdown Analytics','M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z'],
                ['production',   'Production Analytics','M13 7h8m0 0v8m0-8l-8 8-4-4-6 6'],
                ['history',      'Historical Log',    'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
            ] as [$tab, $label, $icon])
            <button
                wire:click="$set('activeTab', '{{ $tab }}')"
                class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition-all
                    {{ $activeTab === $tab ? 'bg-blue-600 text-white shadow' : 'text-slate-400 hover:text-white hover:bg-slate-700' }}"
            >
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $icon }}"/>
                </svg>
                {{ $label }}
            </button>
            @endforeach
        </div>

        {{-- ═══════════════════════════════════════════════════════════════════
             TAB: Generated Reports
        ═══════════════════════════════════════════════════════════════════ --}}
        @if($activeTab === 'generated')

        <!-- Actions Bar -->
        <div class="bg-slate-800 rounded-lg p-6 mb-6 border border-slate-700">
            @if (session()->has('message'))
                <div class="mb-4 rounded-lg border border-blue-500/40 bg-blue-500/10 px-4 py-3 text-sm text-blue-200">
                    {{ session('message') }}
                </div>
            @endif

            <div class="flex items-center justify-between gap-4 mb-4">
                @php
                    $generateRoute = Route::has('report-generator') ? route('report-generator') : (Route::has('reports.generate') ? route('reports.generate') : '#');
                @endphp
                <a href="{{ $generateRoute }}" wire:navigate class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Generate Report
                </a>
            </div>

            <!-- Advanced Filters -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                <div>
                    <label class="block text-sm text-slate-400 mb-2">Mine Area</label>
                    <select wire:model.live="selectedMineAreaId" class="w-full bg-slate-700 text-white px-4 py-2 rounded-lg border border-slate-600 focus:border-blue-500 focus:outline-none">
                        <option value="">All Mine Areas</option>
                        @foreach($mineAreas ?? [] as $area)
                            <option value="{{ $area->id }}">{{ $area->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm text-slate-400 mb-2">Geofence</label>
                    <select wire:model.live="selectedGeofenceId" class="w-full bg-slate-700 text-white px-4 py-2 rounded-lg border border-slate-600 focus:border-blue-500 focus:outline-none">
                        <option value="">All Geofences</option>
                        @foreach($geofences ?? [] as $geo)
                            <option value="{{ $geo->id }}">{{ $geo->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm text-slate-400 mb-2">Machine</label>
                    <select wire:model.live="selectedMachineId" class="w-full bg-slate-700 text-white px-4 py-2 rounded-lg border border-slate-600 focus:border-blue-500 focus:outline-none">
                        <option value="">All Machines</option>
                        @foreach($machinesList ?? [] as $m)
                            <option value="{{ $m->id }}">{{ $m->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                <div>
                    <label class="block text-sm text-slate-400 mb-2">Search Reports</label>
                    <input type="text" wire:model.live="search" placeholder="Search by name or description..."
                        class="w-full bg-slate-700 text-white px-4 py-2 rounded-lg border border-slate-600 focus:border-blue-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-sm text-slate-400 mb-2">Report Type</label>
                    <select wire:model.live="selectedType" class="w-full bg-slate-700 text-white px-4 py-2 rounded-lg border border-slate-600 focus:border-blue-500 focus:outline-none">
                        <option value="all">All Types</option>
                        @foreach ($reportTypes as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm text-slate-400 mb-2">Status</label>
                    <select wire:model.live="selectedStatus" class="w-full bg-slate-700 text-white px-4 py-2 rounded-lg border border-slate-600 focus:border-blue-500 focus:outline-none">
                        <option value="all">All Statuses</option>
                        <option value="processing">Processing</option>
                        <option value="completed">Completed</option>
                        <option value="pending">Pending</option>
                        <option value="failed">Failed</option>
                    </select>
                </div>
            </div>

            <div class="mt-4 flex items-center gap-2">
                <span class="text-sm text-slate-400">Sort by:</span>
                @foreach(['title' => 'Name', 'created_at' => 'Date', 'type' => 'Type'] as $col => $colLabel)
                <button wire:click="setSortBy('{{ $col }}')"
                    class="px-3 py-1 rounded-lg text-sm {{ $sortBy === $col ? 'bg-blue-600 text-white' : 'bg-slate-700 text-slate-300 hover:bg-slate-600' }}">
                    {{ $colLabel }}
                </button>
                @endforeach
            </div>

            @if($hasInFlightReports)
                <div class="mt-4 flex items-center gap-2 rounded-lg border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-100">
                    <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                    </svg>
                    <span>Report generation is in progress. This list refreshes automatically.</span>
                </div>
            @endif
        </div>

        <!-- Reports Table -->
        @if($reports->count() > 0)
        <div class="bg-slate-800 rounded-lg border border-slate-700 overflow-hidden" @if($hasInFlightReports) wire:poll.5s="refreshReports" @endif>
            <table class="w-full">
                <thead class="bg-slate-700/50 border-b border-slate-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-slate-300">Name</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-slate-300">Type</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-slate-300">Status</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-slate-300">Date Range</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-slate-300">Created</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-slate-300">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700">
                    @foreach ($reports as $report)
                    <tr class="hover:bg-slate-700/30 transition">
                        <td class="px-6 py-4">
                            <div class="font-medium text-white">{{ $report->title }}</div>
                            @if($report->filters && isset($report->filters['description']))
                                <div class="text-sm text-slate-400">{{ Str::limit($report->filters['description'], 50) }}</div>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-3 py-1 bg-blue-900 text-blue-300 rounded-lg text-sm font-medium">
                                {{ $reportTypes[$report->type] ?? $report->type }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            @if($report->status === 'completed')
                                <span class="px-3 py-1 bg-green-900 text-green-300 rounded-lg text-sm font-medium">Completed</span>
                            @elseif($report->status === 'processing')
                                <span class="px-3 py-1 bg-blue-900 text-blue-300 rounded-lg text-sm font-medium">Processing</span>
                            @elseif($report->status === 'pending')
                                <span class="px-3 py-1 bg-yellow-900 text-yellow-300 rounded-lg text-sm font-medium">Pending</span>
                            @elseif($report->status === 'failed')
                                <span class="px-3 py-1 bg-red-900 text-red-300 rounded-lg text-sm font-medium">Failed</span>
                            @else
                                <span class="px-3 py-1 bg-purple-900 text-purple-300 rounded-lg text-sm font-medium">{{ ucfirst($report->status) }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm text-slate-400">
                            @if($report->filters && isset($report->filters['start_date']) && isset($report->filters['end_date']))
                                {{ \Carbon\Carbon::parse($report->filters['start_date'])->format('M d') }} – {{ \Carbon\Carbon::parse($report->filters['end_date'])->format('M d, Y') }}
                            @else N/A @endif
                        </td>
                        <td class="px-6 py-4 text-sm text-slate-400">{{ $report->created_at->format('M d, Y H:i') }}</td>
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-2">
                                @if($report->status === 'completed' && $report->file_path)
                                <button wire:click="downloadReport({{ $report->id }})"
                                    class="text-blue-400 hover:text-blue-300 text-sm font-medium transition"
                                    wire:loading.attr="disabled" wire:target="downloadReport({{ $report->id }})">
                                    <span wire:loading.remove wire:target="downloadReport({{ $report->id }})">Download</span>
                                    <span wire:loading wire:target="downloadReport({{ $report->id }})">…</span>
                                </button>
                                @elseif(in_array($report->status, ['pending', 'processing'], true))
                                <span class="text-amber-300 text-sm">Preparing…</span>
                                @elseif($report->status === 'failed')
                                <button wire:click="retryReport({{ $report->id }})"
                                    class="text-amber-300 hover:text-amber-200 text-sm font-medium transition"
                                    wire:loading.attr="disabled" wire:target="retryReport({{ $report->id }})">
                                    <span wire:loading.remove wire:target="retryReport({{ $report->id }})">Retry</span>
                                    <span wire:loading wire:target="retryReport({{ $report->id }})">…</span>
                                </button>
                                @endif
                                <a href="{{ route('reports.show', $report->id) }}"
                                    class="text-slate-400 hover:text-white text-sm font-medium transition">View</a>
                                <button wire:click="confirmDelete({{ $report->id }})"
                                    class="text-red-400 hover:text-red-300 text-sm font-medium transition">Delete</button>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $reports->links() }}</div>
        @else
        <div class="bg-slate-800 rounded-lg border border-slate-700 p-12 text-center">
            <svg class="w-16 h-16 text-slate-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <p class="text-slate-400 text-lg mb-2">No reports found</p>
            <p class="text-slate-500 text-sm">Generate your first report to get started</p>
        </div>
        @endif

        <!-- Delete Confirmation Modal -->
        @if($showDeleteConfirm)
        <div class="fixed inset-0 bg-black/70 flex items-center justify-center z-50">
            <div class="bg-slate-800 rounded-xl border border-slate-700 p-6 w-full max-w-md mx-4">
                <h3 class="text-lg font-semibold text-white mb-2">Delete Report</h3>
                <p class="text-slate-400 mb-6">Are you sure you want to delete this report? This action cannot be undone.</p>
                <div class="flex gap-3 justify-end">
                    <button wire:click="cancelDelete" class="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg transition">Cancel</button>
                    <button wire:click="deleteReport({{ $deleteReportId }})" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition">Delete</button>
                </div>
            </div>
        </div>
        @endif

        @endif {{-- end generated tab --}}

        {{-- ═══════════════════════════════════════════════════════════════════
             TAB: Shift Reports (3.1)
        ═══════════════════════════════════════════════════════════════════ --}}
        @if($activeTab === 'shift_reports')

        <!-- Filters -->
        <div class="bg-slate-800 rounded-lg p-6 mb-6 border border-slate-700">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                <div>
                    <label class="block text-sm text-slate-400 mb-2">Shift</label>
                    <select wire:model.live="shiftReportShift" class="w-full bg-slate-700 text-white px-4 py-2 rounded-lg border border-slate-600 focus:border-blue-500 focus:outline-none">
                        <option value="">Select shift…</option>
                        <option value="A">Shift A</option>
                        <option value="B">Shift B</option>
                        <option value="C">Shift C</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm text-slate-400 mb-2">Date</label>
                    <input type="date" wire:model.live="shiftReportDate"
                        class="w-full bg-slate-700 text-white px-4 py-2 rounded-lg border border-slate-600 focus:border-blue-500 focus:outline-none">
                </div>
                <div>
                    @if(!empty($shiftReportData))
                    <button wire:click="exportShiftReportCsv"
                        class="inline-flex items-center gap-2 bg-green-700 hover:bg-green-600 text-white px-4 py-2 rounded-lg font-medium transition w-full justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        Export CSV
                    </button>
                    @endif
                </div>
            </div>
        </div>

        @if(!$shiftReportShift || !$shiftReportDate)
        <div class="bg-slate-800 rounded-lg border border-slate-700 p-12 text-center">
            <p class="text-slate-400">Select a shift and date to generate the report.</p>
        </div>
        @elseif(empty($shiftReportData))
        <div class="bg-slate-800 rounded-lg border border-slate-700 p-12 text-center">
            <p class="text-slate-400">No feed activity found for Shift {{ $shiftReportShift }} on {{ $shiftReportDate }}.</p>
        </div>
        @else

        <!-- KPI cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            @foreach([
                ['Total Posts',             $shiftReportData['total'],                 'text-blue-400'],
                ['Total Likes',             $shiftReportData['total_likes'],            'text-pink-400'],
                ['Total Comments',          $shiftReportData['total_comments'],         'text-yellow-400'],
                ['Acknowledgements',        $shiftReportData['total_acks'],             'text-green-400'],
            ] as [$kLabel, $kVal, $kColor])
            <div class="bg-slate-800 border border-slate-700 rounded-lg p-4 text-center">
                <div class="text-3xl font-bold {{ $kColor }}">{{ $kVal }}</div>
                <div class="text-sm text-slate-400 mt-1">{{ $kLabel }}</div>
            </div>
            @endforeach
        </div>

        @if($shiftReportData['unresolved_breakdowns'] > 0)
        <div class="bg-red-900/40 border border-red-700 rounded-lg p-4 mb-6 flex items-center gap-3">
            <svg class="w-6 h-6 text-red-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            <span class="text-red-300 font-medium">{{ $shiftReportData['unresolved_breakdowns'] }} unresolved breakdown(s) require attention</span>
        </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <!-- Category Breakdown -->
            <div class="bg-slate-800 border border-slate-700 rounded-lg p-5">
                <h3 class="text-white font-semibold mb-4">Posts by Category</h3>
                <div class="space-y-3">
                    @foreach($shiftReportData['by_category'] as $cat => $count)
                    <div class="flex items-center justify-between">
                        <span class="text-slate-300 text-sm capitalize">{{ str_replace('_', ' ', $cat) }}</span>
                        <div class="flex items-center gap-2">
                            <div class="w-24 bg-slate-700 rounded-full h-2">
                                @php $pct = $shiftReportData['total'] > 0 ? ($count / $shiftReportData['total']) * 100 : 0; @endphp
                                <div class="bg-blue-500 h-2 rounded-full" style="width: {{ (int) $pct }}%"></div>
                            </div>
                            <span class="text-white text-sm font-medium w-6 text-right">{{ $count }}</span>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            <!-- Approval Stats -->
            <div class="bg-slate-800 border border-slate-700 rounded-lg p-5">
                <h3 class="text-white font-semibold mb-4">Approval Statistics</h3>
                <div class="space-y-3">
                    @foreach([
                        ['approved', 'bg-green-500', 'text-green-300'],
                        ['rejected', 'bg-red-500',   'text-red-300'],
                        ['pending',  'bg-yellow-500','text-yellow-300'],
                    ] as [$stat, $bg, $text])
                    <div class="flex items-center justify-between">
                        <span class="{{ $text }} text-sm capitalize">{{ $stat }}</span>
                        <span class="text-white font-semibold">{{ $shiftReportData['approval_stats'][$stat] ?? 0 }}</span>
                    </div>
                    @endforeach
                </div>
            </div>

            <!-- Top Posts by Engagement -->
            <div class="bg-slate-800 border border-slate-700 rounded-lg p-5">
                <h3 class="text-white font-semibold mb-4">Top Posts by Engagement</h3>
                @if(empty($shiftReportData['top_posts']))
                    <p class="text-slate-500 text-sm">No posts with interactions yet.</p>
                @else
                <div class="space-y-3">
                    @foreach($shiftReportData['top_posts'] as $tp)
                    <div class="border-b border-slate-700 pb-2 last:border-0 last:pb-0">
                        <p class="text-slate-300 text-sm">{{ $tp['body'] }}</p>
                        <div class="flex gap-3 mt-1 text-xs text-slate-500">
                            <span>👍 {{ $tp['likes'] }}</span>
                            <span>💬 {{ $tp['comments'] }}</span>
                            <span>✓ {{ $tp['acks'] }}</span>
                            <span class="capitalize">{{ str_replace('_', ' ', $tp['category']) }}</span>
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
        </div>
        @endif {{-- end shiftReportData check --}}

        @endif {{-- end shift_reports tab --}}

        {{-- ═══════════════════════════════════════════════════════════════════
             TAB: Machine Breakdown Analytics (3.2)
        ═══════════════════════════════════════════════════════════════════ --}}
        @if($activeTab === 'breakdown')

        <!-- Filters -->
        <div class="bg-slate-800 rounded-lg p-6 mb-6 border border-slate-700">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm text-slate-400 mb-2">From Date</label>
                    <input type="date" wire:model.live="breakdownDateFrom"
                        class="w-full bg-slate-700 text-white px-4 py-2 rounded-lg border border-slate-600 focus:border-blue-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-sm text-slate-400 mb-2">To Date</label>
                    <input type="date" wire:model.live="breakdownDateTo"
                        class="w-full bg-slate-700 text-white px-4 py-2 rounded-lg border border-slate-600 focus:border-blue-500 focus:outline-none">
                </div>
            </div>
        </div>

        <!-- KPI cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-slate-800 border border-slate-700 rounded-lg p-4 text-center">
                <div class="text-3xl font-bold text-red-400">{{ $breakdownData['total'] ?? 0 }}</div>
                <div class="text-sm text-slate-400 mt-1">Total Breakdowns</div>
            </div>
            <div class="bg-slate-800 border border-slate-700 rounded-lg p-4 text-center">
                <div class="text-3xl font-bold text-green-400">{{ $breakdownData['resolved_count'] ?? 0 }}</div>
                <div class="text-sm text-slate-400 mt-1">Resolved</div>
            </div>
            <div class="bg-slate-800 border border-slate-700 rounded-lg p-4 text-center">
                <div class="text-3xl font-bold text-yellow-400">{{ $breakdownData['unresolved_count'] ?? 0 }}</div>
                <div class="text-sm text-slate-400 mt-1">Unresolved</div>
            </div>
            <div class="bg-slate-800 border border-slate-700 rounded-lg p-4 text-center">
                <div class="text-3xl font-bold text-blue-400">
                    {{ $breakdownData['avg_mttr_minutes'] !== null ? $breakdownData['avg_mttr_minutes'] . ' min' : '—' }}
                </div>
                <div class="text-sm text-slate-400 mt-1">Avg MTTR</div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- Chart: Breakdown by Machine -->
            @php
                $bdChartKey = 'bd-chart-' . md5(json_encode($breakdownData['chart_labels'] ?? []));
            @endphp
            <div class="bg-slate-800 border border-slate-700 rounded-lg p-5">
                <h3 class="text-white font-semibold mb-4">Frequency by Machine</h3>
                @if(empty($breakdownData['chart_labels']))
                    <p class="text-slate-500 text-sm">No breakdown posts with machine IDs in this period.</p>
                @else
                <div
                    wire:key="{{ $bdChartKey }}"
                    x-data="{
                        init() {
                            new Chart(this.$refs.canvas.getContext('2d'), {
                                type: 'bar',
                                data: {
                                    labels: @js($breakdownData['chart_labels'] ?? []),
                                    datasets: [{
                                        label: 'Breakdowns',
                                        data: @js($breakdownData['chart_values'] ?? []),
                                        backgroundColor: 'rgba(239,68,68,0.7)',
                                        borderColor: 'rgba(239,68,68,1)',
                                        borderWidth: 1,
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    plugins: { legend: { display: false } },
                                    scales: {
                                        x: { ticks: { color: '#94a3b8' }, grid: { color: '#1e293b' } },
                                        y: { ticks: { color: '#94a3b8', stepSize: 1 }, grid: { color: '#1e293b' }, beginAtZero: true }
                                    }
                                }
                            });
                        }
                    }"
                    x-init="init()"
                >
                    <canvas x-ref="canvas" class="w-full max-h-64"></canvas>
                </div>
                @endif
            </div>

            <!-- Chart: Breakdown by Section -->
            @php $secChartKey = 'sec-chart-' . md5(json_encode($breakdownData['section_labels'] ?? [])); @endphp
            <div class="bg-slate-800 border border-slate-700 rounded-lg p-5">
                <h3 class="text-white font-semibold mb-4">Frequency by Section</h3>
                @if(empty($breakdownData['section_labels']))
                    <p class="text-slate-500 text-sm">No breakdown posts linked to a mine area in this period.</p>
                @else
                <div
                    wire:key="{{ $secChartKey }}"
                    x-data="{
                        init() {
                            new Chart(this.$refs.canvas.getContext('2d'), {
                                type: 'bar',
                                data: {
                                    labels: @js($breakdownData['section_labels'] ?? []),
                                    datasets: [{
                                        label: 'Breakdowns',
                                        data: @js($breakdownData['section_values'] ?? []),
                                        backgroundColor: 'rgba(251,146,60,0.7)',
                                        borderColor: 'rgba(251,146,60,1)',
                                        borderWidth: 1,
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    plugins: { legend: { display: false } },
                                    scales: {
                                        x: { ticks: { color: '#94a3b8' }, grid: { color: '#1e293b' } },
                                        y: { ticks: { color: '#94a3b8', stepSize: 1 }, grid: { color: '#1e293b' }, beginAtZero: true }
                                    }
                                }
                            });
                        }
                    }"
                    x-init="init()"
                >
                    <canvas x-ref="canvas" class="w-full max-h-64"></canvas>
                </div>
                @endif
            </div>
        </div>

        <!-- Breakdown detail table -->
        @if(!empty($breakdownData['by_machine']))
        <div class="bg-slate-800 border border-slate-700 rounded-lg overflow-hidden mb-6">
            <div class="px-5 py-4 border-b border-slate-700">
                <h3 class="text-white font-semibold">Breakdown Count by Machine</h3>
            </div>
            <table class="w-full">
                <thead class="bg-slate-700/50">
                    <tr>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-slate-300">Machine ID</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-slate-300">Breakdowns</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700">
                    @foreach($breakdownData['by_machine'] as $machineId => $count)
                    <tr class="hover:bg-slate-700/30 transition">
                        <td class="px-6 py-3 text-slate-200 font-mono text-sm">{{ $machineId }}</td>
                        <td class="px-6 py-3">
                            <span class="px-2 py-1 bg-red-900 text-red-300 rounded text-sm">{{ $count }}</span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        @endif {{-- end breakdown tab --}}

        {{-- ═══════════════════════════════════════════════════════════════════
             TAB: Production Analytics (3.3)
        ═══════════════════════════════════════════════════════════════════ --}}
        @if($activeTab === 'production')

        <!-- Filters -->
        <div class="bg-slate-800 rounded-lg p-6 mb-6 border border-slate-700">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm text-slate-400 mb-2">Shift</label>
                    <select wire:model.live="productionShift" class="w-full bg-slate-700 text-white px-4 py-2 rounded-lg border border-slate-600 focus:border-blue-500 focus:outline-none">
                        <option value="">All Shifts</option>
                        <option value="A">Shift A</option>
                        <option value="B">Shift B</option>
                        <option value="C">Shift C</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm text-slate-400 mb-2">Mine Area</label>
                    <select wire:model.live="productionMineAreaId" class="w-full bg-slate-700 text-white px-4 py-2 rounded-lg border border-slate-600 focus:border-blue-500 focus:outline-none">
                        <option value="">All Areas</option>
                        @foreach($mineAreas ?? [] as $area)
                            <option value="{{ $area->id }}">{{ $area->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm text-slate-400 mb-2">From Date</label>
                    <input type="date" wire:model.live="productionDateFrom"
                        class="w-full bg-slate-700 text-white px-4 py-2 rounded-lg border border-slate-600 focus:border-blue-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-sm text-slate-400 mb-2">To Date</label>
                    <input type="date" wire:model.live="productionDateTo"
                        class="w-full bg-slate-700 text-white px-4 py-2 rounded-lg border border-slate-600 focus:border-blue-500 focus:outline-none">
                </div>
            </div>
        </div>

        <!-- Trend cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <!-- Week-on-week -->
            <div class="bg-slate-800 border border-slate-700 rounded-lg p-5">
                <h3 class="text-slate-400 text-sm font-medium mb-3">Week-on-Week (Avg LPH)</h3>
                <div class="flex items-center gap-4">
                    <div>
                        <div class="text-2xl font-bold text-white">{{ $productionData['wow_current'] ?? 0 }}</div>
                        <div class="text-xs text-slate-500">This week</div>
                    </div>
                    <div class="text-slate-600">vs</div>
                    <div>
                        <div class="text-2xl font-bold text-slate-400">{{ $productionData['wow_last'] ?? 0 }}</div>
                        <div class="text-xs text-slate-500">Last week</div>
                    </div>
                    @if(isset($productionData['wow_change']) && $productionData['wow_change'] !== null)
                    <div class="ml-auto">
                        @if($productionData['wow_change'] >= 0)
                            <span class="text-green-400 font-semibold text-lg">↑ {{ $productionData['wow_change'] }}%</span>
                        @else
                            <span class="text-red-400 font-semibold text-lg">↓ {{ abs($productionData['wow_change']) }}%</span>
                        @endif
                    </div>
                    @endif
                </div>
            </div>
            <!-- Month-on-month -->
            <div class="bg-slate-800 border border-slate-700 rounded-lg p-5">
                <h3 class="text-slate-400 text-sm font-medium mb-3">Month-on-Month (Avg LPH)</h3>
                <div class="flex items-center gap-4">
                    <div>
                        <div class="text-2xl font-bold text-white">{{ $productionData['mom_current'] ?? 0 }}</div>
                        <div class="text-xs text-slate-500">This month</div>
                    </div>
                    <div class="text-slate-600">vs</div>
                    <div>
                        <div class="text-2xl font-bold text-slate-400">{{ $productionData['mom_last'] ?? 0 }}</div>
                        <div class="text-xs text-slate-500">Last month</div>
                    </div>
                    @if(isset($productionData['mom_change']) && $productionData['mom_change'] !== null)
                    <div class="ml-auto">
                        @if($productionData['mom_change'] >= 0)
                            <span class="text-green-400 font-semibold text-lg">↑ {{ $productionData['mom_change'] }}%</span>
                        @else
                            <span class="text-red-400 font-semibold text-lg">↓ {{ abs($productionData['mom_change']) }}%</span>
                        @endif
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Timeline Chart -->
        @php $prodChartKey = 'prod-chart-' . md5(json_encode($productionData['timeline_labels'] ?? [])); @endphp
        <div class="bg-slate-800 border border-slate-700 rounded-lg p-5 mb-6">
            <h3 class="text-white font-semibold mb-4">Daily Average Loads Per Hour</h3>
            @if(empty($productionData['timeline_labels']))
                <p class="text-slate-500 text-sm">No shift update posts in this date range.</p>
            @else
            <div
                wire:key="{{ $prodChartKey }}"
                x-data="{
                    init() {
                        new Chart(this.$refs.canvas.getContext('2d'), {
                            type: 'line',
                            data: {
                                labels: @js($productionData['timeline_labels'] ?? []),
                                datasets: [{
                                    label: 'Avg LPH',
                                    data: @js($productionData['timeline_values'] ?? []),
                                    borderColor: 'rgb(59,130,246)',
                                    backgroundColor: 'rgba(59,130,246,0.1)',
                                    tension: 0.3,
                                    fill: true,
                                    pointBackgroundColor: 'rgb(59,130,246)',
                                }]
                            },
                            options: {
                                responsive: true,
                                plugins: {
                                    legend: { display: false },
                                    tooltip: { callbacks: { label: ctx => ' ' + ctx.parsed.y + ' LPH' } }
                                },
                                scales: {
                                    x: { ticks: { color: '#94a3b8' }, grid: { color: '#1e293b' } },
                                    y: { ticks: { color: '#94a3b8' }, grid: { color: '#1e293b' }, beginAtZero: true }
                                }
                            }
                        });
                    }
                }"
                x-init="init()"
            >
                <canvas x-ref="canvas" class="w-full" style="max-height: 280px;"></canvas>
            </div>
            @endif
        </div>

        <!-- Per-Shift Summary Table -->
        <div class="bg-slate-800 border border-slate-700 rounded-lg overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-700">
                <h3 class="text-white font-semibold">Per-Shift Summary</h3>
            </div>
            <table class="w-full">
                <thead class="bg-slate-700/50">
                    <tr>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-slate-300">Shift</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-slate-300">Updates</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-slate-300">Avg LPH</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-slate-300">Total Tonnage</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700">
                    @foreach($productionData['by_shift'] ?? [] as $shift => $stats)
                    <tr class="hover:bg-slate-700/30 transition">
                        <td class="px-6 py-3">
                            <span class="px-2 py-1 bg-blue-900 text-blue-300 rounded text-sm font-medium">Shift {{ $shift }}</span>
                        </td>
                        <td class="px-6 py-3 text-slate-300">{{ $stats['count'] }}</td>
                        <td class="px-6 py-3 text-white font-medium">{{ $stats['avg_loads_per_hour'] ?? '—' }}</td>
                        <td class="px-6 py-3 text-slate-300">{{ $stats['total_tonnage'] !== null ? number_format($stats['total_tonnage']) . ' t' : '—' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @endif {{-- end production tab --}}

        {{-- ═══════════════════════════════════════════════════════════════════
             TAB: Historical Log (3.4)
        ═══════════════════════════════════════════════════════════════════ --}}
        @if($activeTab === 'history')

        <!-- Filters -->
        <div class="bg-slate-800 rounded-lg p-6 mb-6 border border-slate-700">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div class="md:col-span-3">
                    <label class="block text-sm text-slate-400 mb-2">Full-text Search</label>
                    <input type="text" wire:model.live.debounce.400ms="historySearch"
                        placeholder="Search post bodies and comments…"
                        class="w-full bg-slate-700 text-white px-4 py-2 rounded-lg border border-slate-600 focus:border-blue-500 focus:outline-none">
                </div>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm text-slate-400 mb-2">Category</label>
                    <select wire:model.live="historyCategory" class="w-full bg-slate-700 text-white px-4 py-2 rounded-lg border border-slate-600 focus:border-blue-500 focus:outline-none">
                        <option value="">All</option>
                        @foreach(\App\Models\FeedPost::CATEGORIES as $cat)
                            <option value="{{ $cat }}">{{ ucfirst(str_replace('_', ' ', $cat)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm text-slate-400 mb-2">Shift</label>
                    <select wire:model.live="historyShift" class="w-full bg-slate-700 text-white px-4 py-2 rounded-lg border border-slate-600 focus:border-blue-500 focus:outline-none">
                        <option value="">All</option>
                        <option value="A">A</option>
                        <option value="B">B</option>
                        <option value="C">C</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm text-slate-400 mb-2">Author</label>
                    <select wire:model.live="historyAuthorId" class="w-full bg-slate-700 text-white px-4 py-2 rounded-lg border border-slate-600 focus:border-blue-500 focus:outline-none">
                        <option value="">All</option>
                        @foreach($teamUsers as $u)
                            <option value="{{ $u->id }}">{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm text-slate-400 mb-2">Approval</label>
                    <select wire:model.live="historyApproval" class="w-full bg-slate-700 text-white px-4 py-2 rounded-lg border border-slate-600 focus:border-blue-500 focus:outline-none">
                        <option value="">All</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                        <option value="pending">Pending</option>
                        <option value="none">No approval</option>
                    </select>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4 mt-4">
                <div>
                    <label class="block text-sm text-slate-400 mb-2">From Date</label>
                    <input type="date" wire:model.live="historyDateFrom"
                        class="w-full bg-slate-700 text-white px-4 py-2 rounded-lg border border-slate-600 focus:border-blue-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-sm text-slate-400 mb-2">To Date</label>
                    <input type="date" wire:model.live="historyDateTo"
                        class="w-full bg-slate-700 text-white px-4 py-2 rounded-lg border border-slate-600 focus:border-blue-500 focus:outline-none">
                </div>
            </div>
        </div>

        @if($history && $history->count() > 0)
        <div class="bg-slate-800 border border-slate-700 rounded-lg overflow-hidden">
            <table class="w-full">
                <thead class="bg-slate-700/50 border-b border-slate-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-slate-300">Post</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-slate-300">Author</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-slate-300">Category</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-slate-300">Shift</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-slate-300">Section</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-slate-300">Approval</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-slate-300">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700">
                    @foreach($history as $post)
                    @php $isDeleted = $post->deleted_at !== null; @endphp
                    <tr class="hover:bg-slate-700/30 transition {{ $isDeleted ? 'opacity-60' : '' }}">
                        <td class="px-6 py-3 max-w-xs">
                            <p class="text-slate-200 text-sm">{{ Str::limit($post->body, 100) }}</p>
                            @if($isDeleted)
                                <span class="inline-block mt-1 px-2 py-0.5 bg-red-900 text-red-300 text-xs rounded">DELETED</span>
                            @endif
                        </td>
                        <td class="px-6 py-3 text-slate-400 text-sm">{{ $post->author?->name ?? '—' }}</td>
                        <td class="px-6 py-3">
                            <span class="px-2 py-0.5 rounded text-xs font-medium
                                @switch($post->category)
                                    @case('breakdown') bg-red-900 text-red-300 @break
                                    @case('shift_update') bg-blue-900 text-blue-300 @break
                                    @case('safety_alert') bg-orange-900 text-orange-300 @break
                                    @case('production') bg-green-900 text-green-300 @break
                                    @default bg-slate-700 text-slate-300
                                @endswitch
                            ">{{ str_replace('_', ' ', $post->category) }}</span>
                        </td>
                        <td class="px-6 py-3 text-slate-400 text-sm">{{ $post->shift ?? '—' }}</td>
                        <td class="px-6 py-3 text-slate-400 text-sm">{{ $post->mineArea?->name ?? '—' }}</td>
                        <td class="px-6 py-3">
                            @if($post->approval)
                                @if($post->approval->status === 'approved')
                                    <span class="px-2 py-0.5 bg-green-900 text-green-300 rounded text-xs">Approved</span>
                                @elseif($post->approval->status === 'rejected')
                                    <span class="px-2 py-0.5 bg-red-900 text-red-300 rounded text-xs">Rejected</span>
                                @else
                                    <span class="px-2 py-0.5 bg-yellow-900 text-yellow-300 rounded text-xs">Pending</span>
                                @endif
                            @else
                                <span class="text-slate-600 text-xs">—</span>
                            @endif
                        </td>
                        <td class="px-6 py-3 text-slate-500 text-xs">{{ $post->created_at->format('M d, Y H:i') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $history->links() }}</div>
        @elseif($history)
        <div class="bg-slate-800 rounded-lg border border-slate-700 p-12 text-center">
            <svg class="w-16 h-16 text-slate-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p class="text-slate-400 text-lg mb-2">No posts found</p>
            <p class="text-slate-500 text-sm">Try adjusting your filters</p>
        </div>
        @else
        <div class="bg-slate-800 rounded-lg border border-slate-700 p-12 text-center">
            <p class="text-slate-500 text-sm">Loading…</p>
        </div>
        @endif

        @endif {{-- end history tab --}}

    </div>
</div>

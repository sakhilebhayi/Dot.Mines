<div class="min-h-screen bg-[var(--ink)] p-6">
    <div class="max-w-7xl mx-auto">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-4xl font-bold text-[var(--stone)] mb-2">Reports</h1>
            <p class="text-[var(--sand)]">Generate and manage mining operation reports</p>
        </div>

        <!-- Actions Bar -->
        <div class="bg-[var(--ink-soft)] rounded-lg p-6 mb-6 border border-[var(--line)]">
            <div class="flex items-center justify-between gap-4 mb-4">
                <a href="{{ route('report-generator') }}" wire:navigate class="inline-flex items-center gap-2 bg-[var(--gold)] hover:bg-[var(--gold-soft)] text-[var(--ink)] px-4 py-2 rounded-lg font-display font-semibold transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    <span>Generate Report</span>
                </a>
            </div>

            <!-- Advanced Filters (View 2) -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                <!-- Mine Area Filter -->
                <div>
                    <label class="block text-sm text-[var(--sand)] mb-2">Mine Area</label>
                    <select
                        wire:model.live="selectedMineAreaId"
                        class="w-full bg-white/10 text-[var(--stone)] px-4 py-2 rounded-lg border border-[var(--line)] focus:border-[var(--gold)] focus:outline-none"
                    >
                        <option value="">All Mine Areas</option>
                        @foreach($mineAreas ?? [] as $area)
                            <option value="{{ $area->id }}">{{ $area->name }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Geofence Filter -->
                <div>
                    <label class="block text-sm text-[var(--sand)] mb-2">Geofence</label>
                    <select
                        wire:model.live="selectedGeofenceId"
                        class="w-full bg-white/10 text-[var(--stone)] px-4 py-2 rounded-lg border border-[var(--line)] focus:border-[var(--gold)] focus:outline-none"
                    >
                        <option value="">All Geofences</option>
                        @foreach($geofences ?? [] as $geo)
                            <option value="{{ $geo->id }}">{{ $geo->name }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Machine Name Filter -->
                <div>
                    <label class="block text-sm text-[var(--sand)] mb-2">Machine Name</label>
                    <select
                        wire:model.live="selectedMachineId"
                        class="w-full bg-white/10 text-[var(--stone)] px-4 py-2 rounded-lg border border-[var(--line)] focus:border-[var(--gold)] focus:outline-none"
                    >
                        <option value="">All Machines</option>
                        @foreach($machinesList ?? [] as $m)
                            <option value="{{ $m->id }}">{{ $m->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <!-- Filters -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Search -->
                <div>
                    <label class="block text-sm text-[var(--sand)] mb-2">Search Reports</label>
                    <input 
                        type="text" 
                        wire:model.live="search" 
                        placeholder="Search by name or description..."
                        class="w-full bg-white/10 text-[var(--stone)] px-4 py-2 rounded-lg border border-[var(--line)] focus:border-[var(--gold)] focus:outline-none"
                    >
                </div>

                <!-- Report Type Filter -->
                <div>
                    <label class="block text-sm text-[var(--sand)] mb-2">Report Type</label>
                    <select 
                        wire:model.live="selectedType"
                        class="w-full bg-white/10 text-[var(--stone)] px-4 py-2 rounded-lg border border-[var(--line)] focus:border-[var(--gold)] focus:outline-none"
                    >
                        <option value="all">All Types</option>
                        @foreach ($reportTypes as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Status Filter -->
                <div>
                    <label class="block text-sm text-[var(--sand)] mb-2">Status</label>
                    <select 
                        wire:model.live="selectedStatus"
                        class="w-full bg-white/10 text-[var(--stone)] px-4 py-2 rounded-lg border border-[var(--line)] focus:border-[var(--gold)] focus:outline-none"
                    >
                        <option value="all">All Statuses</option>
                        <option value="completed">Completed</option>
                        <option value="pending">Pending</option>
                        <option value="scheduled">Scheduled</option>
                    </select>
                </div>
            </div>

            <!-- Sort Controls -->
            <div class="mt-4 flex items-center gap-2">
                <span class="text-sm text-[var(--sand)]">Sort by:</span>
                <button 
                    wire:click="setSortBy('name')"
                    class="px-3 py-1 rounded-lg text-sm {{ $sortBy === 'name' ? 'bg-[var(--gold)] text-[var(--ink)]' : 'bg-white/10 text-[var(--sand)] hover:bg-white/20' }}"
                >
                    Name
                </button>
                <button 
                    wire:click="setSortBy('created_at')"
                    class="px-3 py-1 rounded-lg text-sm {{ $sortBy === 'created_at' ? 'bg-[var(--gold)] text-[var(--ink)]' : 'bg-white/10 text-[var(--sand)] hover:bg-white/20' }}"
                >
                    Date
                </button>
                <button 
                    wire:click="setSortBy('type')"
                    class="px-3 py-1 rounded-lg text-sm {{ $sortBy === 'type' ? 'bg-[var(--gold)] text-[var(--ink)]' : 'bg-white/10 text-[var(--sand)] hover:bg-white/20' }}"
                >
                    Type
                </button>
            </div>
        </div>

        <!-- Reports Table -->
        @if($reports->count() > 0)
            <div class="bg-[var(--ink-soft)] rounded-lg border border-[var(--line)] overflow-hidden">
                <table class="w-full">
                    <thead class="bg-white/5 border-b border-[var(--line)]">
                        <tr>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-[var(--sand)]">Name</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-[var(--sand)]">Type</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-[var(--sand)]">Status</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-[var(--sand)]">Date Range</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-[var(--sand)]">Created</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-[var(--sand)]">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--line)]">
                        @foreach ($reports as $report)
                            <tr class="hover:bg-white/5 transition">
                                <td class="px-6 py-4">
                                    <div class="font-medium text-[var(--stone)]">{{ $report->title }}</div>
                                    @if($report->filters && isset($report->filters['description']))
                                        <div class="text-sm text-[var(--sand)]">{{ Str::limit($report->filters['description'], 50) }}</div>
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
                                    @elseif($report->status === 'pending')
                                        <span class="px-3 py-1 bg-yellow-900 text-yellow-300 rounded-lg text-sm font-medium">Pending</span>
                                    @elseif($report->status === 'failed')
                                        <span class="px-3 py-1 bg-red-900 text-red-300 rounded-lg text-sm font-medium" @if($report->error_message) title="{{ $report->error_message }}" @endif>Failed</span>
                                    @else
                                        <span class="px-3 py-1 bg-purple-900 text-purple-300 rounded-lg text-sm font-medium">{{ ucfirst($report->status) }}</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-[var(--sand)]">
                                    @if($report->filters && isset($report->filters['start_date']) && isset($report->filters['end_date']))
                                        {{ \Carbon\Carbon::parse($report->filters['start_date'])->format('M d') }} - {{ \Carbon\Carbon::parse($report->filters['end_date'])->format('M d, Y') }}
                                    @else
                                        N/A
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-[var(--sand)]">
                                    {{ $report->created_at->format('M d, Y H:i') }}
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2">
                                        @if($report->file_path)
                                            <button 
                                                wire:click="downloadReport({{ $report->id }})"
                                                class="text-[var(--gold)] hover:text-[var(--gold-soft)] text-sm font-medium transition flex items-center gap-1"
                                                wire:loading.attr="disabled"
                                                wire:target="downloadReport({{ $report->id }})"
                                            >
                                                <span wire:loading.remove wire:target="downloadReport({{ $report->id }})">Download</span>
                                                <span wire:loading wire:target="downloadReport({{ $report->id }})" class="flex items-center gap-1">
                                                    <svg class="animate-spin h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                                    Downloading...
                                                </span>
                                            </button>
                                        @endif
                                        <a 
                                            href="{{ route('reports.show', $report->id) }}"
                                            class="text-[var(--sand)] hover:text-[var(--sand)] text-sm font-medium transition"
                                        >
                                            View
                                        </a>
                                        <button 
                                            wire:click="confirmDelete({{ $report->id }})"
                                            class="text-red-400 hover:text-red-300 text-sm font-medium transition"
                                        >
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="mt-6">
                {{ $reports->links(data: ['scrollTo' => false]) }}
            </div>
        @else
            <!-- Empty State -->
            <div class="bg-[var(--ink-soft)] rounded-lg border border-[var(--line)] p-12 text-center">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-white/10 mb-4">
                    <svg class="w-8 h-8 text-[var(--sand)]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                </div>
                <h3 class="text-xl font-semibold text-[var(--stone)] mb-2">No Reports Found</h3>
                <p class="text-[var(--sand)] mb-6">No reports match your criteria. Try adjusting your filters or create a new report.</p>
                <a href="{{ route('report-generator') }}" wire:navigate class="inline-flex items-center gap-2 bg-[var(--gold)] hover:bg-[var(--gold-soft)] text-[var(--ink)] px-6 py-2 rounded-lg font-display font-semibold transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    <span>Generate First Report</span>
                </a>
            </div>
        @endif
    </div>

    <!-- Delete Confirmation Modal -->
    @if($showDeleteConfirm)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
            <div class="bg-[var(--ink-soft)] rounded-lg border border-[var(--line)] p-6 w-96">
                <h3 class="text-lg font-semibold text-[var(--stone)] mb-4">Delete Report?</h3>
                <p class="text-[var(--sand)] mb-6">This action cannot be undone. The report will be permanently deleted.</p>
                <div class="flex gap-3 justify-end">
                    <button 
                        wire:click="cancelDelete"
                        class="px-4 py-2 rounded-lg bg-white/10 text-[var(--stone)] hover:bg-white/20 transition font-medium"
                    >
                        Cancel
                    </button>
                    <button 
                        wire:click="deleteReport({{ $deleteReportId }})"
                        class="px-4 py-2 rounded-lg bg-red-600 text-[var(--stone)] hover:bg-red-700 transition font-medium flex items-center gap-2"
                        wire:loading.attr="disabled"
                        wire:target="deleteReport"
                    >
                        <span wire:loading.remove wire:target="deleteReport">Delete</span>
                        <span wire:loading wire:target="deleteReport" class="flex items-center gap-2">
                            <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            Deleting...
                        </span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>

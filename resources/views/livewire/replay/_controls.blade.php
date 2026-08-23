{{-- Machine / date-range selection and recent activities (R7 split) --}}
    <!-- Machine Selection -->
    <div class="mb-4">
        <label class="block text-sm font-medium text-[var(--sand)] mb-2">Select Machine</label>
        <select wire:model.live="selectedMachine" class="w-full px-4 py-2 bg-white/5 border border-[var(--line)] rounded-lg text-[var(--stone)] focus:ring-2 focus:ring-[var(--gold)]">
            <option value="">-- Choose a Machine --</option>
            @foreach($machines as $machineType => $machineGroup)
                <optgroup label="{{ strtoupper(str_replace('_', ' ', $machineType)) }}">
                    @foreach($machineGroup as $machine)
                        <option value="{{ $machine->id }}">{{ $machine->name }} ({{ $machine->manufacturer }})</option>
                    @endforeach
                </optgroup>
            @endforeach
        </select>
    </div>

    <!-- Date Range -->
    <div class="mb-4">
        <label class="block text-sm font-medium text-[var(--sand)] mb-2">Start Date</label>
        <input type="date" wire:model="startDate" class="w-full px-4 py-2 bg-white/5 border border-[var(--line)] rounded-lg text-[var(--stone)] focus:ring-2 focus:ring-[var(--gold)]">
    </div>

    <div class="mb-4">
        <label class="block text-sm font-medium text-[var(--sand)] mb-2">End Date</label>
        <input type="date" wire:model="endDate" class="w-full px-4 py-2 bg-white/5 border border-[var(--line)] rounded-lg text-[var(--stone)] focus:ring-2 focus:ring-[var(--gold)]">
    </div>

    <!-- Action Buttons -->
    <div class="grid grid-cols-2 gap-2 mb-6">
        <button wire:click="loadReplay" class="px-4 py-2 bg-[var(--gold)] hover:bg-[var(--gold-soft)] text-[var(--ink)] rounded-lg transition-colors font-display font-semibold flex items-center justify-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
            </svg>
            Load Replay
        </button>
        <button wire:click="showRecentActivities" class="px-4 py-2 bg-white/10 hover:bg-white/20 text-[var(--stone)] rounded-lg transition-colors font-medium flex items-center justify-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            Recent
        </button>
        <button wire:click="exportReplayData" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-[var(--stone)] rounded-lg transition-colors font-medium flex items-center justify-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            Export
        </button>
        <button wire:click="showRoutes" class="px-4 py-2 bg-white/10 hover:bg-white/20 text-[var(--stone)] rounded-lg transition-all font-medium flex items-center justify-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
            </svg>
            Routes
        </button>
    </div>

    <!-- Recent Activities (for selected machine / date range) -->
    @if($showActivities)
        <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg p-4 mb-4">
            <div class="flex items-center justify-between mb-3">
                <h4 class="text-sm font-semibold text-[var(--stone)]">Recent Activities</h4>
                <button wire:click="hideRecentActivities" class="text-xs text-[var(--sand)] hover:text-[var(--stone)]">Close</button>
            </div>
            @if(count($machineActivities) > 0)
                <ul class="space-y-2 text-sm text-[var(--sand)] max-h-64 overflow-y-auto">
                    @foreach($machineActivities as $act)
                        <li>
                            <div class="text-xs text-[var(--sand)]">{{ $act['created_at'] }} — {{ $act['user'] }}</div>
                            <div class="font-medium">{{ $act['action'] }}</div>
                            <div class="text-[var(--sand)] text-sm">{{ $act['description'] }}</div>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="text-sm text-[var(--sand)]">No activities found for the selected machine/date range.</p>
            @endif
        </div>
    @endif


<div class="min-h-screen bg-[var(--ink)] p-6">
    <div class="max-w-7xl mx-auto">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-4xl font-display font-semibold text-[var(--stone)] mb-2">Alerts</h1>
            <p class="text-[var(--sand)]">Monitor and manage machine alerts and notifications</p>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            @php
                $allAlerts = $alerts;
                $criticalCount = $allAlerts->where('priority', 'critical')->count() ?? 0;
                $highCount = $allAlerts->where('priority', 'high')->count() ?? 0;
                $openCount = $allAlerts->whereIn('status', ['active', 'acknowledged'])->count() ?? 0;
            @endphp
            
            <div class="bg-red-900/30 border border-red-700 rounded-lg p-4">
                <div class="text-red-400 text-sm font-medium">Critical Alerts</div>
                <div class="text-3xl font-bold text-red-300 mt-2">{{ $criticalCount }}</div>
            </div>
            
            <div class="bg-orange-900/30 border border-orange-700 rounded-lg p-4">
                <div class="text-orange-400 text-sm font-medium">High Priority</div>
                <div class="text-3xl font-bold text-orange-300 mt-2">{{ $highCount }}</div>
            </div>
            
            <div class="bg-blue-900/30 border border-blue-700 rounded-lg p-4">
                <div class="text-blue-400 text-sm font-medium">Open Alerts</div>
                <div class="text-3xl font-bold text-blue-300 mt-2">{{ $openCount }}</div>
            </div>
            
            <div class="bg-white/5 border border-[var(--line)] rounded-lg p-4">
                <div class="text-[var(--sand)] text-sm font-medium">Total Alerts</div>
                <div class="text-3xl font-bold text-[var(--sand)] mt-2">{{ $allAlerts->total() ?? 0 }}</div>
            </div>
        </div>

        <!-- Filters Bar -->
        <div class="bg-[var(--ink-soft)] rounded-lg p-6 mb-6 border border-[var(--line)]">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                <!-- Search -->
                <div>
                    <label class="block text-sm text-[var(--sand)] mb-2">Search Alerts</label>
                    <input 
                        type="text" 
                        wire:model.live="search" 
                        placeholder="Search by title or description..."
                        class="w-full bg-white/5 text-[var(--stone)] px-4 py-2 rounded-lg border border-[var(--line)] focus:border-[var(--gold)] focus:outline-none"
                    >
                </div>

                <!-- Priority Filter -->
                <div>
                    <label class="block text-sm text-[var(--sand)] mb-2">Priority</label>
                    <select 
                        wire:model.live="selectedPriority"
                        class="w-full bg-white/5 text-[var(--stone)] px-4 py-2 rounded-lg border border-[var(--line)] focus:border-[var(--gold)] focus:outline-none"
                    >

                        <option value="all">All Priorities</option>
                        @foreach ($alertPriorities as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Status Filter -->
                <div>
                    <label class="block text-sm text-[var(--sand)] mb-2">Status</label>
                    <select 
                        wire:model.live="selectedStatus"
                        class="w-full bg-white/5 text-[var(--stone)] px-4 py-2 rounded-lg border border-[var(--line)] focus:border-[var(--gold)] focus:outline-none"
                    >
                        <option value="all">All Statuses</option>
                        @foreach ($alertStatuses as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Type Filter -->
                <div>
                    <label class="block text-sm text-[var(--sand)] mb-2">Type</label>
                    <select 
                        wire:model.live="selectedType"
                        class="w-full bg-white/5 text-[var(--stone)] px-4 py-2 rounded-lg border border-[var(--line)] focus:border-[var(--gold)] focus:outline-none"
                    >
                        <option value="all">All Types</option>
                        @foreach ($alertTypes as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <!-- Sort Controls -->
            <div class="flex items-center gap-2">
                <span class="text-sm text-[var(--sand)]">Sort by:</span>
                <button 
                    wire:click="setSortBy('created_at')"
                    class="px-3 py-1 rounded-lg text-sm {{ $sortBy === 'created_at' ? 'bg-[var(--gold)] text-[var(--ink)]' : 'bg-white/5 text-[var(--sand)] hover:bg-white/10' }}"
                >
                    Date
                </button>
                <button 
                    wire:click="setSortBy('priority')"
                    class="px-3 py-1 rounded-lg text-sm {{ $sortBy === 'priority' ? 'bg-[var(--gold)] text-[var(--ink)]' : 'bg-white/5 text-[var(--sand)] hover:bg-white/10' }}"
                >
                    Priority
                </button>
                <button 
                    wire:click="setSortBy('status')"
                    class="px-3 py-1 rounded-lg text-sm {{ $sortBy === 'status' ? 'bg-[var(--gold)] text-[var(--ink)]' : 'bg-white/5 text-[var(--sand)] hover:bg-white/10' }}"
                >
                    Status
                </button>
            </div>
        </div>

        <!-- Alerts List -->
        @if($alerts->count() > 0)
            <div class="space-y-3">
                @foreach ($alerts as $alert)
                    @php
                        $priorityClasses = $alert->priority === 'critical' ? 'border-red-700 bg-red-900/10' : ($alert->priority === 'high' ? 'border-orange-700 bg-orange-900/10' : ($alert->priority === 'medium' ? 'border-yellow-700 bg-yellow-900/10' : 'border-[var(--line)]'));
                    @endphp

                    <div class="bg-[var(--ink-soft)] rounded-lg border {{ $priorityClasses }} p-4 hover:border-[var(--gold)]/50 transition">
                        <div class="flex items-start justify-between gap-4">
                            <!-- Alert Info -->
                            <div class="flex-1">
                                <div class="flex items-center gap-3 mb-2">
                                    <!-- Priority Badge -->
                                    <span class="px-2 py-1 text-xs font-semibold rounded {{ 
                                        $alert->priority === 'critical' ? 'bg-red-900 text-red-300' : 
                                        ($alert->priority === 'high' ? 'bg-orange-900 text-orange-300' : 
                                        ($alert->priority === 'medium' ? 'bg-yellow-900 text-yellow-300' : 'bg-blue-900 text-blue-300')) 
                                    }}">
                                        {{ ucfirst($alert->priority) }}
                                    </span>

                                    <!-- Status Badge -->
                                                    @php
                                                        $statusLabel = $alertStatuses[$alert->status] ?? ucfirst($alert->status);

                                                        $statusClass = match($alert->status) {
                                                            'active' => 'bg-green-900 text-green-300',
                                                            'acknowledged' => 'bg-blue-900 text-blue-300',
                                                            'resolved' => 'bg-white/5 text-[var(--sand)]',
                                                            'dismissed_unresolved' => 'bg-red-900 text-red-300',
                                                            'dismissed' => 'bg-white/10 text-[var(--sand)]',
                                                            default => 'bg-white/10 text-[var(--sand)]',
                                                        };
                                                    @endphp
                                                    <span class="px-2 py-1 text-xs font-semibold rounded {{ $statusClass }}">{{ $statusLabel }}</span>

                                    <!-- Type Badge -->
                                    <span class="px-2 py-1 text-xs font-medium rounded bg-white/5 text-[var(--sand)]">
                                        {{ $alertTypes[$alert->type] ?? $alert->type }}
                                    </span>
                                </div>

                                <h3 class="text-lg font-semibold text-[var(--stone)] mb-1">{{ $alert->title }}</h3>
                                <p class="text-[var(--sand)] text-sm">{{ $alert->description }}</p>

                                @if($alert->machine)
                                    <div class="mt-2 text-sm text-[var(--sand)]/70">
                                        <a href="{{ route('fleet.show', $alert->machine->id) }}" class="text-[var(--gold)] hover:text-[var(--gold-soft)]">
                                            Machine: {{ $alert->machine->name }}
                                        </a>
                                    </div>
                                @endif

                                <div class="mt-2 text-xs text-[var(--sand)]/70">
                                    {{ $alert->created_at->diffForHumans() }}
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="flex items-center gap-2">
                                <button 
                                    wire:click="showDetails({{ $alert->id }})"
                                    class="px-3 py-1 text-sm bg-white/5 text-[var(--sand)] hover:bg-white/10 rounded transition"
                                >
                                    Details
                                </button>

                                @if($alert->status === 'active')
                                    <button 
                                        wire:click="acknowledgeAlert({{ $alert->id }})"
                                        class="px-3 py-1 text-sm bg-[var(--gold)] text-[var(--ink)] hover:bg-[var(--gold-soft)] rounded transition flex items-center gap-1 font-medium"
                                        wire:loading.attr="disabled"
                                        wire:target="acknowledgeAlert({{ $alert->id }})"
                                    >
                                        <span wire:loading.remove wire:target="acknowledgeAlert({{ $alert->id }})">Acknowledge</span>
                                        <span wire:loading wire:target="acknowledgeAlert({{ $alert->id }})" class="flex items-center gap-1">
                                            <svg class="animate-spin h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                        </span>
                                    </button>
                                @endif

                                @if($alert->status !== 'resolved')
                                    <button 
                                        wire:click="resolveAlert({{ $alert->id }})"
                                        class="px-3 py-1 text-sm bg-green-600 text-[var(--stone)] hover:bg-green-700 rounded transition flex items-center gap-1"
                                        wire:loading.attr="disabled"
                                        wire:target="resolveAlert({{ $alert->id }})"
                                    >
                                        <span wire:loading.remove wire:target="resolveAlert({{ $alert->id }})">Resolve</span>
                                        <span wire:loading wire:target="resolveAlert({{ $alert->id }})" class="flex items-center gap-1">
                                            <svg class="animate-spin h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                        </span>
                                    </button>
                                @endif

                                @if($alert->status !== 'dismissed')
                                    <button 
                                        wire:click="dismissAlert({{ $alert->id }})"
                                        class="px-3 py-1 text-sm bg-white/10 text-[var(--stone)] hover:bg-white/15 rounded transition"
                                    >
                                        Dismiss
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Pagination -->
            <div class="mt-6">
                {{ $alerts->links(data: ['scrollTo' => false]) }}
            </div>
        @else
            <!-- Empty State -->
            <div class="bg-[var(--ink-soft)] rounded-lg border border-[var(--line)] p-12 text-center">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-white/5 mb-4">
                    <svg class="w-8 h-8 text-[var(--sand)]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                    </svg>
                </div>
                <h3 class="text-xl font-semibold text-[var(--stone)] mb-2">No Alerts Found</h3>
                <p class="text-[var(--sand)]">No alerts match your criteria. Great job!</p>
            </div>
        @endif
    </div>

    <!-- Alert Details Modal -->
    @if($showDetailsModal && $selectedAlert)
        @php
            $isSpeedAlert = in_array(strtolower($selectedAlert->type ?? ''), ['speed', 'speed_limit', 'overspeed']);
            $modalSizeClass = $isSpeedAlert ? 'w-full max-w-3xl max-h-[80vh]' : 'w-96 max-h-96';
        @endphp
        <div data-app-overlay class="fixed inset-0 z-[1100] bg-black/60 flex items-center justify-center p-4 overflow-y-auto">
            <div class="bg-[var(--ink-soft)] rounded-lg border border-[var(--line)] p-6 {{ $modalSizeClass }} overflow-y-auto">
                <div class="flex justify-between items-start mb-4">
                    <h3 class="text-lg font-semibold text-[var(--stone)]">{{ $selectedAlert->title }}</h3>
                    <button 
                        wire:click="closeDetails"
                        class="text-[var(--sand)] hover:text-[var(--sand)]"
                    >
                        ✕
                    </button>
                </div>

                <div class="space-y-4">
                    <div>
                        <div class="text-xs font-semibold text-[var(--sand)] uppercase mb-1">Description</div>
                        <div class="text-[var(--sand)]">{{ $selectedAlert->description }}</div>
                    </div>

                    @if($selectedAlert->details)
                        <div>
                            <div class="text-xs font-semibold text-[var(--sand)] uppercase mb-1">Details</div>
                            <div class="text-[var(--sand)] text-sm">{{ $selectedAlert->details }}</div>
                        </div>
                    @endif

                    <div>
                        <div class="text-xs font-semibold text-[var(--sand)] uppercase mb-1">Priority</div>
                        <div class="text-[var(--sand)]">{{ ucfirst($selectedAlert->priority) }}</div>
                    </div>

                    <div>
                        <div class="text-xs font-semibold text-[var(--sand)] uppercase mb-1">Status</div>
                        <div class="text-[var(--sand)]">{{ ucfirst($selectedAlert->status) }}</div>
                    </div>

                    <div>
                        <div class="text-xs font-semibold text-[var(--sand)] uppercase mb-1">Created</div>
                        <div class="text-[var(--sand)]">{{ $selectedAlert->created_at->format('M d, Y H:i') }}</div>
                    </div>

                    @if($selectedAlert->acknowledged_at)
                        <div>
                            <div class="text-xs font-semibold text-[var(--sand)] uppercase mb-1">Acknowledged</div>
                            <div class="text-[var(--sand)]">{{ $selectedAlert->acknowledged_at->format('M d, Y H:i') }}</div>
                        </div>
                    @endif

                    @if($selectedAlert->resolved_at)
                        <div>
                            <div class="text-xs font-semibold text-[var(--sand)] uppercase mb-1">Resolved</div>
                            <div class="text-[var(--sand)]">{{ $selectedAlert->resolved_at->format('M d, Y H:i') }}</div>
                        </div>
                    @endif

                    {{-- Location / Context --}}
                    @if($isSpeedAlert)
                        <!-- Expanded speed alert context -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <div class="text-xs font-semibold text-[var(--sand)] uppercase mb-1">GPS Coordinates</div>
                                <div class="text-[var(--sand)] text-sm">
                                    @if(is_array($selectedAlert->metadata ?? []) && isset($selectedAlert->metadata['latitude']) && isset($selectedAlert->metadata['longitude']))
                                        {{ $selectedAlert->metadata['latitude'] }}, {{ $selectedAlert->metadata['longitude'] }}
                                        <a href="https://maps.google.com/?q={{ $selectedAlert->metadata['latitude'] }},{{ $selectedAlert->metadata['longitude'] }}" target="_blank" class="text-[var(--gold)] hover:text-[var(--gold-soft)] ml-2 text-xs">View on map →</a>
                                    @elseif($selectedAlert->machine && $selectedAlert->machine->last_location_latitude)
                                        {{ $selectedAlert->machine->last_location_latitude }}, {{ $selectedAlert->machine->last_location_longitude }}
                                        <a href="https://maps.google.com/?q={{ $selectedAlert->machine->last_location_latitude }},{{ $selectedAlert->machine->last_location_longitude }}" target="_blank" class="text-[var(--gold)] hover:text-[var(--gold-soft)] ml-2 text-xs">View on map →</a>
                                    @else
                                        <span class="text-[var(--sand)]/70">No coordinates available</span>
                                    @endif
                                </div>
                            </div>

                            <div>
                                <div class="text-xs font-semibold text-[var(--sand)] uppercase mb-1">Associated Geofence</div>
                                <div class="text-[var(--sand)]">
                                    @if(isset($selectedAlert->geofence) && $selectedAlert->geofence)
                                        {{ $selectedAlert->geofence->name }}
                                    @elseif(is_array($selectedAlert->metadata ?? []) && isset($selectedAlert->metadata['geofence_name']))
                                        {{ $selectedAlert->metadata['geofence_name'] }}
                                    @else
                                        <span class="text-[var(--sand)]/70">N/A</span>
                                    @endif
                                </div>
                            </div>

                            <div>
                                <div class="text-xs font-semibold text-[var(--sand)] uppercase mb-1">Mining Area</div>
                                <div class="text-[var(--sand)]">
                                    @if($selectedAlert->mineArea)
                                        <a href="{{ route('mine-areas.show', $selectedAlert->mineArea->id) }}" class="text-[var(--gold)] hover:text-[var(--gold-soft)]">{{ $selectedAlert->mineArea->name }}</a>
                                    @else
                                        <span class="text-[var(--sand)]/70">N/A</span>
                                    @endif
                                </div>
                            </div>

                            <div>
                                <div class="text-xs font-semibold text-[var(--sand)] uppercase mb-1">Asset</div>
                                <div class="text-[var(--sand)]">
                                    @if($selectedAlert->machine)
                                        <a href="{{ route('fleet.show', $selectedAlert->machine->id) }}" class="text-[var(--gold)] hover:text-[var(--gold-soft)]">{{ $selectedAlert->machine->name }}</a>
                                    @else
                                        <span class="text-[var(--sand)]/70">Unlinked asset</span>
                                    @endif
                                </div>
                            </div>

                            <div>
                                <div class="text-xs font-semibold text-[var(--sand)] uppercase mb-1">Timestamp</div>
                                <div class="text-[var(--sand)]">{{ $selectedAlert->created_at->format('M d, Y H:i:s') }}</div>
                            </div>

                            <div>
                                <div class="text-xs font-semibold text-[var(--sand)] uppercase mb-1">Management Chain</div>
                                <div class="text-[var(--sand)] text-sm">
                                    @php
                                        // Try to resolve management chain from alert data, else fall back to mineAreaManagers
                                        $chain = $selectedAlert->management_chain ?? null;
                                        $resolved = [];
                                        if (!$chain || !is_array($chain)) {
                                            $chain = [];
                                            if (!empty($mineAreaManagers) && is_array($mineAreaManagers)) {
                                                foreach ($mineAreaManagers as $m) {
                                                    $roleKey = strtolower(trim($m['role'] ?? ''));
                                                    $chain[$roleKey] = $m;
                                                }
                                            }
                                        }

                                        // Helper to find by role keywords
                                        $findRole = function($keywords) use ($chain) {
                                            foreach ($keywords as $k) {
                                                foreach ($chain as $key => $val) {
                                                    if (stripos($key, $k) !== false || (is_string($val['role'] ?? '') && stripos($val['role'], $k) !== false)) {
                                                        return $val;
                                                    }
                                                }
                                                // direct key check
                                                if (isset($chain[$k])) return $chain[$k];
                                            }
                                            return null;
                                        };

                                        $supervisor = $findRole(['supervisor','mine supervisor','mine_supervisor']);
                                        $opsManager = $findRole(['operations manager','operations_manager','operations','ops manager','ops']);
                                        $safetyOfficer = $findRole(['safety officer','safety_officer','safety']);
                                    @endphp

                                    <ul class="space-y-2">
                                        <li>
                                            <strong>Mine Supervisor:</strong>
                                            @if($supervisor)
                                                {{ $supervisor['name'] }} <span class="text-[var(--sand)]/70">({{ $supervisor['role'] }})</span> — <a href="mailto:{{ $supervisor['email'] }}" class="text-[var(--gold)]">{{ $supervisor['email'] }}</a>
                                            @else
                                                <span class="text-[var(--sand)]/70">Unassigned</span>
                                            @endif
                                        </li>
                                        <li>
                                            <strong>Operations Manager:</strong>
                                            @if($opsManager)
                                                {{ $opsManager['name'] }} <span class="text-[var(--sand)]/70">({{ $opsManager['role'] }})</span> — <a href="mailto:{{ $opsManager['email'] }}" class="text-[var(--gold)]">{{ $opsManager['email'] }}</a>
                                            @else
                                                <span class="text-[var(--sand)]/70">Unassigned</span>
                                            @endif
                                        </li>
                                        <li>
                                            <strong>Safety Officer:</strong>
                                            @if($safetyOfficer)
                                                {{ $safetyOfficer['name'] }} <span class="text-[var(--sand)]/70">({{ $safetyOfficer['role'] }})</span> — <a href="mailto:{{ $safetyOfficer['email'] }}" class="text-[var(--gold)]">{{ $safetyOfficer['email'] }}</a>
                                            @else
                                                <span class="text-[var(--sand)]/70">Unassigned</span>
                                            @endif
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    @else
                        <div>
                            <div class="text-xs font-semibold text-[var(--sand)] uppercase mb-1">Location</div>
                            <div class="text-[var(--sand)] text-sm">
                                @if(is_array($selectedAlert->metadata ?? []) && isset($selectedAlert->metadata['latitude']) && isset($selectedAlert->metadata['longitude']))
                                    {{ $selectedAlert->metadata['latitude'] }}, {{ $selectedAlert->metadata['longitude'] }}
                                    <a href="https://maps.google.com/?q={{ $selectedAlert->metadata['latitude'] }},{{ $selectedAlert->metadata['longitude'] }}" target="_blank" class="text-[var(--gold)] hover:text-[var(--gold-soft)] ml-2 text-xs">View on map →</a>
                                @elseif($selectedAlert->machine && $selectedAlert->machine->last_location_latitude)
                                    {{ $selectedAlert->machine->last_location_latitude }}, {{ $selectedAlert->machine->last_location_longitude }}
                                    <a href="https://maps.google.com/?q={{ $selectedAlert->machine->last_location_latitude }},{{ $selectedAlert->machine->last_location_longitude }}" target="_blank" class="text-[var(--gold)] hover:text-[var(--gold-soft)] ml-2 text-xs">View on map →</a>
                                @else
                                    <span class="text-[var(--sand)]/70">No coordinates available</span>
                                @endif
                            </div>
                        </div>

                        <div>
                            <div class="text-xs font-semibold text-[var(--sand)] uppercase mb-1">Geofence</div>
                            <div class="text-[var(--sand)]">
                                @if(isset($selectedAlert->geofence) && $selectedAlert->geofence)
                                    {{ $selectedAlert->geofence->name }}
                                @elseif(is_array($selectedAlert->metadata ?? []) && isset($selectedAlert->metadata['geofence_name']))
                                    {{ $selectedAlert->metadata['geofence_name'] }}
                                @else
                                    <span class="text-[var(--sand)]/70">N/A</span>
                                @endif
                            </div>
                        </div>

                        <div>
                            <div class="text-xs font-semibold text-[var(--sand)] uppercase mb-1">Mine Area</div>
                            <div class="text-[var(--sand)]">
                                @if($selectedAlert->mineArea)
                                    <a href="{{ route('mine-areas.show', $selectedAlert->mineArea->id) }}" class="text-[var(--gold)] hover:text-[var(--gold-soft)]">{{ $selectedAlert->mineArea->name }}</a>
                                @else
                                    <span class="text-[var(--sand)]/70">N/A</span>
                                @endif
                            </div>
                        </div>

                    <div>
                        <div class="text-xs font-semibold text-[var(--sand)] uppercase mb-1">Mine Area Managers</div>
                        <div class="text-[var(--sand)] text-sm">
                            @if(!empty($mineAreaManagers) && count($mineAreaManagers) > 0)
                                <ul class="list-disc pl-5">
                                    @foreach($mineAreaManagers as $mgr)
                                        <li class="text-[var(--sand)]">{{ $mgr['name'] }} <span class="text-[var(--sand)]/70">({{ $mgr['role'] }})</span> — <a href="mailto:{{ $mgr['email'] }}" class="text-[var(--gold)]">{{ $mgr['email'] }}</a></li>
                                    @endforeach
                                </ul>
                            @else
                                <span class="text-[var(--sand)]/70">No managers assigned</span>
                            @endif
                        </div>
                    </div>
                </div>

                    @endif

                <div class="flex gap-2 justify-end mt-6 pt-4 border-t border-[var(--line)]">
                    <button 
                        wire:click="closeDetails"
                        class="px-4 py-2 bg-white/5 text-[var(--stone)] rounded hover:bg-white/10 transition"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Dismiss Confirmation Modal -->
    @if($showDismissConfirm && isset($pendingDismissAlertId))
        @php $pendingAlert = \App\Models\Alert::find($pendingDismissAlertId); @endphp
        <div data-app-overlay class="fixed inset-0 z-[1100] bg-black/60 flex items-center justify-center p-4 overflow-y-auto">
            <div class="bg-[var(--ink-soft)] rounded-lg border border-[var(--line)] p-6 w-96 max-h-80 overflow-y-auto">
                <div class="mb-4">
                    <h3 class="text-lg font-semibold text-[var(--stone)]">Confirm Dismissal</h3>
                    <p class="text-sm text-[var(--sand)] mt-2">This issue remains unresolved. Dismissing may affect operational safety. Continue?</p>
                </div>

                <div class="space-y-3">
                    <div class="text-sm text-[var(--sand)]">
                        <strong>{{ $pendingAlert?->title }}</strong>
                        <div class="text-[var(--sand)] text-xs mt-1">{{ $pendingAlert?->description }}</div>
                    </div>
                </div>

                <div class="flex gap-2 justify-end mt-6 pt-4 border-t border-[var(--line)]">
                    <button wire:click="cancelDismiss" class="px-4 py-2 bg-white/5 text-[var(--stone)] rounded hover:bg-white/10">Cancel</button>
                    <button wire:click="confirmDismiss('confirm')" class="px-4 py-2 bg-red-600 text-[var(--stone)] rounded hover:bg-red-500">Confirm Dismiss</button>
                </div>
            </div>
        </div>
    @endif
</div>

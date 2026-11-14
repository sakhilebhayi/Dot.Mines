{{-- 30s, visible-only: KPIs/alerts change on the 30s alert-generation
     cadence at fastest, and background tabs should cost the server nothing.
     (Was an unconditional 10s -- 90 wasted requests per real data change.) --}}
<div class="animate-fade-in" wire:poll.visible.30s="loadDashboardData">
    <!-- Loading Spinner -->
    @if ($isLoading)
        <div class="flex justify-center items-center h-96">
            <svg class="animate-spin h-12 w-12 text-[var(--gold)]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
            </svg>
        </div>
        <script nonce="{{ request()->attributes->get('csp_nonce') }}">window.scrollTo(0,0);</script>
    @else
        {{-- The greeting, the setup steps and the fleet counts live in the
             landing above this board (livewire/dashboard-landing.blade.php),
             which is in the first response instead of behind the lazy load. --}}

    <livewire:fuel-cushion />

    <!-- Fleet Dispatch: live operational flow derived from real telemetry
         (speed, engine state, freshness) and open geofence entries (typed
         zones). States are conservative -- loading/dumping are only claimed
         inside a zone of that type; stale machines say so. Polls every 30s. -->
    <div class="bg-[var(--ink-soft)] rounded-lg shadow-lg p-6 border border-[var(--line)] mb-8"
        wire:poll.visible.30s>
        @php $dispatch = $this->fleetDispatch; @endphp
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <h2 class="text-xl font-display font-semibold text-[var(--stone)] flex items-center gap-2">
                Fleet Dispatch
                <x-freshness :timestamp="$this->telemetryFreshAt" :stale-after="1800" label="Telemetry" class="font-normal" />
            </h2>
            <div class="flex flex-wrap gap-2 text-xs">
                @foreach ([
                    'loading' => ['Loading', 'bg-green-500/15 text-green-400'],
                    'dumping' => ['Dumping', 'bg-orange-500/15 text-orange-400'],
                    'travelling' => ['Travelling', 'bg-blue-500/15 text-blue-400'],
                    'idling' => ['Idling', 'bg-yellow-500/15 text-yellow-400'],
                    'parked' => ['Parked', 'bg-gray-500/15 text-[var(--sand)]'],
                    'no_telemetry' => ['No telemetry', 'bg-red-500/15 text-red-400'],
                ] as $key => [$label, $chip])
                    @if ($dispatch['counts'][$key] > 0)
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full font-medium {{ $chip }}">
                            {{ $dispatch['counts'][$key] }} {{ $label }}
                        </span>
                    @endif
                @endforeach
            </div>
        </div>

        @php
            $workingStates = $dispatch['counts']['loading'] + $dispatch['counts']['dumping']
                + $dispatch['counts']['travelling'] + $dispatch['counts']['idling'];
        @endphp
        @if (count($dispatch['machines']) > 0 && $workingStates === 0)
            {{-- A parked fleet is a finished shift, not a failure. --}}
            <p class="text-[var(--sand)]/80 text-sm mb-3">
                Fleet is quiet &mdash; no machines are working right now. Live states appear here as soon as a machine starts up.
            </p>
        @endif

        @if (count($dispatch['machines']) === 0)
            <p class="text-[var(--sand)] text-sm py-4 text-center">No machines in the fleet yet. <a href="{{ route('fleet') }}" class="text-[var(--gold)] hover:text-[var(--gold-soft)]">Add machines</a> to see live dispatch.</p>
        @else
            @php
                $byState = collect($dispatch['machines'])->groupBy('state');

                /* One visual chip per machine: status dot + name + the most
                   useful secondary fact for that state (zone > speed > age).
                   The classification basis rides in the tooltip, and the
                   whole chip links to the machine without losing context. */
                $dispatchChip = function ($row, string $dotClass) {
                    $machine = $row['machine'];
                    $sub = $row['zone']
                        ?? ($row['speed'] !== null && $row['speed'] >= 1 ? number_format($row['speed'], 0).' km/h' : null)
                        ?? $row['updated_at']?->diffForHumans(short: true)
                        ?? 'no report';

                    return [$machine, $sub, $dotClass];
                };
            @endphp

            {{-- The pit cycle, left to right: what the operation actually
                 looks like at a glance (brief §14-15). Loading/dumping are
                 only ever claimed inside a typed zone; travel direction
                 (haul vs return) is not distinguishable from the available
                 telemetry, so one honest Haul Route lane carries all
                 movement rather than guessing outbound vs inbound. --}}
            <div class="flex flex-col md:flex-row items-stretch gap-2 mb-4">
                @foreach ([
                    'loading' => ['Loading Area', 'border-green-500/30', 'text-green-400', 'bg-green-400'],
                    'travelling' => ['Haul Route', 'border-blue-500/30', 'text-blue-400', 'bg-blue-400'],
                    'dumping' => ['Dump Area', 'border-orange-500/30', 'text-orange-400', 'bg-orange-400'],
                ] as $state => [$laneLabel, $laneBorder, $laneText, $dot])
                    @if (! $loop->first)
                        <div class="flex items-center justify-center text-[var(--sand)]/50 shrink-0" aria-hidden="true">
                            <svg class="w-5 h-5 rotate-90 md:rotate-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
                        </div>
                    @endif
                    <div class="flex-1 rounded-lg border {{ $laneBorder }} bg-[var(--ink)]/40 p-3 min-h-[7rem]">
                        <p class="text-xs font-semibold uppercase tracking-wider {{ $laneText }} mb-2 flex items-center justify-between">
                            {{ $laneLabel }}
                            <span class="text-[var(--sand)] font-normal normal-case tracking-normal">{{ ($byState[$state] ?? collect())->count() }}</span>
                        </p>
                        <div class="flex flex-wrap gap-1.5">
                            @forelse ($byState[$state] ?? [] as $row)
                                @php [$machine, $sub, $dotClass] = $dispatchChip($row, $dot); @endphp
                                <a href="{{ route('fleet.show', $machine) }}" wire:key="dispatch-{{ $machine->id }}"
                                   title="{{ $row['basis'] }}"
                                   class="inline-flex items-center gap-1.5 px-2 py-1 rounded-md bg-[var(--ink-soft)] border border-[var(--line)] hover:border-[var(--gold)] transition-colors">
                                    <span class="size-1.5 rounded-full {{ $dotClass }}" aria-hidden="true"></span>
                                    <span class="text-xs font-medium text-[var(--stone)]">{{ $machine->name }}</span>
                                    <span class="text-[10px] text-[var(--sand)]">{{ $sub }}</span>
                                </a>
                            @empty
                                <span class="text-xs text-[var(--sand)]/50 py-1">None right now</span>
                            @endforelse
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Off-cycle machines: idle, parked, silent. Same chips, muted lanes. --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                @foreach ([
                    'idling' => ['Idle', 'text-yellow-400', 'bg-yellow-400'],
                    'parked' => ['Parked', 'text-[var(--sand)]', 'bg-gray-400'],
                    'no_telemetry' => ['No telemetry', 'text-red-400', 'bg-red-400'],
                ] as $state => [$laneLabel, $laneText, $dot])
                    <div class="rounded-lg border border-[var(--line)] bg-[var(--ink)]/40 p-3">
                        <p class="text-xs font-semibold uppercase tracking-wider {{ $laneText }} mb-2 flex items-center justify-between">
                            {{ $laneLabel }}
                            <span class="text-[var(--sand)] font-normal normal-case tracking-normal">{{ ($byState[$state] ?? collect())->count() }}</span>
                        </p>
                        <div class="flex flex-wrap gap-1.5">
                            @forelse ($byState[$state] ?? [] as $row)
                                @php [$machine, $sub, $dotClass] = $dispatchChip($row, $dot); @endphp
                                <a href="{{ route('fleet.show', $machine) }}" wire:key="dispatch-{{ $machine->id }}"
                                   title="{{ $row['basis'] }}"
                                   class="inline-flex items-center gap-1.5 px-2 py-1 rounded-md bg-[var(--ink-soft)] border border-[var(--line)] hover:border-[var(--gold)] transition-colors">
                                    <span class="size-1.5 rounded-full {{ $dotClass }}" aria-hidden="true"></span>
                                    <span class="text-xs font-medium text-[var(--stone)]">{{ $machine->name }}</span>
                                    <span class="text-[10px] text-[var(--sand)]">{{ $sub }}</span>
                                </a>
                            @empty
                                <span class="text-xs text-[var(--sand)]/50 py-1">None right now</span>
                            @endforelse
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <!-- Mine Activity: compact feed, linking to the full stream -->
    @if($this->recentFeed->isNotEmpty())
        <div class="bg-[var(--ink-soft)] rounded-lg shadow-lg p-6 border border-[var(--line)] mb-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-display font-semibold text-[var(--stone)]">Mine Activity</h2>
                <a href="{{ route('feed') }}" class="text-[var(--gold)] text-sm hover:underline">View All Activity</a>
            </div>
            <div class="space-y-2">
                @foreach($this->recentFeed as $feedItem)
                    <div wire:key="dash-feed-{{ $feedItem->id }}" class="flex items-baseline gap-3 text-sm">
                        <span class="text-[var(--sand)] text-xs tabular-nums shrink-0" title="{{ $feedItem->occurred_at->toDayDateTimeString() }}">{{ $feedItem->occurred_at->format('H:i') }}</span>
                        <span class="text-[var(--stone)] truncate">{{ $feedItem->title }}</span>
                        <span class="text-[var(--sand)] text-xs shrink-0 ml-auto">{{ \App\Models\FeedItem::CATEGORIES[$feedItem->category] ?? ucfirst($feedItem->category) }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Recent Alerts -->
        <div class="lg:col-span-2 bg-[var(--ink-soft)] rounded-lg shadow-lg p-6 border border-[var(--line)]">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl font-display font-semibold text-[var(--stone)] flex items-center gap-2">
                    
                    Recent Alerts
                </h2>
                <a href="{{ route('alerts') }}" class="text-[var(--gold)] hover:text-[var(--gold-soft)] text-sm font-medium transition-colors">
                    View All →
                </a>
            </div>

            @if (count($recentAlerts) > 0)
                <div class="space-y-3">
                    @foreach ($recentAlerts as $alert)
                        {{-- Priority is carried by the labelled chip, not a coloured edge. --}}
                        <div class="flex items-start justify-between p-4 bg-white/5 rounded-lg hover:bg-white/[0.07] transition-colors duration-200 border border-[var(--line)]">
                            <div class="flex items-start gap-4 flex-1">
                                <div class="mt-1">
                                    @if ($alert['priority'] === 'high')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-500/20 text-red-400">
                                            HIGH
                                        </span>
                                    @elseif ($alert['priority'] === 'medium')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-500/20 text-yellow-400">
                                            MEDIUM
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-500/20 text-blue-400">
                                            LOW
                                        </span>
                                    @endif
                                </div>
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-[var(--stone)]">{{ $alert['title'] }}</p>
                                    <p class="text-xs text-[var(--sand)] mt-1">
                                        {{ ucfirst(str_replace('_', ' ', $alert['type'])) }}@if ($alert['machine']) · {{ $alert['machine'] }}@endif
                                    </p>
                                    <p class="text-xs text-[var(--sand)]/70 mt-1">{{ $alert['created_at'] }}</p>
                                </div>
                            </div>
                            @if ($alert['status'] === 'active')
                                <button wire:click="acknowledgeAlert({{ $alert['id'] }})"
                                    class="px-3 py-1 text-xs border border-[var(--gold)] text-[var(--gold-soft)] hover:bg-[var(--gold)] hover:text-[var(--ink)] rounded transition-colors duration-200 font-medium focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--gold)]">
                                    Acknowledge
                                </button>
                            @else
                                <span class="px-3 py-1 text-xs bg-green-500/15 text-green-400 rounded font-medium">
                                    {{ ucfirst($alert['status']) }}
                                </span>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-12">
                    <h3 class="text-lg font-semibold text-[var(--stone)] mb-2">All Clear!</h3>
                    <p class="text-[var(--sand)] text-sm">No active alerts at the moment. <a href="{{ route('alerts') }}" class="text-[var(--gold)] hover:text-[var(--gold-soft)]">View all alerts</a>.</p>
                </div>
            @endif
        </div>

        <!-- Machine Status & Quick Actions -->
        <div class="bg-[var(--ink-soft)] rounded-lg shadow-lg p-6 border border-[var(--line)] mt-6 lg:mt-0">
            <h2 class="text-xl font-display font-semibold text-[var(--stone)] mb-6 flex items-center gap-2">
                
                Fleet Status
            </h2>

            @if (count($machineStatus) > 0)
                <div class="space-y-4">
                    @foreach ($machineStatus as $status)
                        <div>
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-sm font-medium text-[var(--sand)] flex items-center gap-2">
                                    @if($status['status'] === 'Active')
                                        <span class="w-2 h-2 bg-green-500 rounded-full"></span>
                                    @elseif($status['status'] === 'Idle')
                                        <span class="w-2 h-2 bg-blue-500 rounded-full"></span>
                                    @elseif($status['status'] === 'Maintenance')
                                        <span class="w-2 h-2 bg-red-500 rounded-full"></span>
                                    @else
                                        <span class="w-2 h-2 bg-gray-500 rounded-full"></span>
                                    @endif
                                    {{ $status['status'] }}
                                </span>
                                <span class="text-sm font-bold text-[var(--stone)]">{{ $status['count'] }}</span>
                            </div>
                            <div class="w-full bg-white/10 rounded-full h-3 overflow-hidden">
                                @php
                                    $percentage = ($status['count'] / max($totalMachines, 1)) * 100;
                                    $color = match($status['status']) {
                                        'Active' => 'bg-green-500',
                                        'Idle' => 'bg-blue-500',
                                        'Maintenance' => 'bg-red-500',
                                        default => 'bg-gray-500',
                                    };
                                @endphp
                                <div class="{{ $color }} h-3 rounded-full transition-all duration-1000 ease-out" style="width: {{ $percentage }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-8">
                    <p class="text-[var(--sand)] text-sm">No machine data available. <a href="{{ route('fleet') }}" class="text-[var(--gold)] hover:text-[var(--gold-soft)]">Add a machine</a>.</p>
                </div>
            @endif

            <!-- Quick Actions -->
            <div class="mt-8 pt-6 border-t border-[var(--line)]">
                <h3 class="text-sm font-semibold text-[var(--sand)] mb-3 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                    Quick Actions
                </h3>
                <div class="space-y-2">
                    <a href="{{ route('fleet') }}"
                        class="block w-full px-4 py-3 bg-white/5 hover:bg-white/10 border border-[var(--line)] text-[var(--stone)] text-sm rounded-lg transition-all duration-200 text-center font-medium">
                        <span class="flex items-center justify-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                            View Fleet
                        </span>
                    </a>
                    <a href="{{ route('map') }}"
                        class="block w-full px-4 py-3 bg-white/5 hover:bg-white/10 border border-[var(--line)] text-[var(--stone)] text-sm rounded-lg transition-all duration-200 text-center font-medium">
                        <span class="flex items-center justify-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6 3m-6-3v-13m6 3l5.553-2.776A1 1 0 0121 5.618v10.764a1 1 0 01-1.447.894L15 20m0-13v13"/>
                            </svg>
                            Live Map
                        </span>
                    </a>
                    <a href="{{ route('ai-optimization') }}"
                        class="block w-full px-4 py-3 bg-white/5 hover:bg-white/10 border border-[var(--line)] text-[var(--stone)] text-sm rounded-lg transition-all duration-200 text-center font-medium">
                        <span class="flex items-center justify-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                            </svg>
                            AI Insights
                        </span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    @endif
</div>

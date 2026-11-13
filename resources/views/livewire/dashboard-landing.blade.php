{{-- The signed-in landing: logo, who and where, the state in words, one
     primary action, then the readings. Not lazy, so all of it is in the
     first response. Re-reads on the board's own 30s visible-only cadence.
     No entrance motion and no counting figures: every number here is the
     current count, shown as it is. --}}
<div wire:poll.visible.30s="loadState">
    @if ($teamName !== '')
        @php
            $primary = $this->primaryAction;
            $hour = (int) now()->format('H');
            $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
            $setupOpen = $totalMineAreas === 0 || $totalMachines === 0;
            $linkClass = 'underline decoration-[var(--line)] underline-offset-4 hover:text-[var(--gold-soft)] hover:decoration-[var(--gold)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--gold)] rounded-sm';
        @endphp

        <section aria-labelledby="landing-headline"
                 class="mb-8 rounded-lg border border-[var(--line)] bg-[var(--ink-soft)]">
            {{-- Identity row: the real logo file, and the way back. --}}
            <div class="flex flex-wrap items-center justify-between gap-x-6 gap-y-1 border-b border-[var(--line)] px-4 sm:px-6">
                <img src="{{ asset('images/logo-light.png') }}" alt="Dot.Mines"
                     width="1181" height="886" data-landing-logo
                     class="h-20 w-auto sm:h-24">
                <nav aria-label="Dot ecosystem" class="flex items-center gap-5 text-sm text-[var(--sand)]">
                    <a href="{{ $this->infodotUrl }}/home" class="inline-flex min-h-11 items-center {{ $linkClass }}">InfoDot</a>
                    <a href="{{ $this->infodotUrl }}/platforms" class="inline-flex min-h-11 items-center {{ $linkClass }}">Platforms</a>
                </nav>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1.55fr)_minmax(0,1fr)]">
                <div class="min-w-0 p-4 sm:p-6 lg:p-8">
                    <h2 id="landing-headline"
                        class="max-w-[22ch] text-3xl font-display font-semibold leading-[1.1] text-[var(--stone)] sm:text-4xl [overflow-wrap:anywhere] [text-wrap:balance]">
                        Know where every machine is and what it is doing.
                    </h2>

                    <p class="mt-4 max-w-[58ch] text-lg leading-snug text-[var(--stone)]">
                        {{ $greeting }}, {{ $firstName }}. At <span class="font-semibold">{{ $teamName }}</span>:
                        <span data-landing-state>{{ lcfirst($this->stateSentence) }}</span>
                    </p>

                    <div class="mt-6 flex flex-wrap items-center gap-x-6 gap-y-2">
                        <a href="{{ route($primary['route']) }}" data-primary-action
                           class="inline-flex min-h-12 items-center rounded-lg bg-[var(--gold)] px-6 text-base font-display font-semibold text-[var(--ink)] transition-colors duration-150 hover:bg-[var(--gold-soft)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--stone)] focus-visible:ring-offset-2 focus-visible:ring-offset-[var(--ink-soft)]">
                            {{ $primary['label'] }}
                        </a>
                        <ul class="flex flex-wrap items-center gap-x-5 text-sm text-[var(--sand)]">
                            @foreach ([['fleet', 'Fleet'], ['map', 'Live map'], ['alerts', 'Alerts']] as [$routeName, $label])
                                @if ($routeName !== $primary['route'])
                                    <li><a href="{{ route($routeName) }}" class="inline-flex min-h-11 items-center {{ $linkClass }}">{{ $label }}</a></li>
                                @endif
                            @endforeach
                        </ul>
                    </div>

                    @if ($setupOpen)
                        {{-- Honest empty state: what is missing, in order, and who can fix it. --}}
                        <div class="mt-6 max-w-[58ch] border-t border-[var(--line)] pt-5" data-landing-setup>
                            <p class="text-sm text-[var(--sand)]">
                                @if ($canCreateMineAreas || $canCreateMachines)
                                    Setup is not finished. What is left:
                                @else
                                    Setup is not finished. An administrator or fleet manager still has to do what is left.
                                @endif
                            </p>
                            <ol class="mt-3 space-y-1 text-sm">
                                <li class="flex items-baseline justify-between gap-4">
                                    <a href="{{ route('mine-areas') }}" class="inline-flex min-h-11 items-center text-[var(--stone)] {{ $linkClass }}">Create a mine area</a>
                                    <span class="font-mono text-xs {{ $totalMineAreas > 0 ? 'text-[var(--sand)]' : 'text-[var(--gold-soft)]' }}">{{ $totalMineAreas > 0 ? 'done' : 'to do' }}</span>
                                </li>
                                <li class="flex items-baseline justify-between gap-4">
                                    <a href="{{ route('fleet') }}" class="inline-flex min-h-11 items-center text-[var(--stone)] {{ $linkClass }}">Add your first machine</a>
                                    <span class="font-mono text-xs {{ $totalMachines > 0 ? 'text-[var(--sand)]' : 'text-[var(--gold-soft)]' }}">{{ $totalMachines > 0 ? 'done' : 'to do' }}</span>
                                </li>
                                <li class="flex items-baseline justify-between gap-4">
                                    <a href="{{ route('teams.show', auth()->user()->currentTeam) }}" class="inline-flex min-h-11 items-center text-[var(--stone)] {{ $linkClass }}">Invite your team</a>
                                    <span class="font-mono text-xs text-[var(--sand)]">any time</span>
                                </li>
                            </ol>
                        </div>
                    @endif

                    <p class="mt-6 max-w-[58ch] text-sm text-[var(--sand)]">
                        Dot.Mines reads position, engine state, fuel and alerts from your fleet's telemetry, so a shift is run on readings.
                    </p>
                </div>

                {{-- Readings: one list sharing the panel's edges, each row a way
                     into the page that owns the figure. --}}
                <div class="min-w-0 border-t border-[var(--line)] lg:border-l lg:border-t-0">
                    <div class="flex items-baseline justify-between gap-3 px-4 pb-2 pt-4 sm:px-6">
                        <h3 class="text-sm font-semibold text-[var(--stone)]">Readings</h3>
                        <span class="font-mono text-xs text-[var(--sand)]">{{ now()->format('D j M Y') }}</span>
                    </div>
                    <ul class="divide-y divide-[var(--line)] border-t border-[var(--line)]">
                        @foreach ([
                            ['fleet', 'Machines', 'machines', $totalMachines, false],
                            ['fleet', 'Marked active', 'marked-active', $activeMachines, false],
                            ['alerts', 'Active alerts', 'active-alerts', $activeAlerts, $activeAlerts > 0],
                            ['geofences', 'Geofences', 'geofences', $totalGeofences, false],
                            ['mine-areas', 'Mine areas', 'mine-areas', $totalMineAreas, false],
                        ] as [$routeName, $label, $key, $value, $signal])
                            <li>
                                <a href="{{ route($routeName) }}"
                                   class="flex min-h-11 items-baseline justify-between gap-4 px-4 py-2.5 transition-colors duration-150 hover:bg-white/5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[var(--gold)] sm:px-6">
                                    <span class="text-sm text-[var(--sand)]">{{ $label }}</span>
                                    <span class="font-mono text-lg tabular-nums {{ $signal ? 'text-[var(--gold-soft)]' : 'text-[var(--stone)]' }}"
                                          data-reading="{{ $key }}">{{ $value }}</span>
                                </a>
                            </li>
                        @endforeach
                        <li class="flex min-h-11 items-baseline justify-between gap-4 px-4 py-2.5 sm:px-6">
                            <span class="text-sm text-[var(--sand)]">Telemetry</span>
                            <x-freshness :timestamp="$this->telemetryFreshAt" :stale-after="1800" label="Updated" class="font-mono" />
                        </li>
                    </ul>
                </div>
            </div>
        </section>
    @endif
</div>

<div class="max-w-3xl mx-auto px-4 py-8" wire:poll.visible.30s>
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-semibold text-[var(--stone)]">System Health</h1>
        <x-freshness :timestamp="now()" />
    </div>

    <div class="space-y-3">
        @foreach ($this->checks as $check)
            <div wire:key="check-{{ $loop->index }}"
                class="flex items-center justify-between gap-4 bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg px-4 py-3">
                <div class="flex items-center gap-3">
                    @if ($check['state'] === 'healthy')
                        <span class="inline-block w-2.5 h-2.5 rounded-full bg-emerald-400" aria-hidden="true"></span>
                    @elseif ($check['state'] === 'warning')
                        <span class="inline-block w-2.5 h-2.5 rounded-full bg-amber-400" aria-hidden="true"></span>
                    @else
                        <span class="inline-block w-2.5 h-2.5 rounded-full bg-red-500" aria-hidden="true"></span>
                    @endif
                    <span class="text-sm font-medium text-[var(--stone)]">{{ $check['label'] }}</span>
                </div>
                <span class="text-xs text-[var(--sand)] text-right">{{ $check['detail'] }}</span>
            </div>
        @endforeach
    </div>

    @php $api = $this->apiHealth; @endphp
    @if ($api['integration'])
        <h2 class="text-sm font-semibold text-[var(--stone)] mt-8 mb-3 uppercase tracking-wide">Integration API — last sync</h2>
        <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg px-4 py-3 space-y-2">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <span class="text-sm font-medium text-[var(--stone)]">{{ ucfirst($api['integration']->provider) }} · {{ $api['integration']->status }}</span>
                <x-freshness :timestamp="$api['integration']->last_sync_at" :stale-after="2700" label="Synced" />
            </div>
            @if ($api['stats'] !== [])
                <dl class="grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-1 text-xs text-[var(--sand)]">
                    @if (($api['stats']['failed'] ?? false))
                        <div class="col-span-full text-red-400">Last run FAILED after {{ number_format($api['stats']['duration_ms'] ?? 0) }} ms — see the error below.</div>
                    @else
                        <div><dt class="inline">Duration:</dt> <dd class="inline text-[var(--stone)]">{{ number_format($api['stats']['duration_ms'] ?? 0) }} ms</dd></div>
                        <div><dt class="inline">Machines received:</dt> <dd class="inline text-[var(--stone)]">{{ $api['stats']['machines_received'] ?? '—' }}</dd></div>
                        <div><dt class="inline">Machines synced:</dt> <dd class="inline text-[var(--stone)]">{{ $api['stats']['machines_synced'] ?? '—' }}</dd></div>
                        <div><dt class="inline">Production records:</dt> <dd class="inline text-[var(--stone)]">{{ $api['stats']['production_records_total'] ?? '—' }} ({{ ($api['stats']['production_records_delta'] ?? 0) >= 0 ? '+' : '' }}{{ $api['stats']['production_records_delta'] ?? 0 }})</dd></div>
                        <div><dt class="inline">Deep sync:</dt> <dd class="inline text-[var(--stone)]">{{ ($api['stats']['deep_sync'] ?? false) ? 'yes' : 'no' }}</dd></div>
                    @endif
                </dl>
            @else
                <p class="text-xs text-[var(--sand)]/70">No per-run statistics recorded yet — they appear after the next sync.</p>
            @endif
            @if ($api['integration']->last_error)
                <p class="text-xs text-red-400">Last error ({{ $api['integration']->last_error_at?->diffForHumans() ?? 'time unknown' }}): {{ $api['integration']->last_error }}</p>
            @endif
        </div>
    @endif

    @php $recon = $this->reconciliation; @endphp
    @if ($recon)
        <h2 class="text-sm font-semibold text-[var(--stone)] mt-8 mb-3 uppercase tracking-wide">Production reconciliation — {{ $recon['date'] }}</h2>
        <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg px-4 py-3 mb-3 text-xs text-[var(--sand)]">
            {{ $recon['totals']['machines'] }} machine(s) · {{ number_format($recon['totals']['loads']) }} loads · {{ number_format($recon['totals']['tonnes'], 1) }} t stored for the day
        </div>
        <div class="space-y-3">
            @foreach ($recon['checks'] as $check)
                <div wire:key="recon-{{ $loop->index }}"
                    class="flex items-center justify-between gap-4 bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg px-4 py-3">
                    <div class="flex items-center gap-3">
                        @if ($check['state'] === 'healthy')
                            <span class="inline-block w-2.5 h-2.5 rounded-full bg-emerald-400" aria-hidden="true"></span>
                        @elseif ($check['state'] === 'warning')
                            <span class="inline-block w-2.5 h-2.5 rounded-full bg-amber-400" aria-hidden="true"></span>
                        @else
                            <span class="inline-block w-2.5 h-2.5 rounded-full bg-red-500" aria-hidden="true"></span>
                        @endif
                        <span class="text-sm font-medium text-[var(--stone)]">{{ $check['label'] }}</span>
                    </div>
                    <span class="text-xs text-[var(--sand)] text-right">{{ $check['detail'] }}</span>
                </div>
            @endforeach
        </div>
    @endif

    <p class="text-xs text-[var(--sand)]/70 mt-6">
        Auto-refreshes every 30 seconds while visible. “Realtime push: not configured” is a normal state — polling covers freshness until Pusher keys are installed.
    </p>
</div>

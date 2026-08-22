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

    <p class="text-xs text-[var(--sand)]/70 mt-6">
        Auto-refreshes every 30 seconds while visible. “Realtime push: not configured” is a normal state — polling covers freshness until Pusher keys are installed.
    </p>
</div>

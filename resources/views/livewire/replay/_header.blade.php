{{-- Page header (R7: split out of fleet-movement-replay.blade.php) --}}
<!-- Header -->
<div class="bg-[var(--ink-soft)] border-b border-[var(--line)] p-6">
    <div class="max-w-7xl mx-auto">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold text-[var(--stone)]">Fleet Movement Replay</h1>
                <p class="text-[var(--sand)] mt-1">Review historical vehicle movements and routes</p>
                <p class="text-xs text-[var(--sand)]/70 mt-1">Playback steps through the machine's real recorded GPS readings — positions between readings are never invented.</p>
            </div>
            <a href="{{ route('fleet') }}" class="px-4 py-2 bg-white/5 hover:bg-white/10 border border-[var(--line)] text-[var(--stone)] rounded-lg transition-colors">
                Back to Fleet
            </a>
        </div>
    </div>
</div>


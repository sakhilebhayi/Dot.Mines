<div>
@if(($cushion['available'] ?? false))
    <div class="bg-[var(--ink-soft)] rounded-lg shadow-lg p-6 border border-[var(--line)] mb-8">
        <div class="flex items-center gap-2 mb-3">
            <span class="text-lg">⛽</span>
            <h3 class="text-sm font-bold text-[var(--stone)] uppercase tracking-wide">Fuel Cushion</h3>
        </div>

        @if($cushion['has_no_recent_consumption'])
            <div class="text-xl font-bold text-[var(--sand)] mb-1">
                No recent fuel consumption recorded.
            </div>
        @else
            <div class="text-2xl font-bold text-green-400 mb-1">
                Approximately {{ $cushion['days'] }} day{{ $cushion['days'] === 1 ? '' : 's' }} of reserves at current usage.
            </div>
        @endif

        <p class="text-xs text-[var(--sand)]">{{ $cushion['basis'] }}</p>

        @if($cushion['what_if'])
            <p class="text-xs text-[var(--sand)] mt-2">
                If <strong>{{ $cushion['what_if']['machine_name'] }}</strong>'s consumption were removed, this would extend to approximately {{ $cushion['what_if']['days_without_machine'] }} day{{ $cushion['what_if']['days_without_machine'] === 1 ? '' : 's' }}.
            </p>
        @endif
    </div>
@endif
</div>

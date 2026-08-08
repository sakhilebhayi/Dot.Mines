<div>
@if(($cushion['available'] ?? false))
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 border border-gray-200 dark:border-gray-700 mb-8">
        <div class="flex items-center gap-2 mb-3">
            <span class="text-lg">⛽</span>
            <h3 class="text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wide">Fuel Cushion</h3>
        </div>

        @if($cushion['has_no_recent_consumption'])
            <div class="text-xl font-bold text-gray-700 dark:text-gray-200 mb-1">
                No recent fuel consumption recorded.
            </div>
        @else
            <div class="text-2xl font-bold text-green-600 dark:text-green-400 mb-1">
                Approximately {{ $cushion['days'] }} day{{ $cushion['days'] === 1 ? '' : 's' }} of reserves at current usage.
            </div>
        @endif

        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $cushion['basis'] }}</p>

        @if($cushion['what_if'])
            <p class="text-xs text-gray-600 dark:text-gray-300 mt-2">
                If <strong>{{ $cushion['what_if']['machine_name'] }}</strong>'s consumption were removed, this would extend to approximately {{ $cushion['what_if']['days_without_machine'] }} day{{ $cushion['what_if']['days_without_machine'] === 1 ? '' : 's' }}.
            </p>
        @endif
    </div>
@endif
</div>

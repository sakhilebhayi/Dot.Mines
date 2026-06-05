<!-- History Tab - Show assignment history -->
<div class="space-y-6">
    <h2 class="text-2xl font-semibold text-white">Assignment History</h2>

    @if($assignmentHistory->count() > 0)
        <div class="bg-gray-800 border border-gray-700 rounded-lg shadow-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-gray-300">Machine</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-gray-300">Assigned Date</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-gray-300">Unassigned Date</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-gray-300">Duration</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-gray-300">Notes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @foreach($assignmentHistory as $machine)
                            <tr class="hover:bg-slate-50 transition">
                                <td class="px-6 py-4">
                                    <p class="font-medium text-white">{{ $machine->name }}</p>
                                    <p class="text-sm text-gray-400">{{ $machine->model }}</p>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-400">
                                    {{ $machine->pivot->assigned_at->format('M d, Y H:i') }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-400">
                                    @if($machine->pivot->unassigned_at)
                                        {{ $machine->pivot->unassigned_at->format('M d, Y H:i') }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-400">
                                    @if($machine->pivot->unassigned_at)
                                        @php
                                            $duration = $machine->pivot->assigned_at->diff($machine->pivot->unassigned_at);
                                            $durationStr = $duration->d > 0
                                                ? $duration->d . 'd ' . $duration->h . 'h'
                                                : $duration->h . 'h ' . $duration->i . 'm';
                                        @endphp
                                        {{ $durationStr }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-400">
                                    {{ $machine->pivot->notes ?: '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <div class="bg-gray-800 border border-gray-700 rounded-lg shadow-lg p-12 text-center">
            <p class="text-gray-400">No assignment history yet</p>
        </div>
    @endif

    <!-- History Info -->
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <p class="text-sm text-blue-900">
            <strong>Note:</strong> This view shows machines that have been unassigned from this area. Currently assigned machines can be found in the Overview tab.
        </p>
    </div>
</div>

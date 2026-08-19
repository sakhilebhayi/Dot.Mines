<!-- History Tab - Show assignment history -->
<div class="space-y-6">
    <h2 class="text-2xl font-display font-semibold text-[var(--stone)]">Assignment History</h2>

    @if($assignmentHistory->count() > 0)
        <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg shadow-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-white/5 border-b border-[var(--line)]">
                        <tr>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-[var(--sand)]">Machine</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-[var(--sand)]">Assigned Date</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-[var(--sand)]">Unassigned Date</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-[var(--sand)]">Duration</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-[var(--sand)]">Notes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--line)]">
                        @foreach($assignmentHistory as $record)
                            <tr class="hover:bg-white/5 transition">
                                <td class="px-6 py-4">
                                    <p class="font-medium text-[var(--stone)]">{{ $record->machine->name ?? 'Deleted machine' }}</p>
                                    <p class="text-sm text-[var(--sand)]">{{ $record->machine->model ?? '' }}</p>
                                </td>
                                <td class="px-6 py-4 text-sm text-[var(--sand)]">
                                    {{ $record->assigned_at->format('M d, Y H:i') }}
                                </td>
                                <td class="px-6 py-4 text-sm text-[var(--sand)]">
                                    {{ $record->unassigned_at?->format('M d, Y H:i') ?? '—' }}
                                </td>
                                <td class="px-6 py-4 text-sm text-[var(--sand)]">
                                    @if($record->unassigned_at)
                                        @php
                                            $duration = $record->assigned_at->diff($record->unassigned_at);
                                        @endphp
                                        {{ $duration->d > 0 ? "{$duration->d}d {$duration->h}h" : "{$duration->h}h {$duration->i}m" }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-[var(--sand)]">
                                    {{ $record->notes ?: ($record->reason ?: '—') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg shadow-lg p-12 text-center">
            <p class="text-[var(--sand)]">No assignment history yet</p>
        </div>
    @endif

    <!-- History Info -->
    <div class="bg-[var(--gold)]/10 border border-[var(--gold)]/20 rounded-lg p-4">
        <p class="text-sm text-[var(--sand)]">
            <strong class="text-[var(--stone)]">Note:</strong> This view shows machines that have been unassigned from this area. Currently assigned machines can be found in the Overview tab.
        </p>
    </div>
</div>

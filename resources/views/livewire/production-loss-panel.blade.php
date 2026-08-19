<div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg p-6 mb-6">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <h3 class="text-lg font-semibold text-[var(--stone)]">Production Loss Accountability</h3>
        @if ($canManage)
            <button type="button" wire:click="openRecordModal"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-sm font-medium bg-[var(--gold)]/15 text-[var(--gold)] border border-[var(--gold)]/30 hover:bg-[var(--gold)]/25">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Record Production Loss
            </button>
        @endif
    </div>

    {{-- Potential losses detected from telemetry, awaiting a human verdict --}}
    @if ($summary['pending_review'] > 0)
        <div class="flex items-start gap-3 p-4 mb-4 rounded-lg bg-yellow-500/10 border border-yellow-500/30" role="status">
            <svg class="w-5 h-5 text-yellow-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"></path>
            </svg>
            <p class="text-sm text-yellow-200">
                {{ $summary['pending_review'] }} potential production-loss {{ Str::plural('event', $summary['pending_review']) }} {{ $summary['pending_review'] === 1 ? 'requires' : 'require' }} review.
                Detected losses are never counted until a person classifies them.
            </p>
        </div>
    @endif

    {{-- Summary tiles: only user-recorded or human-confirmed losses count --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
        <div class="bg-white/5 rounded-lg p-4">
            <p class="text-[var(--sand)] text-sm">Lost Hours (total)</p>
            <p class="text-xl font-semibold text-[var(--stone)] mt-1">{{ number_format($summary['total_hours'], 1) }} h</p>
        </div>
        <div class="bg-white/5 rounded-lg p-4">
            <p class="text-[var(--sand)] text-sm">Today</p>
            <p class="text-xl font-semibold text-[var(--stone)] mt-1">{{ number_format($summary['today_hours'], 1) }} h</p>
        </div>
        <div class="bg-white/5 rounded-lg p-4">
            <p class="text-[var(--sand)] text-sm">This Week</p>
            <p class="text-xl font-semibold text-[var(--stone)] mt-1">{{ number_format($summary['week_hours'], 1) }} h</p>
        </div>
        <div class="bg-white/5 rounded-lg p-4">
            <p class="text-[var(--sand)] text-sm">This Month</p>
            <p class="text-xl font-semibold text-[var(--stone)] mt-1">{{ number_format($summary['month_hours'], 1) }} h</p>
        </div>
    </div>

    @if ($summary['primary_reason'] !== null || $impact !== null)
        <div class="flex flex-wrap gap-x-6 gap-y-2 mb-4 text-sm">
            @if ($summary['primary_reason'] !== null)
                <p class="text-[var(--sand)]">
                    Primary loss reason: <span class="text-[var(--stone)] font-medium">{{ $summary['primary_reason'] }}</span>
                </p>
            @endif
            @if ($impact !== null)
                <p class="text-[var(--sand)]">
                    Estimated production impact this month:
                    <span class="text-[var(--stone)] font-medium">≈ {{ number_format($impact['estimated_loss'], 1) }} {{ $impact['unit'] }}</span>
                    <span class="text-[var(--sand)]/70">(estimate — this machine averaged {{ number_format($impact['rate_per_hour'], 1) }} {{ $impact['unit'] }}/engine-hour over the last {{ $impact['basis_days'] }} days)</span>
                </p>
            @endif
        </div>
    @endif

    {{-- Loss event history --}}
    @if ($events->isEmpty())
        <div class="text-center py-8">
            <p class="text-[var(--sand)]">No production losses recorded</p>
            <p class="text-[var(--sand)]/70 text-sm mt-1">Losses detected from telemetry or recorded by your team will appear here.</p>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="border-b border-[var(--line)]">
                    <tr>
                        <th class="text-left px-4 py-2 text-[var(--sand)]">Date</th>
                        <th class="text-left px-4 py-2 text-[var(--sand)]">Start</th>
                        <th class="text-left px-4 py-2 text-[var(--sand)]">End</th>
                        <th class="text-left px-4 py-2 text-[var(--sand)]">Lost Hours</th>
                        <th class="text-left px-4 py-2 text-[var(--sand)]">Reason</th>
                        <th class="text-left px-4 py-2 text-[var(--sand)]">Source</th>
                        <th class="text-left px-4 py-2 text-[var(--sand)]">Status</th>
                        <th class="text-left px-4 py-2 text-[var(--sand)]">Notes</th>
                        @if ($canManage)
                            <th class="text-left px-4 py-2 text-[var(--sand)]"><span class="sr-only">Actions</span></th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--line)]">
                    @foreach ($events as $event)
                        <tr class="hover:bg-white/5" wire:key="loss-event-{{ $event->id }}">
                            <td class="px-4 py-2 text-[var(--sand)]">{{ $event->started_at->format('Y-m-d') }}</td>
                            <td class="px-4 py-2 text-[var(--sand)]">{{ $event->started_at->format('H:i') }}</td>
                            <td class="px-4 py-2 text-[var(--sand)]">{{ $event->ended_at->format('H:i') }}</td>
                            <td class="px-4 py-2 text-[var(--stone)] font-medium">{{ number_format($event->lost_hours, 1) }}</td>
                            <td class="px-4 py-2 text-[var(--sand)]">
                                {{ $event->reasonLabel() }}
                                @if ($event->category !== null)
                                    <span class="text-[var(--sand)]/70">({{ ucfirst($event->category) }})</span>
                                @endif
                            </td>
                            <td class="px-4 py-2">
                                @if ($event->source === \App\Models\ProductionLossEvent::SOURCE_SYSTEM)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-500/20 text-blue-400" @if ($event->detection_basis) title="{{ $event->detection_basis }}" @endif>Detected</span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-500/20 text-[var(--sand)]">Recorded</span>
                                @endif
                            </td>
                            <td class="px-4 py-2">
                                @if ($event->status === \App\Models\ProductionLossEvent::STATUS_PENDING)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-500/20 text-yellow-400">Pending review</span>
                                @elseif ($event->status === \App\Models\ProductionLossEvent::STATUS_CONFIRMED)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-500/20 text-green-400">Confirmed</span>
                                @elseif ($event->status === \App\Models\ProductionLossEvent::STATUS_DISPUTED)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-500/20 text-red-400">Disputed</span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-500/20 text-[var(--sand)]">Resolved</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-[var(--sand)] max-w-[16rem] truncate" @if ($event->notes) title="{{ $event->notes }}" @endif>
                                {{ $event->notes ?? '—' }}
                            </td>
                            @if ($canManage)
                                <td class="px-4 py-2">
                                    @if ($event->status === \App\Models\ProductionLossEvent::STATUS_PENDING)
                                        <button type="button" wire:click="openClassify({{ $event->id }})"
                                            class="text-[var(--gold)] hover:text-[var(--gold-soft)] text-xs font-medium">
                                            Classify
                                        </button>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="text-xs text-[var(--sand)]/70 mt-3">
            Totals include only user-recorded and confirmed losses. Detected events are excluded until reviewed.
        </p>
    @endif

    {{-- Record / classify dialog --}}
    @if ($showRecordModal || $classifyingEventId !== null)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/60" wire:click="cancelDialogs" aria-hidden="true"></div>
            <div class="relative w-full max-w-lg bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg p-6"
                role="dialog" aria-modal="true" aria-labelledby="loss-dialog-title">
                <h4 id="loss-dialog-title" class="text-lg font-semibold text-[var(--stone)] mb-4">
                    {{ $classifyingEventId !== null ? 'Classify Detected Loss' : 'Record Production Loss' }}
                </h4>

                <form wire:submit="{{ $classifyingEventId !== null ? 'classifyEvent' : 'recordLoss' }}" class="space-y-4">
                    @if ($classifyingEventId === null)
                        <div>
                            <label for="loss-date" class="block text-sm text-[var(--sand)] mb-1">Date</label>
                            <input id="loss-date" type="date" wire:model="lossDate" max="{{ now()->toDateString() }}"
                                class="w-full rounded-md bg-white/5 border-[var(--line)] text-[var(--stone)] text-sm focus:border-[var(--gold)] focus:ring-[var(--gold)]">
                            @error('lossDate') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="loss-start" class="block text-sm text-[var(--sand)] mb-1">Start time</label>
                                <input id="loss-start" type="time" wire:model="startTime"
                                    class="w-full rounded-md bg-white/5 border-[var(--line)] text-[var(--stone)] text-sm focus:border-[var(--gold)] focus:ring-[var(--gold)]">
                                @error('startTime') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                                @error('started_at') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="loss-end" class="block text-sm text-[var(--sand)] mb-1">End time</label>
                                <input id="loss-end" type="time" wire:model="endTime"
                                    class="w-full rounded-md bg-white/5 border-[var(--line)] text-[var(--stone)] text-sm focus:border-[var(--gold)] focus:ring-[var(--gold)]">
                                @error('endTime') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                                @error('ended_at') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    @endif

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="loss-category" class="block text-sm text-[var(--sand)] mb-1">Category</label>
                            <select id="loss-category" wire:model.live="category"
                                class="w-full rounded-md bg-white/5 border-[var(--line)] text-[var(--stone)] text-sm focus:border-[var(--gold)] focus:ring-[var(--gold)]">
                                @foreach (array_keys($reasonTaxonomy) as $categoryOption)
                                    <option value="{{ $categoryOption }}">{{ ucfirst($categoryOption) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="loss-reason" class="block text-sm text-[var(--sand)] mb-1">Reason</label>
                            <select id="loss-reason" wire:model="reason"
                                class="w-full rounded-md bg-white/5 border-[var(--line)] text-[var(--stone)] text-sm focus:border-[var(--gold)] focus:ring-[var(--gold)]">
                                @foreach ($reasonTaxonomy[$category] ?? ['other'] as $reasonOption)
                                    <option value="{{ $reasonOption }}">{{ ucfirst(str_replace('_', ' ', $reasonOption)) }}</option>
                                @endforeach
                            </select>
                            @error('reason') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label for="loss-notes" class="block text-sm text-[var(--sand)] mb-1">
                            Notes {{ $reason === 'other' ? '(required for "Other")' : '(optional)' }}
                        </label>
                        <textarea id="loss-notes" wire:model="notes" rows="3" maxlength="2000"
                            @if ($reason === 'other') required @endif
                            class="w-full rounded-md bg-white/5 border-[var(--line)] text-[var(--stone)] text-sm focus:border-[var(--gold)] focus:ring-[var(--gold)]"
                            placeholder="What happened?"></textarea>
                        @error('notes') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" wire:click="cancelDialogs"
                            class="px-4 py-2 rounded-md text-sm text-[var(--sand)] hover:text-[var(--stone)] border border-[var(--line)]">
                            Cancel
                        </button>
                        <button type="submit"
                            class="px-4 py-2 rounded-md text-sm font-medium bg-[var(--gold)] text-black hover:bg-[var(--gold-soft)]">
                            {{ $classifyingEventId !== null ? 'Confirm Classification' : 'Record Loss' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>

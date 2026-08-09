<div class="w-full">
    <div class="p-6">
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-[var(--stone)]">Operator Fatigue</h1>
            <p class="text-[var(--sand)] mt-2">Log shift fatigue readings and see who on your team needs rest before it becomes a safety incident.</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Log a shift -->
            <div class="lg:col-span-1 bg-[var(--ink-soft)] rounded-lg shadow-lg p-6">
                <h2 class="text-lg font-semibold text-[var(--stone)] mb-4">Log a shift</h2>

                <form wire:submit="submitShift" class="space-y-4">
                    <div>
                        <label class="block text-sm text-[var(--sand)] mb-1" for="operatorId">Operator</label>
                        <select wire:model="operatorId" id="operatorId" class="select select-bordered w-full bg-[var(--ink)] text-[var(--stone)] border-[var(--line)]">
                            @foreach ($this->operators as $operator)
                                <option value="{{ $operator->id }}">{{ $operator->name }}</option>
                            @endforeach
                        </select>
                        @error('operatorId') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm text-[var(--sand)] mb-1" for="shiftDate">Shift date</label>
                            <input wire:model="shiftDate" id="shiftDate" type="date" class="input input-bordered w-full bg-[var(--ink)] text-[var(--stone)] border-[var(--line)]">
                            @error('shiftDate') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm text-[var(--sand)] mb-1" for="shiftType">Shift type</label>
                            <select wire:model="shiftType" id="shiftType" class="select select-bordered w-full bg-[var(--ink)] text-[var(--stone)] border-[var(--line)]">
                                <option value="morning">Morning</option>
                                <option value="afternoon">Afternoon</option>
                                <option value="night">Night</option>
                            </select>
                            @error('shiftType') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm text-[var(--sand)] mb-1" for="shiftStart">Shift start</label>
                            <input wire:model="shiftStart" id="shiftStart" type="time" class="input input-bordered w-full bg-[var(--ink)] text-[var(--stone)] border-[var(--line)]">
                            @error('shiftStart') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm text-[var(--sand)] mb-1" for="shiftEnd">Shift end</label>
                            <input wire:model="shiftEnd" id="shiftEnd" type="time" class="input input-bordered w-full bg-[var(--ink)] text-[var(--stone)] border-[var(--line)]">
                            @error('shiftEnd') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm text-[var(--sand)] mb-1" for="hoursWorked">Hours worked</label>
                            <input wire:model="hoursWorked" id="hoursWorked" type="number" step="0.5" min="0" max="24" class="input input-bordered w-full bg-[var(--ink)] text-[var(--stone)] border-[var(--line)]">
                            @error('hoursWorked') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm text-[var(--sand)] mb-1" for="consecutiveDays">Consecutive days</label>
                            <input wire:model="consecutiveDays" id="consecutiveDays" type="number" step="0.5" min="0" max="31" class="input input-bordered w-full bg-[var(--ink)] text-[var(--stone)] border-[var(--line)]">
                            @error('consecutiveDays') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm text-[var(--sand)] mb-1" for="breakTimeMinutes">Break time (min)</label>
                            <input wire:model="breakTimeMinutes" id="breakTimeMinutes" type="number" step="5" min="0" max="1440" class="input input-bordered w-full bg-[var(--ink)] text-[var(--stone)] border-[var(--line)]">
                            @error('breakTimeMinutes') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm text-[var(--sand)] mb-1" for="incidentsCount">Incidents this shift</label>
                            <input wire:model="incidentsCount" id="incidentsCount" type="number" min="0" class="input input-bordered w-full bg-[var(--ink)] text-[var(--stone)] border-[var(--line)]">
                            @error('incidentsCount') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm text-[var(--sand)] mb-1" for="notes">Notes (optional)</label>
                        <textarea wire:model="notes" id="notes" rows="2" class="textarea textarea-bordered w-full bg-[var(--ink)] text-[var(--stone)] border-[var(--line)]"></textarea>
                        @error('notes') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <button type="submit" class="btn btn-primary w-full bg-[var(--gold)] hover:bg-[var(--gold-soft)] border-none text-[var(--ink)] font-display font-semibold">
                        Record shift
                    </button>
                </form>
            </div>

            <!-- Roster -->
            <div class="lg:col-span-2 bg-[var(--ink-soft)] rounded-lg shadow-lg p-6">
                <h2 class="text-lg font-semibold text-[var(--stone)] mb-4">Recent readings</h2>

                @if ($this->roster->isEmpty())
                    <p class="text-[var(--sand)] text-sm">No fatigue readings logged yet for this team.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left text-[var(--sand)] border-b border-[var(--line)]">
                                    <th class="py-2 pr-4">Operator</th>
                                    <th class="py-2 pr-4">Shift</th>
                                    <th class="py-2 pr-4">Hours</th>
                                    <th class="py-2 pr-4">Consec. days</th>
                                    <th class="py-2 pr-4">Score</th>
                                    <th class="py-2 pr-4">Level</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($this->roster as $reading)
                                    @php
                                        $levelClasses = match ($reading->alert_level) {
                                            'critical' => 'bg-red-900 text-red-300',
                                            'high' => 'bg-orange-900 text-orange-300',
                                            'medium' => 'bg-yellow-900 text-yellow-300',
                                            'low' => 'bg-blue-900 text-blue-300',
                                            default => 'bg-white/10 text-[var(--sand)]',
                                        };
                                    @endphp
                                    <tr class="border-b border-[var(--line)]/50">
                                        <td class="py-2 pr-4 text-[var(--stone)]">{{ $reading->user?->name ?? 'Unknown' }}</td>
                                        <td class="py-2 pr-4 text-[var(--sand)]">{{ $reading->shift_date->format('M d') }} &middot; {{ ucfirst($reading->shift_type) }}</td>
                                        <td class="py-2 pr-4 text-[var(--sand)]">{{ $reading->hours_worked }}</td>
                                        <td class="py-2 pr-4 text-[var(--sand)]">{{ $reading->consecutive_days }}</td>
                                        <td class="py-2 pr-4 text-[var(--sand)]">{{ $reading->fatigue_score }}/100</td>
                                        <td class="py-2 pr-4">
                                            <span class="px-2 py-1 rounded text-xs font-medium {{ $levelClasses }}">{{ ucfirst($reading->alert_level) }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

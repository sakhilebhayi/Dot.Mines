<!-- Manage Tab - Remove machines from area -->
<div class="space-y-6">
    <!-- Toolbar -->
    <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg shadow-lg p-6">
        <div class="flex flex-col sm:flex-row gap-4 items-start sm:items-end">
            <div class="flex-1 min-w-0">
                <label class="block text-sm font-medium text-[var(--sand)] mb-2">Search</label>
                <input
                    type="text"
                    wire:model.live="searchTerm"
                    placeholder="Search assigned machines..."
                    class="w-full px-4 py-2 bg-white/5 border border-[var(--line)] rounded-lg text-[var(--stone)] focus:ring-2 focus:ring-[var(--gold)] focus:border-transparent"
                />
            </div>
            @if(count($selectedMachineIds) > 0)
                <button
                    wire:click="unassignMultipleMachines"
                    wire:confirm="Unassign {{ count($selectedMachineIds) }} machine(s)?"
                    class="px-6 py-2 bg-red-600 text-[var(--stone)] rounded-lg hover:bg-red-700 transition font-medium"
                >
                    Unassign Selected
                </button>
            @endif
        </div>
    </div>

    <!-- Machines List -->
    @if($assignedMachines->count() > 0)
        <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg shadow-lg overflow-hidden">
            <table class="w-full">
                <thead class="bg-white/5 border-b border-[var(--line)]">
                    <tr>
                        <th class="px-6 py-3 text-left">
                            <input
                                type="checkbox"
                                wire:model.live="selectAll"
                                @change="$wire.toggleSelectAll()"
                                class="rounded"
                            />
                        </th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-[var(--sand)]">Machine</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-[var(--sand)]">Model</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-[var(--sand)]">Status</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-[var(--sand)]">Assigned</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-[var(--sand)]">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--line)]">
                    @foreach($assignedMachines as $machine)
                        <tr class="hover:bg-white/5 transition">
                            <td class="px-6 py-4">
                                <input
                                    type="checkbox"
                                    wire:model.live="selectedMachineIds"
                                    value="{{ $machine->id }}"
                                    class="rounded"
                                />
                            </td>
                            <td class="px-6 py-4 text-sm font-medium text-[var(--stone)]">{{ $machine->name }}</td>
                            <td class="px-6 py-4 text-sm text-[var(--sand)]">{{ $machine->model }}</td>
                            <td class="px-6 py-4 text-sm">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium
                                    @if($machine->status === 'active') bg-green-500/15 text-green-400
                                    @elseif($machine->status === 'maintenance') bg-red-500/15 text-red-400
                                    @else bg-white/10 text-[var(--sand)]
                                    @endif
                                ">
                                    {{ ucfirst($machine->status) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-[var(--sand)]">
                                @if($assignmentByMachine->has($machine->id))
                                    {{ $assignmentByMachine[$machine->id]->assigned_at->format('M d') }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm space-x-2">
                                <button
                                    wire:click="unassignMachine({{ $machine->id }})"
                                    wire:confirm="Remove {{ $machine->name }}?"
                                    class="text-red-400 hover:text-red-300 font-medium transition"
                                >
                                    Remove
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg shadow-lg p-12 text-center">
            <p class="text-[var(--sand)]">No machines assigned to this area</p>
        </div>
    @endif
</div>

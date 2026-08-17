<!-- Assign Tab - Add machines to area -->
<div class="space-y-6">
    <!-- Toolbar -->
    <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg shadow-lg p-6">
        <div class="flex flex-col sm:flex-row gap-4 items-start sm:items-end">
            <div class="flex-1 min-w-0">
                <label class="block text-sm font-medium text-[var(--sand)] mb-2">Search</label>
                <input
                    type="text"
                    wire:model.live="searchTerm"
                    placeholder="Search other machines..."
                    class="w-full px-4 py-2 bg-white/5 border border-[var(--line)] rounded-lg text-[var(--stone)] focus:ring-2 focus:ring-[var(--gold)] focus:border-transparent"
                />
            </div>
            <div class="w-full sm:w-auto">
                <label class="block text-sm font-medium text-[var(--sand)] mb-2">Filter</label>
                <select
                    wire:model.live="filterStatus"
                    class="w-full px-4 py-2 bg-white/5 border border-[var(--line)] rounded-lg text-[var(--stone)] focus:ring-2 focus:ring-[var(--gold)] focus:border-transparent"
                >
                    <option value="">All Statuses</option>
                    <option value="active">Active</option>
                    <option value="idle">Idle</option>
                    <option value="maintenance">Maintenance</option>
                </select>
            </div>
            @if(count($selectedMachineIds) > 0)
                <button
                    wire:click="assignSelectedMachines"
                    class="px-6 py-2 bg-[var(--gold)] text-[var(--ink)] rounded-lg hover:bg-[var(--gold-soft)] transition font-display font-semibold"
                >
                    ✓ Assign Selected ({{ count($selectedMachineIds) }})
                </button>
            @endif
        </div>
    </div>

    <!-- Info Alert -->
    <div class="bg-[var(--gold)]/10 border border-[var(--gold)]/20 rounded-lg p-4">
        <p class="text-sm text-[var(--sand)]">
            <strong class="text-[var(--stone)]">{{ $unassignedCount }} machine(s)</strong> currently assigned to other areas are available here.
        </p>
    </div>

    <!-- Machines List -->
    @if($machines->count() > 0)
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
                        <th class="px-6 py-3 text-left text-sm font-semibold text-[var(--sand)]">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--line)]">
                    @foreach($machines as $machine)
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
                            <td class="px-6 py-4 text-sm space-x-2">
                                <button
                                    wire:click="showAssignForm({{ $machine->id }})"
                                    class="text-[var(--gold)] hover:text-[var(--gold-soft)] font-medium transition"
                                >
                                    Assign
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg shadow-lg p-6 border-t border-[var(--line)]">
            {{ $machines->links('pagination::tailwind') }}
        </div>
    @else
        <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg shadow-lg p-12 text-center">
            <p class="text-[var(--sand)] mb-4">No other machines available to assign</p>
            @if($searchTerm)
                <button
                    wire:click="$set('searchTerm', '')"
                    class="text-[var(--gold)] hover:text-[var(--gold-soft)] font-medium transition"
                >
                    Clear search
                </button>
            @endif
        </div>
    @endif

    <!-- Individual Assignment Form -->
    @if($showAssignModal && $selectedMachine)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
            <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg shadow-xl max-w-md w-full p-6">
                <h3 class="text-lg font-semibold text-[var(--stone)] mb-4">Assign Machine</h3>

                <div class="mb-4 p-3 bg-white/5 rounded-lg">
                    <p class="text-sm text-[var(--sand)]">Machine</p>
                    <p class="font-medium text-[var(--stone)]">{{ $selectedMachine->name }}</p>
                    <p class="text-sm text-[var(--sand)] mt-1">{{ $selectedMachine->model }}</p>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-[var(--sand)] mb-2">Notes (Optional)</label>
                    <textarea
                        wire:model="selectedNotes"
                        placeholder="Add notes for this assignment..."
                        rows="3"
                        class="w-full px-4 py-2 bg-white/5 border border-[var(--line)] rounded-lg text-[var(--stone)] focus:ring-2 focus:ring-[var(--gold)] focus:border-transparent"
                    ></textarea>
                </div>

                <div class="flex gap-3">
                    <button
                        wire:click="cancelAssignForm"
                        class="flex-1 px-4 py-2 bg-white/5 hover:bg-white/10 border border-[var(--line)] text-[var(--stone)] rounded-lg transition font-medium"
                    >
                        Cancel
                    </button>
                    <button
                        wire:click="assignSingleMachine({{ $selectedMachine->id }})"
                        class="flex-1 px-4 py-2 bg-[var(--gold)] text-[var(--ink)] rounded-lg hover:bg-[var(--gold-soft)] transition font-display font-semibold"
                    >
                        Assign
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>

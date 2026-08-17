<!-- Overview Tab -->
<div class="space-y-6">
    <!-- Current Assignments -->
    <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg shadow-lg p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-2xl font-display font-semibold text-[var(--stone)]">Assigned Machines</h2>
            @if($assignedMachines->count() > 0)
                <button
                    wire:click="exportAssignmentReport"
                    class="px-4 py-2 bg-[var(--gold)] text-[var(--ink)] rounded-lg hover:bg-[var(--gold-soft)] transition font-display font-semibold"
                >
                    ⬇️ Export Report
                </button>
            @endif
        </div>

        @if($assignedMachines->count() > 0)
            <div class="space-y-2 max-h-96 overflow-y-auto">
                @foreach($assignedMachines as $machine)
                    <div class="flex items-center justify-between p-4 bg-white/5 rounded-lg border border-[var(--line)] hover:bg-white/10 transition">
                        <div class="flex-1">
                            <p class="font-medium text-[var(--stone)]">{{ $machine->name }}</p>
                            <p class="text-sm text-[var(--sand)]">{{ $machine->model }}</p>
                            @if($assignmentByMachine->has($machine->id))
                                <p class="text-xs text-[var(--sand)]/70 mt-1">
                                    Assigned: {{ $assignmentByMachine[$machine->id]->assigned_at->format('M d, Y H:i') }}
                                </p>
                            @endif
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium
                                @if($machine->status === 'active') bg-green-500/15 text-green-400
                                @elseif($machine->status === 'maintenance') bg-red-500/15 text-red-400
                                @else bg-white/10 text-[var(--sand)]
                                @endif
                            ">
                                {{ ucfirst($machine->status) }}
                            </span>
                            <button
                                wire:click="unassignMachine({{ $machine->id }})"
                                wire:confirm="Remove {{ $machine->name }} from {{ $mineArea->name }}?"
                                class="text-red-400 hover:text-red-300 font-medium transition"
                            >
                                Remove
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Statistics -->
            <div class="mt-6 p-4 bg-[var(--gold)]/10 border border-[var(--gold)]/20 rounded-lg">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                    <div>
                        <p class="text-[var(--sand)] font-medium">Total Assigned</p>
                        <p class="text-2xl font-display font-semibold text-[var(--stone)]">{{ $assignedMachines->count() }}</p>
                    </div>
                    <div>
                        <p class="text-[var(--sand)] font-medium">Active</p>
                        <p class="text-2xl font-display font-semibold text-[var(--stone)]">{{ $assignedMachines->where('status', 'active')->count() }}</p>
                    </div>
                    <div>
                        <p class="text-[var(--sand)] font-medium">Maintenance</p>
                        <p class="text-2xl font-display font-semibold text-[var(--stone)]">{{ $assignedMachines->where('status', 'maintenance')->count() }}</p>
                    </div>
                    <div>
                        <p class="text-[var(--sand)] font-medium">Coverage</p>
                        <p class="text-2xl font-display font-semibold text-[var(--stone)]">{{ $totalMachines > 0 ? round(($assignedMachines->count() / $totalMachines) * 100) : 0 }}%</p>
                    </div>
                </div>
            </div>
        @else
            <div class="text-center py-8">
                <svg class="w-12 h-12 text-[var(--sand)]/50 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4v2m0 0v2m0-6H9m6 0h-3"></path>
                </svg>
                <p class="text-[var(--sand)] mb-4">No machines assigned yet</p>
                <button
                    wire:click="switchToAssign"
                    class="inline-flex items-center px-6 py-2 bg-[var(--gold)] text-[var(--ink)] rounded-lg hover:bg-[var(--gold-soft)] transition font-display font-semibold"
                >
                    + Assign Machines
                </button>
            </div>
        @endif
    </div>

    <!-- Quick Stats -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Area Info -->
        <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg shadow-lg p-6">
            <h3 class="font-semibold text-[var(--stone)] mb-4">Mine Area Details</h3>
            <div class="space-y-3 text-sm">
                <div>
                    <p class="text-[var(--sand)]">Location</p>
                    <p class="font-medium text-[var(--stone)]">{{ $mineArea->location ?: 'N/A' }}</p>
                </div>
                <div>
                    <p class="text-[var(--sand)]">Area Size</p>
                    <p class="font-medium text-[var(--stone)]">{{ $mineArea->area_size_hectares ? number_format($mineArea->area_size_hectares, 1).' ha' : 'N/A' }}</p>
                </div>
                <div>
                    <p class="text-[var(--sand)]">Status</p>
                    <p class="font-medium text-[var(--stone)]">
                        @if($mineArea->status === 'active')
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-500/15 text-green-400">Active</span>
                        @else
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-white/10 text-[var(--sand)]">{{ ucfirst($mineArea->status) }}</span>
                        @endif
                    </p>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg shadow-lg p-6">
            <h3 class="font-semibold text-[var(--stone)] mb-4">Recent Activity</h3>
            <div class="space-y-3 text-sm">
                <div class="p-3 bg-white/5 rounded-lg">
                    <p class="text-[var(--sand)]">Last Updated</p>
                    <p class="font-medium text-[var(--stone)]">{{ $mineArea->updated_at->format('M d, Y H:i') }}</p>
                </div>
                <div class="p-3 bg-white/5 rounded-lg">
                    <p class="text-[var(--sand)]">Created</p>
                    <p class="font-medium text-[var(--stone)]">{{ $mineArea->created_at->format('M d, Y') }}</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Main Machine Assignment Manager View -->
<div class="min-h-screen bg-[var(--ink)] py-8 px-4 sm:px-6 lg:px-8">
    <div class="max-w-7xl mx-auto">
        <!-- Header -->
        <div class="mb-8">
            <a href="{{ route('mine-areas.show', $mineArea->id) }}" class="text-[var(--gold)] hover:text-[var(--gold-soft)] font-medium mb-4 flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
                Back to {{ $mineArea->name }}
            </a>
            <h1 class="text-4xl font-display font-semibold text-[var(--stone)]">Machine Assignment</h1>
            <p class="mt-2 text-[var(--sand)]">Manage machines assigned to <strong class="text-[var(--stone)]">{{ $mineArea->name }}</strong></p>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg shadow-lg p-6">
                <p class="text-sm font-medium text-[var(--sand)] uppercase">Total Machines</p>
                <p class="text-3xl font-display font-semibold text-[var(--stone)] mt-2">{{ $totalMachines }}</p>
            </div>
            <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg shadow-lg p-6">
                <p class="text-sm font-medium text-[var(--sand)] uppercase">Assigned Here</p>
                <p class="text-3xl font-display font-semibold text-green-400 mt-2">{{ $assignedMachines->count() }}</p>
            </div>
            <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg shadow-lg p-6">
                <p class="text-sm font-medium text-[var(--sand)] uppercase">In Other Areas</p>
                <p class="text-3xl font-display font-semibold text-[var(--gold)] mt-2">{{ $unassignedCount }}</p>
            </div>
            <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg shadow-lg p-6">
                <p class="text-sm font-medium text-[var(--sand)] uppercase">Coverage</p>
                <p class="text-3xl font-display font-semibold text-[var(--stone)] mt-2">{{ $totalMachines > 0 ? round(($assignedMachines->count() / $totalMachines) * 100) : 0 }}%</p>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg shadow-lg mb-8 overflow-hidden">
            <div class="flex border-b border-[var(--line)]">
                <button
                    wire:click="switchToOverview"
                    @class(['flex-1 px-6 py-4 text-center font-medium transition',
                            'bg-[var(--gold)]/10 text-[var(--gold)] border-b-2 border-[var(--gold)]' => $view === 'overview',
                            'text-[var(--sand)] hover:bg-white/5' => $view !== 'overview'])
                >
                    Overview
                </button>
                <button
                    wire:click="switchToManage"
                    @class(['flex-1 px-6 py-4 text-center font-medium transition',
                            'bg-[var(--gold)]/10 text-[var(--gold)] border-b-2 border-[var(--gold)]' => $view === 'manage',
                            'text-[var(--sand)] hover:bg-white/5' => $view !== 'manage'])
                >
                    Manage
                </button>
                <button
                    wire:click="switchToAssign"
                    @class(['flex-1 px-6 py-4 text-center font-medium transition',
                            'bg-[var(--gold)]/10 text-[var(--gold)] border-b-2 border-[var(--gold)]' => $view === 'assign',
                            'text-[var(--sand)] hover:bg-white/5' => $view !== 'assign'])
                >
                    Assign
                </button>
                <button
                    wire:click="switchToHistory"
                    @class(['flex-1 px-6 py-4 text-center font-medium transition',
                            'bg-[var(--gold)]/10 text-[var(--gold)] border-b-2 border-[var(--gold)]' => $view === 'history',
                            'text-[var(--sand)] hover:bg-white/5' => $view !== 'history'])
                >
                    History
                </button>
            </div>
        </div>

        <!-- View Content -->
        <div>
            @if($view === 'overview')
                @include('livewire.machine-assignment-manager.overview')
            @elseif($view === 'manage')
                @include('livewire.machine-assignment-manager.manage')
            @elseif($view === 'assign')
                @include('livewire.machine-assignment-manager.assign')
            @elseif($view === 'history')
                @include('livewire.machine-assignment-manager.history')
            @endif
        </div>
    </div>
</div>

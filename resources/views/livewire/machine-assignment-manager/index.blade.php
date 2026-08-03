<!-- Main Machine Assignment Manager View -->
<div class="min-h-screen bg-gray-50 dark:bg-gray-900 py-8 px-4 sm:px-6 lg:px-8">
    <div class="max-w-7xl mx-auto">
        <!-- Header -->
        <div class="mb-8">
            <a href="{{ route('mine-areas') }}" class="text-blue-600 hover:text-blue-800 font-medium mb-4 flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
                Back to Mine Areas
            </a>
            <h1 class="text-4xl font-bold text-white">Machine Assignment</h1>
            <p class="mt-2 text-gray-400">Manage machines assigned to <strong>{{ $mineArea->name }}</strong></p>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-gray-800 border border-gray-700 rounded-lg shadow-lg p-6">
                <p class="text-sm font-medium text-gray-400 uppercase">Total Machines</p>
                <p class="text-3xl font-bold text-white mt-2">{{ $totalMachines }}</p>
            </div>
            <div class="bg-gray-800 border border-gray-700 rounded-lg shadow-lg p-6">
                <p class="text-sm font-medium text-gray-400 uppercase">Assigned</p>
                <p class="text-3xl font-bold text-green-600 mt-2">{{ $assignedMachines->count() }}</p>
            </div>
            <div class="bg-gray-800 border border-gray-700 rounded-lg shadow-lg p-6">
                <p class="text-sm font-medium text-gray-400 uppercase">Unassigned</p>
                <p class="text-3xl font-bold text-orange-600 mt-2">{{ $unassignedCount }}</p>
            </div>
            <div class="bg-gray-800 border border-gray-700 rounded-lg shadow-lg p-6">
                <p class="text-sm font-medium text-gray-400 uppercase">Coverage</p>
                <p class="text-3xl font-bold text-blue-600 mt-2">{{ $totalMachines > 0 ? round(($assignedMachines->count() / $totalMachines) * 100) : 0 }}%</p>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="bg-gray-800 border border-gray-700 rounded-lg shadow-lg mb-8 overflow-hidden">
            <div class="flex border-b border-slate-200">
                <button 
                    wire:click="switchToOverview"
                    @class(['flex-1 px-6 py-4 text-center font-medium transition', 
                            'bg-blue-50 text-blue-700 border-b-2 border-blue-600' => $view === 'overview',
                            'text-gray-300 hover:bg-slate-50' => $view !== 'overview'])
                >
                    📊 Overview
                </button>
                <button 
                    wire:click="switchToManage"
                    @class(['flex-1 px-6 py-4 text-center font-medium transition',
                            'bg-blue-50 text-blue-700 border-b-2 border-blue-600' => $view === 'manage',
                            'text-gray-300 hover:bg-slate-50' => $view !== 'manage'])
                >
                    🔧 Manage
                </button>
                <button 
                    wire:click="switchToAssign"
                    @class(['flex-1 px-6 py-4 text-center font-medium transition',
                            'bg-blue-50 text-blue-700 border-b-2 border-blue-600' => $view === 'assign',
                            'text-gray-300 hover:bg-slate-50' => $view !== 'assign'])
                >
                    ➕ Assign
                </button>
                <button 
                    wire:click="switchToHistory"
                    @class(['flex-1 px-6 py-4 text-center font-medium transition',
                            'bg-blue-50 text-blue-700 border-b-2 border-blue-600' => $view === 'history',
                            'text-gray-300 hover:bg-slate-50' => $view !== 'history'])
                >
                    📜 History
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

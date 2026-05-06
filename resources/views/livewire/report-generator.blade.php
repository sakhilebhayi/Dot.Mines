<div class="min-h-screen bg-slate-900 p-6">
    <div class="max-w-4xl mx-auto">
        <!-- Header -->
        <div class="mb-8">
            <a href="{{ route('reports') }}" class="text-blue-400 hover:text-blue-300 font-medium mb-4 inline-block">← Back to Reports</a>
            <h1 class="text-4xl font-bold text-white">Generate Report</h1>
            <p class="text-slate-400 mt-2">Create custom reports for your mining operations</p>
        </div>

        <!-- Progress Steps -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                @for($i = 1; $i <= 3; $i++)
                    <div class="flex items-center flex-1">
                        <div class="flex items-center">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center font-semibold {{ $step >= $i ? 'bg-blue-600 text-white' : 'bg-slate-700 text-slate-400' }}">
                                {{ $i }}
                            </div>
                            <span class="ml-3 text-sm font-medium {{ $step >= $i ? 'text-white' : 'text-slate-400' }}">
                                @if($i === 1) Report Details
                                @elseif($i === 2) Date Range
                                @else Options
                                @endif
                            </span>
                        </div>
                        @if($i < 3)
                            <div class="flex-1 h-1 ml-4 {{ $step > $i ? 'bg-blue-600' : 'bg-slate-700' }}"></div>
                        @endif
                    </div>
                @endfor
            </div>
        </div>

        <!-- Form Container -->
        <div class="bg-slate-800 rounded-lg border border-slate-700 p-8">
            <!-- Step 1: Report Details -->
            @if($step === 1)
                <div class="space-y-6">
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Report Name</label>
                        <input 
                            type="text" 
                            wire:model="reportName" 
                            placeholder="e.g., January Production Summary"
                            class="w-full bg-slate-700 text-white px-4 py-2 rounded-lg border border-slate-600 focus:border-blue-500 focus:outline-none"
                        >
                        @error('reportName') <span class="text-red-400 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-4">Select Report Type</label>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            @foreach($reportTypes as $key => $type)
                                <div class="relative">
                                    <label 
                                        wire:click="$set('reportType', '{{ $key }}')"
                                        class="block p-4 rounded-lg border-2 cursor-pointer transition {{ $reportType === $key ? 'border-blue-600 bg-slate-700' : 'border-slate-600 bg-slate-700/50 hover:border-slate-500' }}"
                                    >
                                        <div class="flex flex-col items-start gap-2">
                                            <div class="text-2xl">{{ $type['icon'] }}</div>
                                            <div class="font-medium text-white">{{ $type['label'] }}</div>
                                            <div class="text-sm text-slate-400 leading-relaxed">{{ $type['description'] }}</div>
                                        </div>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Description (Optional)</label>
                        <textarea 
                            wire:model="description" 
                            placeholder="Add any notes or context for this report..."
                            rows="4"
                            class="w-full bg-slate-700 text-white px-4 py-2 rounded-lg border border-slate-600 focus:border-blue-500 focus:outline-none"
                        ></textarea>
                        @error('description') <span class="text-red-400 text-sm">{{ $message }}</span> @enderror
                    </div>
                </div>
            @endif

            <!-- Step 2: Date Range & Format -->
            @if($step === 2)
                <div class="space-y-6">
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">Start Date</label>
                        <input 
                            type="date" 
                            wire:model="startDate"
                            class="w-full bg-slate-700 text-white px-4 py-2 rounded-lg border border-slate-600 focus:border-blue-500 focus:outline-none"
                        >
                        @error('startDate') <span class="text-red-400 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">End Date</label>
                        <input 
                            type="date" 
                            wire:model="endDate"
                            class="w-full bg-slate-700 text-white px-4 py-2 rounded-lg border border-slate-600 focus:border-blue-500 focus:outline-none"
                        >
                        @error('endDate') <span class="text-red-400 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-4">Export Format</label>
                        <div class="grid grid-cols-3 gap-4">
                            @foreach(['pdf' => 'PDF', 'csv' => 'CSV', 'xlsx' => 'Excel'] as $fmt => $label)
                                <div>
                                    <label 
                                        wire:click="$set('format', '{{ $fmt }}')"
                                        class="block p-4 text-center rounded-lg border-2 cursor-pointer transition {{ $format === $fmt ? 'border-blue-600 bg-blue-900/30' : 'border-slate-600 bg-slate-700/50 hover:border-slate-500' }}"
                                    >
                                        <div class="font-medium text-white">{{ $label }}</div>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-4">Select Machines (Optional)</label>
                        <div class="space-y-3 mb-4">
                            <div class="flex gap-2">
                                <button 
                                    type="button"
                                    wire:click.prevent="selectAllMachines"
                                    wire:loading.attr="disabled"
                                    wire:target="selectAllMachines"
                                    class="px-3 py-1 bg-slate-700 hover:bg-slate-600 text-white text-sm rounded transition"
                                >
                                    Select All
                                </button>
                                <button 
                                    type="button"
                                    wire:click.prevent="clearMachines"
                                    wire:loading.attr="disabled"
                                    wire:target="clearMachines"
                                    class="px-3 py-1 bg-slate-700 hover:bg-slate-600 text-white text-sm rounded transition"
                                >
                                    Clear All
                                </button>
                            </div>
                            <div class="grid grid-cols-2 gap-3 max-h-48 overflow-y-auto">
                                @foreach($machines as $machine)
                                    <label wire:key="machine-checkbox-{{ $machine->id }}" class="flex items-center gap-2 p-2 rounded hover:bg-slate-700/50 cursor-pointer">
                                        <input 
                                            type="checkbox" 
                                            wire:model.number="selectedMachines" 
                                            value="{{ $machine->id }}"
                                            class="rounded bg-slate-600 border-slate-500"
                                        >
                                        <span class="text-sm text-slate-300">{{ $machine->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-4">Select Geofences (Optional)</label>
                        <div class="space-y-3 mb-4">
                            <div class="flex gap-2">
                                <button 
                                    type="button"
                                    wire:click.prevent="selectAllGeofences"
                                    wire:loading.attr="disabled"
                                    wire:target="selectAllGeofences"
                                    class="px-3 py-1 bg-slate-700 hover:bg-slate-600 text-white text-sm rounded transition"
                                >
                                    Select All
                                </button>
                                <button 
                                    type="button"
                                    wire:click.prevent="clearGeofences"
                                    wire:loading.attr="disabled"
                                    wire:target="clearGeofences"
                                    class="px-3 py-1 bg-slate-700 hover:bg-slate-600 text-white text-sm rounded transition"
                                >
                                    Clear All
                                </button>
                            </div>
                            <div class="grid grid-cols-2 gap-3 max-h-48 overflow-y-auto">
                                @forelse($geofences as $geofence)
                                    <label wire:key="geofence-checkbox-{{ $geofence->id }}" class="flex items-center gap-2 p-2 rounded hover:bg-slate-700/50 cursor-pointer">
                                        <input 
                                            type="checkbox" 
                                            wire:model.number="selectedGeofences" 
                                            value="{{ $geofence->id }}"
                                            class="rounded bg-slate-600 border-slate-500"
                                        >
                                        <span class="text-sm text-slate-300">{{ $geofence->name ?? ('Geofence #' . $geofence->id) }}</span>
                                    </label>
                                @empty
                                    <p class="text-sm text-slate-400">No geofences available for your current team.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Step 3: Options -->
            @if($step === 3)
                <div class="space-y-6">
                    <div class="space-y-4">
                        <label class="flex items-center gap-3 p-4 rounded-lg border border-slate-600 cursor-pointer hover:bg-slate-700/30">
                            <input 
                                type="checkbox" 
                                wire:model="includeMetrics"
                                class="rounded bg-slate-600 border-slate-500"
                            >
                            <div>
                                <div class="font-medium text-white">Include Performance Metrics</div>
                                <div class="text-sm text-slate-400">RPM, temperature, fuel usage, and load data</div>
                            </div>
                        </label>

                        <label class="flex items-center gap-3 p-4 rounded-lg border border-slate-600 cursor-pointer hover:bg-slate-700/30">
                            <input 
                                type="checkbox" 
                                wire:model="includeAlerts"
                                class="rounded bg-slate-600 border-slate-500"
                            >
                            <div>
                                <div class="font-medium text-white">Include Alerts & Issues</div>
                                <div class="text-sm text-slate-400">Warning and error events from the period</div>
                            </div>
                        </label>

                        <label class="flex items-center gap-3 p-4 rounded-lg border border-slate-600 cursor-pointer hover:bg-slate-700/30">
                            <input 
                                type="checkbox" 
                                wire:model="includeChart"
                                class="rounded bg-slate-600 border-slate-500"
                            >
                            <div>
                                <div class="font-medium text-white">Include Charts & Graphs</div>
                                <div class="text-sm text-slate-400">Visual data representations and trends</div>
                            </div>
                        </label>
                    </div>

                    <div class="border-t border-slate-600 pt-6">
                        <label class="flex items-center gap-3 p-4 rounded-lg border border-slate-600 cursor-pointer hover:bg-slate-700/30">
                            <input 
                                type="checkbox" 
                                wire:model="autoSchedule"
                                class="rounded bg-slate-600 border-slate-500"
                            >
                            <div>
                                <div class="font-medium text-white">Automatically Schedule This Report</div>
                                <div class="text-sm text-slate-400">Generate this report on a regular basis</div>
                            </div>
                        </label>

                        @if($autoSchedule)
                            <div class="mt-4 ml-8">
                                <label class="block text-sm font-medium text-slate-300 mb-2">Schedule Frequency</label>
                                <select 
                                    wire:model="scheduleFrequency"
                                    class="w-full bg-slate-700 text-white px-4 py-2 rounded-lg border border-slate-600 focus:border-blue-500 focus:outline-none"
                                >
                                    <option value="daily">Daily</option>
                                    <option value="weekly">Weekly</option>
                                    <option value="monthly">Monthly</option>
                                </select>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <!-- Form Actions -->
            <div class="flex gap-4 justify-between mt-8 pt-6 border-t border-slate-700">
                @if($step > 1)
                    <button type="button"
                        wire:click="previousStep"
                        class="px-6 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-600 transition font-medium"
                    >
                        Previous
                    </button>
                @else
                    <a 
                        href="{{ route('reports') }}"
                        class="px-6 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-600 transition font-medium"
                    >
                        Cancel
                    </a>
                @endif

                @if($step < 3)
                    <button type="button"
                        wire:click="nextStep"
                        class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium"
                    >
                        Next
                    </button>
                @else
                    <button 
                        wire:click="generateReport"
                        class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition font-medium flex items-center gap-2"
                        wire:loading.attr="disabled"
                        wire:target="generateReport"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <span class="ml-1" wire:loading.remove wire:target="generateReport">Generate Report</span>
                        <span class="ml-1" wire:loading wire:target="generateReport">Starting...</span>
                    </button>
                @endif
            </div>
        </div>
    </div>
</div>

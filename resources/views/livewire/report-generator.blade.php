<div class="min-h-screen bg-[var(--ink)] p-6">
    <div class="max-w-4xl mx-auto">
        <!-- Header -->
        <div class="mb-8">
            <a href="{{ route('reports') }}" class="text-[var(--gold)] hover:text-[var(--gold-soft)] font-medium mb-4 inline-block">← Back to Reports</a>
            <h1 class="text-4xl font-bold text-[var(--stone)]">Generate Report</h1>
            <p class="text-[var(--sand)] mt-2">Create custom reports for your mining operations</p>
        </div>

        <!-- Progress Steps -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                @for($i = 1; $i <= 3; $i++)
                    <div class="flex items-center flex-1">
                        <div class="flex items-center">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center font-semibold {{ $step >= $i ? 'bg-[var(--gold)] text-[var(--ink)]' : 'bg-white/10 text-[var(--sand)]' }}">
                                {{ $i }}
                            </div>
                            <span class="ml-3 text-sm font-medium {{ $step >= $i ? 'text-[var(--stone)]' : 'text-[var(--sand)]' }}">
                                @if($i === 1) Report Details
                                @elseif($i === 2) Date Range
                                @else Options
                                @endif
                            </span>
                        </div>
                        @if($i < 3)
                            <div class="flex-1 h-1 ml-4 {{ $step > $i ? 'bg-[var(--gold)]' : 'bg-white/10' }}"></div>
                        @endif
                    </div>
                @endfor
            </div>
        </div>

        <!-- Form Container -->
        <div class="bg-[var(--ink-soft)] rounded-lg border border-[var(--line)] p-8">
            <!-- Step 1: Report Details -->
            @if($step === 1)
                <div class="space-y-6">
                    <div>
                        <label class="block text-sm font-medium text-[var(--sand)] mb-2">Report Name</label>
                        <input 
                            type="text" 
                            wire:model="reportName" 
                            placeholder="e.g., January Production Summary"
                            class="w-full bg-white/10 text-[var(--stone)] px-4 py-2 rounded-lg border border-[var(--line)] focus:border-[var(--gold)] focus:outline-none"
                        >
                        @error('reportName') <span class="text-red-400 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-[var(--sand)] mb-4">Select Report Type</label>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            @foreach($reportTypes as $key => $type)
                                <div class="relative">
                                    <label 
                                        wire:click="$set('reportType', '{{ $key }}')"
                                        class="block p-4 rounded-lg border-2 cursor-pointer transition {{ $reportType === $key ? 'border-[var(--gold)] bg-[var(--gold)]/10' : 'border-[var(--line)] bg-white/5 hover:border-[var(--line)]' }}"
                                    >
                                        <div class="flex flex-col items-start gap-2">
                                            <div class="text-2xl">{{ $type['icon'] }}</div>
                                            <div class="font-medium text-[var(--stone)]">{{ $type['label'] }}</div>
                                            <div class="text-sm text-[var(--sand)] leading-relaxed">{{ $type['description'] }}</div>
                                        </div>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-[var(--sand)] mb-2">Description (Optional)</label>
                        <textarea 
                            wire:model="description" 
                            placeholder="Add any notes or context for this report..."
                            rows="4"
                            class="w-full bg-white/10 text-[var(--stone)] px-4 py-2 rounded-lg border border-[var(--line)] focus:border-[var(--gold)] focus:outline-none"
                        ></textarea>
                        @error('description') <span class="text-red-400 text-sm">{{ $message }}</span> @enderror
                    </div>
                </div>
            @endif

            <!-- Step 2: Date Range & Format -->
            @if($step === 2)
                <div class="space-y-6">
                    <div>
                        <label class="block text-sm font-medium text-[var(--sand)] mb-2">Start Date</label>
                        <input 
                            type="date" 
                            wire:model="startDate"
                            class="w-full bg-white/10 text-[var(--stone)] px-4 py-2 rounded-lg border border-[var(--line)] focus:border-[var(--gold)] focus:outline-none"
                        >
                        @error('startDate') <span class="text-red-400 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-[var(--sand)] mb-2">End Date</label>
                        <input 
                            type="date" 
                            wire:model="endDate"
                            class="w-full bg-white/10 text-[var(--stone)] px-4 py-2 rounded-lg border border-[var(--line)] focus:border-[var(--gold)] focus:outline-none"
                        >
                        @error('endDate') <span class="text-red-400 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-[var(--sand)] mb-4">Export Format</label>
                        <div class="grid grid-cols-3 gap-4">
                            @foreach(['pdf' => 'PDF', 'csv' => 'CSV', 'xlsx' => 'Excel'] as $fmt => $label)
                                <div>
                                    <label 
                                        wire:click="$set('format', '{{ $fmt }}')"
                                        class="block p-4 text-center rounded-lg border-2 cursor-pointer transition {{ $format === $fmt ? 'border-[var(--gold)] bg-[var(--gold)]/10' : 'border-[var(--line)] bg-white/5 hover:border-[var(--line)]' }}"
                                    >
                                        <div class="font-medium text-[var(--stone)]">{{ $label }}</div>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-[var(--sand)] mb-4">Select Machines (Optional)</label>
                        <div class="space-y-3 mb-4">
                            <div class="flex gap-2">
                                <button 
                                    wire:click="selectAllMachines"
                                    class="px-3 py-1 bg-white/10 hover:bg-white/20 text-[var(--stone)] text-sm rounded transition"
                                >
                                    Select All
                                </button>
                                <button 
                                    wire:click="clearMachines"
                                    class="px-3 py-1 bg-white/10 hover:bg-white/20 text-[var(--stone)] text-sm rounded transition"
                                >
                                    Clear
                                </button>
                            </div>
                            <div class="grid grid-cols-2 gap-3 max-h-48 overflow-y-auto">
                                @foreach($machines as $machine)
                                    <label class="flex items-center gap-2 p-2 rounded hover:bg-white/5 cursor-pointer">
                                        <input 
                                            type="checkbox" 
                                            wire:model="selectedMachines" 
                                            value="{{ $machine->id }}"
                                            class="rounded bg-white/10 border-[var(--line)]"
                                        >
                                        <span class="text-sm text-[var(--sand)]">{{ $machine->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Step 3: Options -->
            @if($step === 3)
                <div class="space-y-6">
                    <div class="space-y-4">
                        <label class="flex items-center gap-3 p-4 rounded-lg border border-[var(--line)] cursor-pointer hover:bg-white/5">
                            <input 
                                type="checkbox" 
                                wire:model="includeMetrics"
                                class="rounded bg-white/10 border-[var(--line)]"
                            >
                            <div>
                                <div class="font-medium text-[var(--stone)]">Include Performance Metrics</div>
                                <div class="text-sm text-[var(--sand)]">RPM, temperature, fuel usage, and load data</div>
                            </div>
                        </label>

                        <label class="flex items-center gap-3 p-4 rounded-lg border border-[var(--line)] cursor-pointer hover:bg-white/5">
                            <input 
                                type="checkbox" 
                                wire:model="includeAlerts"
                                class="rounded bg-white/10 border-[var(--line)]"
                            >
                            <div>
                                <div class="font-medium text-[var(--stone)]">Include Alerts & Issues</div>
                                <div class="text-sm text-[var(--sand)]">Warning and error events from the period</div>
                            </div>
                        </label>

                        <label class="flex items-center gap-3 p-4 rounded-lg border border-[var(--line)] cursor-pointer hover:bg-white/5">
                            <input 
                                type="checkbox" 
                                wire:model="includeChart"
                                class="rounded bg-white/10 border-[var(--line)]"
                            >
                            <div>
                                <div class="font-medium text-[var(--stone)]">Include Charts & Graphs</div>
                                <div class="text-sm text-[var(--sand)]">Visual data representations and trends</div>
                            </div>
                        </label>
                    </div>

                    <div class="border-t border-[var(--line)] pt-6">
                        <label class="flex items-center gap-3 p-4 rounded-lg border border-[var(--line)] cursor-pointer hover:bg-white/5">
                            <input 
                                type="checkbox" 
                                wire:model="autoSchedule"
                                class="rounded bg-white/10 border-[var(--line)]"
                            >
                            <div>
                                <div class="font-medium text-[var(--stone)]">Automatically Schedule This Report</div>
                                <div class="text-sm text-[var(--sand)]">Generate this report on a regular basis</div>
                            </div>
                        </label>

                        @if($autoSchedule)
                            <div class="mt-4 ml-8">
                                <label class="block text-sm font-medium text-[var(--sand)] mb-2">Schedule Frequency</label>
                                <select 
                                    wire:model="scheduleFrequency"
                                    class="w-full bg-white/10 text-[var(--stone)] px-4 py-2 rounded-lg border border-[var(--line)] focus:border-[var(--gold)] focus:outline-none"
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
            <div class="flex gap-4 justify-between mt-8 pt-6 border-t border-[var(--line)]">
                @if($step > 1)
                    <button type="button"
                        wire:click="previousStep"
                        class="px-6 py-2 bg-white/10 text-[var(--stone)] rounded-lg hover:bg-white/20 transition font-medium"
                    >
                        Previous
                    </button>
                @else
                    <a 
                        href="{{ route('reports') }}"
                        class="px-6 py-2 bg-white/10 text-[var(--stone)] rounded-lg hover:bg-white/20 transition font-medium"
                    >
                        Cancel
                    </a>
                @endif

                @if($step < 3)
                    <button type="button"
                        wire:click="nextStep"
                        class="px-6 py-2 bg-[var(--gold)] text-[var(--ink)] rounded-lg hover:bg-[var(--gold-soft)] transition font-display font-semibold"
                    >
                        Next
                    </button>
                @else
                    <button 
                        wire:click="generateReport"
                        class="px-6 py-2 bg-[var(--gold)] text-[var(--ink)] rounded-lg hover:bg-[var(--gold-soft)] transition font-display font-semibold flex items-center gap-2"
                        wire:loading.attr="disabled"
                        wire:target="generateReport"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <span class="ml-1">Generate Report</span>
                    </button>
                @endif
            </div>
        </div>
    </div>
</div>

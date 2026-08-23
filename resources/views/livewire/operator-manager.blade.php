<div class="px-6 py-8">
    <div class="mb-8 flex items-center justify-between">
        <div>
            <h2 class="text-3xl font-display font-semibold text-[var(--stone)]">Operators</h2>
            <p class="text-[var(--sand)] mt-2">The people operating the fleet — employment, licences and compliance in one place</p>
        </div>
        @can('create', \App\Models\Operator::class)
            <button wire:click="$set('showCreateModal', true)"
                    class="px-6 py-3 bg-[var(--gold)] hover:bg-[var(--gold-soft)] text-[var(--ink)] rounded-lg font-display font-semibold transition">
                + Add Operator
            </button>
        @endcan
    </div>

    {{-- Compliance headline --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg p-4">
            <div class="text-[var(--sand)] text-sm">Operators</div>
            <div class="text-2xl font-display font-semibold text-[var(--stone)]">{{ $this->counts['total'] }}</div>
        </div>
        <button wire:click="$set('complianceFilter', 'compliant')" class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg p-4 text-left hover:border-[var(--line-strong)] transition">
            <div class="text-[var(--sand)] text-sm">🟢 Compliant</div>
            <div class="text-2xl font-display font-semibold text-green-300">{{ $this->counts['compliant'] }}</div>
        </button>
        <button wire:click="$set('complianceFilter', 'expiring')" class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg p-4 text-left hover:border-[var(--line-strong)] transition">
            <div class="text-[var(--sand)] text-sm">🟡 Expiring Soon</div>
            <div class="text-2xl font-display font-semibold text-yellow-300">{{ $this->counts['expiring'] }}</div>
        </button>
        <button wire:click="$set('complianceFilter', 'non_compliant')" class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg p-4 text-left hover:border-[var(--line-strong)] transition">
            <div class="text-[var(--sand)] text-sm">🔴 Non-Compliant</div>
            <div class="text-2xl font-display font-semibold text-red-300">{{ $this->counts['non_compliant'] }}</div>
        </button>
    </div>

    {{-- Filters --}}
    <div class="flex flex-wrap gap-3 mb-6">
        <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search name or employee number…"
               class="flex-1 min-w-64 px-3 py-2 bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg text-[var(--stone)]">
        <select wire:model.live="statusFilter" class="px-3 py-2 bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg text-[var(--stone)]">
            <option value="">All statuses</option>
            @foreach(\App\Models\Operator::STATUSES as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
        <select wire:model.live="equipmentFilter" class="px-3 py-2 bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg text-[var(--stone)]">
            <option value="">All equipment</option>
            @foreach($this->equipmentTypes as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
        <select wire:model.live="complianceFilter" class="px-3 py-2 bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg text-[var(--stone)]">
            <option value="">All compliance</option>
            <option value="compliant">Compliant</option>
            <option value="expiring">Expiring soon</option>
            <option value="non_compliant">Non-compliant</option>
        </select>
    </div>

    @if($this->rows->isEmpty())
        <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg p-10 text-center">
            <h3 class="text-[var(--stone)] font-display font-semibold text-lg">No operators found</h3>
            <p class="text-[var(--sand)] mt-2">
                @if($search !== '' || $statusFilter !== '' || $complianceFilter !== '' || $equipmentFilter !== '')
                    Nothing matches the current filters.
                @else
                    Add your first operator to start tracking licences, medicals and machine authorisations.
                @endif
            </p>
        </div>
    @else
        <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-[var(--ink)] border-b border-[var(--line)]">
                        <tr>
                            <th class="px-6 py-4 text-left text-[var(--stone)] font-semibold">Operator</th>
                            <th class="px-6 py-4 text-left text-[var(--stone)] font-semibold">Employee #</th>
                            <th class="px-6 py-4 text-left text-[var(--stone)] font-semibold">Role</th>
                            <th class="px-6 py-4 text-left text-[var(--stone)] font-semibold">Site</th>
                            <th class="px-6 py-4 text-left text-[var(--stone)] font-semibold">Status</th>
                            <th class="px-6 py-4 text-left text-[var(--stone)] font-semibold">Compliance</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($this->rows as $row)
                            <tr wire:key="operator-{{ $row['operator']->id }}"
                                onclick="window.location='{{ route('operators.show', $row['operator']) }}'"
                                class="border-b border-[var(--line)] cursor-pointer hover:bg-[var(--ink)] transition">
                                <td class="px-6 py-4">
                                    <span class="text-[var(--stone)] font-semibold">{{ $row['operator']->name }}</span>
                                    @if($row['operator']->default_shift)
                                        <span class="block text-[var(--sand)] text-xs mt-0.5">{{ ucfirst($row['operator']->default_shift) }} shift</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-[var(--sand)]">{{ $row['operator']->employee_number }}</td>
                                <td class="px-6 py-4 text-[var(--sand)]">{{ $row['operator']->job_title ?? '—' }}</td>
                                <td class="px-6 py-4 text-[var(--sand)]">{{ $row['operator']->mineArea?->name ?? '—' }}</td>
                                <td class="px-6 py-4">
                                    <span class="inline-block px-2 py-1 text-xs rounded {{ $row['operator']->employment_status === 'active' ? 'bg-green-900 text-green-200' : 'bg-white/10 text-[var(--sand)]' }}">
                                        {{ \App\Models\Operator::STATUSES[$row['operator']->employment_status] ?? ucfirst($row['operator']->employment_status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    @if($row['verdict'] === 'compliant')
                                        <span class="inline-block px-2 py-1 bg-green-900 text-green-200 text-xs rounded">🟢 Compliant</span>
                                    @elseif($row['verdict'] === 'expiring')
                                        <span class="inline-block px-2 py-1 bg-yellow-900 text-yellow-200 text-xs rounded">🟡 Expiring Soon</span>
                                    @else
                                        <span class="inline-block px-2 py-1 bg-red-900 text-red-200 text-xs rounded">🔴 Non-Compliant</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if($showCreateModal)
        <x-dialog-modal wire:model.live="showCreateModal">
            <x-slot name="title">Add an operator</x-slot>
            <x-slot name="content">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm text-[var(--stone)] mb-1">Employee number</label>
                        <input type="text" wire:model="form.employee_number" class="w-full px-3 py-2 bg-[var(--ink)] border border-[var(--line)] rounded text-[var(--stone)]">
                        @error('form.employee_number') <p class="text-red-300 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm text-[var(--stone)] mb-1">Default shift</label>
                        <select wire:model="form.default_shift" class="w-full px-3 py-2 bg-[var(--ink)] border border-[var(--line)] rounded text-[var(--stone)]">
                            <option value="day">Day (06:00–18:00)</option>
                            <option value="night">Night (18:00–06:00)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm text-[var(--stone)] mb-1">First name</label>
                        <input type="text" wire:model="form.first_name" class="w-full px-3 py-2 bg-[var(--ink)] border border-[var(--line)] rounded text-[var(--stone)]">
                        @error('form.first_name') <p class="text-red-300 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm text-[var(--stone)] mb-1">Last name</label>
                        <input type="text" wire:model="form.last_name" class="w-full px-3 py-2 bg-[var(--ink)] border border-[var(--line)] rounded text-[var(--stone)]">
                        @error('form.last_name') <p class="text-red-300 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm text-[var(--stone)] mb-1">Phone</label>
                        <input type="text" wire:model="form.phone" class="w-full px-3 py-2 bg-[var(--ink)] border border-[var(--line)] rounded text-[var(--stone)]">
                    </div>
                    <div>
                        <label class="block text-sm text-[var(--stone)] mb-1">Job title</label>
                        <input type="text" wire:model="form.job_title" placeholder="Machine Operator" class="w-full px-3 py-2 bg-[var(--ink)] border border-[var(--line)] rounded text-[var(--stone)]">
                    </div>
                </div>
                <p class="text-[var(--sand)] text-xs mt-4">Licences, medical fitness and training are added from the operator's page after creation.</p>
            </x-slot>
            <x-slot name="footer">
                <button wire:click="$set('showCreateModal', false)" class="px-4 py-2 text-[var(--sand)] hover:text-[var(--stone)] transition">Cancel</button>
                <button wire:click="create" wire:loading.attr="disabled"
                        class="ml-3 px-5 py-2 bg-[var(--gold)] hover:bg-[var(--gold-soft)] text-[var(--ink)] rounded-lg font-semibold transition">
                    Create operator
                </button>
            </x-slot>
        </x-dialog-modal>
    @endif
</div>

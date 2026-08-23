<div class="px-6 py-8">
    {{-- Overview header --}}
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <a href="{{ route('operators.index') }}" class="text-[var(--sand)] text-sm hover:text-[var(--stone)]">&larr; Operators</a>
            <h2 class="text-3xl font-display font-semibold text-[var(--stone)] mt-1">{{ $operator->name }}</h2>
            <p class="text-[var(--sand)] mt-1">
                {{ $operator->job_title ?? 'Operator' }} · {{ $operator->employee_number }}
                @if($operator->mineArea) · {{ $operator->mineArea->name }} @endif
                @if($operator->default_shift) · {{ ucfirst($operator->default_shift) }} shift @endif
            </p>
        </div>
        <div class="flex items-center gap-3">
            @if($this->compliance['verdict'] === 'compliant')
                <span class="px-3 py-1.5 bg-green-900 text-green-200 rounded-lg text-sm font-semibold">🟢 Compliant</span>
            @elseif($this->compliance['verdict'] === 'expiring')
                <span class="px-3 py-1.5 bg-yellow-900 text-yellow-200 rounded-lg text-sm font-semibold">🟡 Expiring Soon</span>
            @else
                <span class="px-3 py-1.5 bg-red-900 text-red-200 rounded-lg text-sm font-semibold">🔴 Non-Compliant</span>
            @endif
            @can('update', $operator)
                <select wire:change="setEmploymentStatus($event.target.value)"
                        class="px-3 py-1.5 bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg text-[var(--stone)] text-sm">
                    @foreach(\App\Models\Operator::STATUSES as $value => $label)
                        <option value="{{ $value }}" @selected($operator->employment_status === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            @endcan
        </div>
    </div>

    {{-- Compliance table: the immediate answer to "can they work?" --}}
    <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg p-5 mb-6">
        <h3 class="text-[var(--stone)] font-display font-semibold mb-3">Compliance</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-[var(--sand)] border-b border-[var(--line)]">
                        <th class="text-left py-2">Requirement</th>
                        <th class="text-left py-2">Status</th>
                        <th class="text-left py-2">Detail</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($this->compliance['items'] as $item)
                        <tr wire:key="compliance-{{ $item['requirement'] }}" class="border-b border-[var(--line)]">
                            <td class="py-2 text-[var(--stone)]">{{ $item['label'] }}</td>
                            <td class="py-2">
                                @if($item['status'] === 'valid' || $item['status'] === 'perpetual')
                                    <span class="text-green-300">🟢 Valid</span>
                                @elseif($item['status'] === 'expiring')
                                    <span class="text-yellow-300">🟡 Expiring Soon</span>
                                @elseif($item['status'] === 'missing')
                                    <span class="text-red-300">🔴 Missing</span>
                                @else
                                    <span class="text-red-300">🔴 Expired</span>
                                @endif
                            </td>
                            <td class="py-2 text-[var(--sand)]">{{ $item['detail'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Equipment authorisation matrix --}}
    <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg p-5 mb-6">
        <h3 class="text-[var(--stone)] font-display font-semibold mb-1">Equipment Authorisation</h3>
        <p class="text-[var(--sand)] text-sm mb-3">What this operator is currently licensed to operate.</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
            @foreach($this->authorisations as $auth)
                <div wire:key="auth-{{ $auth['type'] }}" class="flex items-center justify-between px-3 py-2 rounded border {{ $auth['authorised'] ? 'border-green-900 bg-green-900/10' : 'border-[var(--line)]' }}">
                    <span class="text-[var(--stone)] text-sm">{{ $auth['label'] }}</span>
                    @if($auth['authorised'])
                        <span class="text-green-300 text-xs">✓ {{ $auth['licence']?->expires_on?->format('d/m/Y') ?? 'No expiry' }}</span>
                    @elseif($auth['licence'] !== null)
                        <span class="text-red-300 text-xs">Lapsed</span>
                    @else
                        <span class="text-[var(--sand)] text-xs">—</span>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Qualifications --}}
        <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg p-5">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-[var(--stone)] font-display font-semibold">Qualifications &amp; Licences</h3>
                @can('update', $operator)
                    <button wire:click="$set('showQualificationModal', true)" class="px-3 py-1.5 text-sm border border-[var(--line-strong)] text-[var(--sand)] hover:text-[var(--stone)] rounded transition">+ Add</button>
                @endcan
            </div>
            @forelse($operator->qualifications as $qualification)
                <div wire:key="qual-{{ $qualification->id }}" class="border-t border-[var(--line)] py-3">
                    <div class="flex items-center justify-between">
                        <span class="text-[var(--stone)] font-semibold">{{ $qualification->title }}</span>
                        @if($qualification->standing !== 'active')
                            <span class="text-red-300 text-xs uppercase">{{ $qualification->standing }}</span>
                        @elseif($qualification->expiryStatus() === 'expired')
                            <span class="text-red-300 text-xs">Expired</span>
                        @elseif($qualification->expiryStatus() === 'expiring')
                            <span class="text-yellow-300 text-xs">Expires in {{ $qualification->daysUntilExpiry() }} days</span>
                        @else
                            <span class="text-green-300 text-xs">Valid</span>
                        @endif
                    </div>
                    <div class="text-[var(--sand)] text-xs mt-1">
                        @if($qualification->licence_number) № {{ $qualification->licence_number }} · @endif
                        @if($qualification->equipment_type) {{ \App\Support\EquipmentType::label($qualification->equipment_type) }} · @endif
                        @if($qualification->issued_on) Issued {{ $qualification->issued_on->format('d/m/Y') }} · @endif
                        @if($qualification->expires_on) Expires {{ $qualification->expires_on->format('d/m/Y') }} @else No expiry @endif
                    </div>
                </div>
            @empty
                <p class="text-[var(--sand)] text-sm">No qualifications on record.</p>
            @endforelse
        </div>

        {{-- Training --}}
        <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg p-5">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-[var(--stone)] font-display font-semibold">Training &amp; Competency</h3>
                @can('update', $operator)
                    <button wire:click="$set('showTrainingModal', true)" class="px-3 py-1.5 text-sm border border-[var(--line-strong)] text-[var(--sand)] hover:text-[var(--stone)] rounded transition">+ Add</button>
                @endcan
            </div>
            @forelse($operator->trainings as $training)
                <div wire:key="training-{{ $training->id }}" class="border-t border-[var(--line)] py-3">
                    <div class="flex items-center justify-between">
                        <span class="text-[var(--stone)] font-semibold">{{ $training->course }}</span>
                        @if($training->competency !== 'competent')
                            <span class="text-yellow-300 text-xs uppercase">{{ str_replace('_', ' ', $training->competency) }}</span>
                        @elseif($training->expiryStatus() === 'expired')
                            <span class="text-red-300 text-xs">Refresher due</span>
                        @elseif($training->expiryStatus() === 'expiring')
                            <span class="text-yellow-300 text-xs">Renewal in {{ $training->daysUntilExpiry() }} days</span>
                        @else
                            <span class="text-green-300 text-xs">Current</span>
                        @endif
                    </div>
                    <div class="text-[var(--sand)] text-xs mt-1">
                        @if($training->category) {{ \App\Models\OperatorTraining::CATEGORIES[$training->category] ?? $training->category }} · @endif
                        @if($training->completed_on) Completed {{ $training->completed_on->format('d/m/Y') }} · @endif
                        @if($training->expires_on) Renew by {{ $training->expires_on->format('d/m/Y') }} @endif
                    </div>
                </div>
            @empty
                <p class="text-[var(--sand)] text-sm">No training on record.</p>
            @endforelse
        </div>
    </div>

    {{-- Medical: rendered only for users holding the medical permission --}}
    @if($this->canViewMedical)
        <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg p-5 mt-6">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-[var(--stone)] font-display font-semibold">Occupational Medical</h3>
                @if($this->canManageMedical)
                    <button wire:click="$set('showMedicalModal', true)" class="px-3 py-1.5 text-sm border border-[var(--line-strong)] text-[var(--sand)] hover:text-[var(--stone)] rounded transition">+ Record</button>
                @endif
            </div>
            @forelse($operator->medicals->sortByDesc('expires_on') as $medical)
                <div wire:key="medical-{{ $medical->id }}" class="border-t border-[var(--line)] py-3">
                    <div class="flex items-center justify-between">
                        <span class="text-[var(--stone)] font-semibold">{{ \App\Models\OperatorMedical::FITNESS_LABELS[$medical->fitness] ?? $medical->fitness }}</span>
                        @if(! $medical->isInGoodStanding())
                            <span class="text-red-300 text-xs">Not fit</span>
                        @elseif($medical->expiryStatus() === 'expired')
                            <span class="text-red-300 text-xs">Expired</span>
                        @elseif($medical->expiryStatus() === 'expiring')
                            <span class="text-yellow-300 text-xs">Expires in {{ $medical->daysUntilExpiry() }} days</span>
                        @else
                            <span class="text-green-300 text-xs">Valid</span>
                        @endif
                    </div>
                    <div class="text-[var(--sand)] text-xs mt-1">
                        @if($medical->certificate_number) № {{ $medical->certificate_number }} · @endif
                        @if($medical->provider) {{ $medical->provider }} · @endif
                        @if($medical->examined_on) Examined {{ $medical->examined_on->format('d/m/Y') }} · @endif
                        @if($medical->expires_on) Expires {{ $medical->expires_on->format('d/m/Y') }} @endif
                    </div>
                    @if($medical->has_restrictions && $medical->restrictions)
                        <div class="mt-2 px-3 py-2 bg-yellow-900/20 border border-yellow-900 rounded text-yellow-200 text-xs">
                            Restrictions: {{ $medical->restrictions }}
                        </div>
                    @endif
                </div>
            @empty
                <p class="text-[var(--sand)] text-sm">No medical certificates on record.</p>
            @endforelse
        </div>
    @endif

    {{-- Employment & contact --}}
    <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg p-5 mt-6">
        <h3 class="text-[var(--stone)] font-display font-semibold mb-3">Employment &amp; Contact</h3>
        <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-3 text-sm">
            <div><dt class="text-[var(--sand)]">Department</dt><dd class="text-[var(--stone)]">{{ $operator->department ?? '—' }}</dd></div>
            <div><dt class="text-[var(--sand)]">Employment type</dt><dd class="text-[var(--stone)]">{{ $operator->employment_type ? ucfirst($operator->employment_type) : '—' }}</dd></div>
            <div><dt class="text-[var(--sand)]">Employed since</dt><dd class="text-[var(--stone)]">{{ $operator->employed_from?->format('d/m/Y') ?? '—' }}</dd></div>
            <div><dt class="text-[var(--sand)]">Phone</dt><dd class="text-[var(--stone)]">{{ $operator->phone ?? '—' }}</dd></div>
            <div><dt class="text-[var(--sand)]">Email</dt><dd class="text-[var(--stone)]">{{ $operator->email ?? '—' }}</dd></div>
            <div><dt class="text-[var(--sand)]">Supervisor</dt><dd class="text-[var(--stone)]">{{ $operator->supervisor?->name ?? '—' }}</dd></div>
            <div class="sm:col-span-2 lg:col-span-3"><dt class="text-[var(--sand)]">Emergency contact</dt>
                <dd class="text-[var(--stone)]">
                    @if($operator->emergency_contact_name)
                        {{ $operator->emergency_contact_name }}
                        @if($operator->emergency_contact_relationship) ({{ $operator->emergency_contact_relationship }}) @endif
                        @if($operator->emergency_contact_phone) · {{ $operator->emergency_contact_phone }} @endif
                    @else — @endif
                </dd>
            </div>
        </dl>
    </div>

    {{-- Add qualification --}}
    @if($showQualificationModal)
        <x-dialog-modal wire:model.live="showQualificationModal">
            <x-slot name="title">Add a qualification or licence</x-slot>
            <x-slot name="content">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <label class="block text-sm text-[var(--stone)] mb-1">Title</label>
                        <input type="text" wire:model="qualificationForm.title" placeholder="ADT Operator" class="w-full px-3 py-2 bg-[var(--ink)] border border-[var(--line)] rounded text-[var(--stone)]">
                        @error('qualificationForm.title') <p class="text-red-300 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm text-[var(--stone)] mb-1">Licence number</label>
                        <input type="text" wire:model="qualificationForm.licence_number" class="w-full px-3 py-2 bg-[var(--ink)] border border-[var(--line)] rounded text-[var(--stone)]">
                    </div>
                    <div>
                        <label class="block text-sm text-[var(--stone)] mb-1">Authorises equipment</label>
                        <select wire:model="qualificationForm.equipment_type" class="w-full px-3 py-2 bg-[var(--ink)] border border-[var(--line)] rounded text-[var(--stone)]">
                            <option value="">None — not equipment-specific</option>
                            @foreach(\App\Support\EquipmentType::CATALOGUE as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm text-[var(--stone)] mb-1">Issuing authority</label>
                        <input type="text" wire:model="qualificationForm.issuing_authority" class="w-full px-3 py-2 bg-[var(--ink)] border border-[var(--line)] rounded text-[var(--stone)]">
                    </div>
                    <div>
                        <label class="block text-sm text-[var(--stone)] mb-1">Issued</label>
                        <input type="date" wire:model="qualificationForm.issued_on" class="w-full px-3 py-2 bg-[var(--ink)] border border-[var(--line)] rounded text-[var(--stone)]">
                    </div>
                    <div>
                        <label class="block text-sm text-[var(--stone)] mb-1">Expires</label>
                        <input type="date" wire:model="qualificationForm.expires_on" class="w-full px-3 py-2 bg-[var(--ink)] border border-[var(--line)] rounded text-[var(--stone)]">
                        @error('qualificationForm.expires_on') <p class="text-red-300 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
            </x-slot>
            <x-slot name="footer">
                <button wire:click="$set('showQualificationModal', false)" class="px-4 py-2 text-[var(--sand)] hover:text-[var(--stone)] transition">Cancel</button>
                <button wire:click="addQualification" wire:loading.attr="disabled" class="ml-3 px-5 py-2 bg-[var(--gold)] hover:bg-[var(--gold-soft)] text-[var(--ink)] rounded-lg font-semibold transition">Add</button>
            </x-slot>
        </x-dialog-modal>
    @endif

    {{-- Record medical --}}
    @if($showMedicalModal && $this->canManageMedical)
        <x-dialog-modal wire:model.live="showMedicalModal">
            <x-slot name="title">Record a medical examination</x-slot>
            <x-slot name="content">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm text-[var(--stone)] mb-1">Fitness finding</label>
                        <select wire:model="medicalForm.fitness" class="w-full px-3 py-2 bg-[var(--ink)] border border-[var(--line)] rounded text-[var(--stone)]">
                            @foreach(\App\Models\OperatorMedical::FITNESS_LABELS as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm text-[var(--stone)] mb-1">Certificate number</label>
                        <input type="text" wire:model="medicalForm.certificate_number" class="w-full px-3 py-2 bg-[var(--ink)] border border-[var(--line)] rounded text-[var(--stone)]">
                    </div>
                    <div>
                        <label class="block text-sm text-[var(--stone)] mb-1">Provider</label>
                        <input type="text" wire:model="medicalForm.provider" class="w-full px-3 py-2 bg-[var(--ink)] border border-[var(--line)] rounded text-[var(--stone)]">
                    </div>
                    <div>
                        <label class="block text-sm text-[var(--stone)] mb-1">Examined</label>
                        <input type="date" wire:model="medicalForm.examined_on" class="w-full px-3 py-2 bg-[var(--ink)] border border-[var(--line)] rounded text-[var(--stone)]">
                    </div>
                    <div>
                        <label class="block text-sm text-[var(--stone)] mb-1">Expires</label>
                        <input type="date" wire:model="medicalForm.expires_on" class="w-full px-3 py-2 bg-[var(--ink)] border border-[var(--line)] rounded text-[var(--stone)]">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm text-[var(--stone)] mb-1">Restrictions <span class="text-[var(--sand)]">(if any)</span></label>
                        <textarea wire:model="medicalForm.restrictions" rows="2" placeholder="e.g. No night shift" class="w-full px-3 py-2 bg-[var(--ink)] border border-[var(--line)] rounded text-[var(--stone)]"></textarea>
                    </div>
                </div>
            </x-slot>
            <x-slot name="footer">
                <button wire:click="$set('showMedicalModal', false)" class="px-4 py-2 text-[var(--sand)] hover:text-[var(--stone)] transition">Cancel</button>
                <button wire:click="addMedical" wire:loading.attr="disabled" class="ml-3 px-5 py-2 bg-[var(--gold)] hover:bg-[var(--gold-soft)] text-[var(--ink)] rounded-lg font-semibold transition">Record</button>
            </x-slot>
        </x-dialog-modal>
    @endif

    {{-- Add training --}}
    @if($showTrainingModal)
        <x-dialog-modal wire:model.live="showTrainingModal">
            <x-slot name="title">Add training</x-slot>
            <x-slot name="content">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <label class="block text-sm text-[var(--stone)] mb-1">Course</label>
                        <input type="text" wire:model="trainingForm.course" placeholder="Site Induction" class="w-full px-3 py-2 bg-[var(--ink)] border border-[var(--line)] rounded text-[var(--stone)]">
                        @error('trainingForm.course') <p class="text-red-300 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm text-[var(--stone)] mb-1">Category</label>
                        <select wire:model="trainingForm.category" class="w-full px-3 py-2 bg-[var(--ink)] border border-[var(--line)] rounded text-[var(--stone)]">
                            <option value="">—</option>
                            @foreach(\App\Models\OperatorTraining::CATEGORIES as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm text-[var(--stone)] mb-1">Equipment <span class="text-[var(--sand)]">(if machine-specific)</span></label>
                        <select wire:model="trainingForm.equipment_type" class="w-full px-3 py-2 bg-[var(--ink)] border border-[var(--line)] rounded text-[var(--stone)]">
                            <option value="">—</option>
                            @foreach(\App\Support\EquipmentType::CATALOGUE as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm text-[var(--stone)] mb-1">Completed</label>
                        <input type="date" wire:model="trainingForm.completed_on" class="w-full px-3 py-2 bg-[var(--ink)] border border-[var(--line)] rounded text-[var(--stone)]">
                    </div>
                    <div>
                        <label class="block text-sm text-[var(--stone)] mb-1">Renew by</label>
                        <input type="date" wire:model="trainingForm.expires_on" class="w-full px-3 py-2 bg-[var(--ink)] border border-[var(--line)] rounded text-[var(--stone)]">
                    </div>
                </div>
            </x-slot>
            <x-slot name="footer">
                <button wire:click="$set('showTrainingModal', false)" class="px-4 py-2 text-[var(--sand)] hover:text-[var(--stone)] transition">Cancel</button>
                <button wire:click="addTraining" wire:loading.attr="disabled" class="ml-3 px-5 py-2 bg-[var(--gold)] hover:bg-[var(--gold-soft)] text-[var(--ink)] rounded-lg font-semibold transition">Add</button>
            </x-slot>
        </x-dialog-modal>
    @endif
</div>

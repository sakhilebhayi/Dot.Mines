<div class="min-h-screen bg-slate-900 p-6" wire:poll.10s="loadIntegrations">
    <div class="max-w-6xl mx-auto">

        {{-- Header --}}
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-3xl font-bold text-white">Integrations</h1>
                <p class="text-slate-400 mt-1">Connect your equipment manufacturer APIs — machines sync automatically to your fleet.</p>
            </div>
            <button wire:click="openAddModal"
                class="flex items-center gap-2 px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Add Integration
            </button>
        </div>

        {{-- Provider Grid --}}
        <div class="grid grid-cols-3 md:grid-cols-5 lg:grid-cols-7 gap-3 mb-8">
            @foreach($availableProviders as $slug => $provider)
                @php $isConnected = collect($integrations)->contains('provider', $slug); @endphp
                <div class="bg-slate-800 border {{ $isConnected ? 'border-emerald-500/50' : 'border-slate-700' }} rounded-lg p-3 text-center relative">
                    @if($isConnected)
                        <span class="absolute top-1.5 right-1.5 w-2 h-2 bg-emerald-400 rounded-full"></span>
                    @endif
                    <div class="text-2xl mb-1">{{ $provider['icon'] }}</div>
                    <p class="text-white text-xs font-medium leading-tight">{{ $provider['name'] }}</p>
                    <p class="text-slate-500 text-[10px] mt-0.5">{{ $isConnected ? 'Connected' : 'Available' }}</p>
                </div>
            @endforeach
        </div>

        {{-- Active Integrations --}}
        @if(count($integrations) > 0)
            <div class="space-y-3">
                @foreach($integrations as $integration)
                    <div class="bg-slate-800 border border-slate-700 rounded-xl p-5 hover:border-slate-600 transition-colors">
                        <div class="flex items-center justify-between gap-4">
                            <div class="flex items-center gap-4 min-w-0">
                                <div class="text-3xl flex-shrink-0">
                                    {{ $availableProviders[$integration['provider']]['icon'] ?? '📦' }}
                                </div>
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <p class="text-white font-semibold">{{ $integration['name'] }}</p>
                                        @php
                                            $statusColour = match($integration['status']) {
                                                'connected' => 'bg-emerald-500/20 text-emerald-300',
                                                'testing'   => 'bg-amber-500/20 text-amber-300',
                                                'disconnected', 'error' => 'bg-red-500/20 text-red-300',
                                                default     => 'bg-slate-700 text-slate-300',
                                            };
                                        @endphp
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold {{ $statusColour }}">
                                            {{ ucfirst($integration['status']) }}
                                        </span>
                                    </div>
                                    <p class="text-slate-400 text-sm mt-0.5">
                                        {{ $availableProviders[$integration['provider']]['name'] ?? $integration['provider'] }}
                                        &middot; Last sync: {{ $integration['last_sync_at'] }}
                                        @if($integration['machines_count'] > 0)
                                            &middot; {{ $integration['machines_count'] }} machines
                                        @endif
                                    </p>
                                    @if($integration['last_error'])
                                        <p class="text-red-400 text-xs mt-1 truncate max-w-md">⚠ {{ $integration['last_error'] }}</p>
                                    @endif
                                </div>
                            </div>

                            <div class="flex items-center gap-2 flex-shrink-0">
                                <select
                                    wire:change="updateSyncFrequency({{ $integration['id'] }}, $event.target.value)"
                                    class="text-xs bg-slate-700 border border-slate-600 text-slate-300 rounded px-2 py-1.5 focus:outline-none focus:border-blue-500"
                                    title="Sync frequency">
                                    @foreach([
                                        'every_5_minutes'  => 'Every 5 min',
                                        'every_15_minutes' => 'Every 15 min',
                                        'hourly'           => 'Hourly',
                                        'every_6_hours'    => 'Every 6 hrs',
                                        'daily'            => 'Daily',
                                        'manual'           => 'Manual only',
                                    ] as $value => $label)
                                        <option value="{{ $value }}" {{ $integration['sync_frequency'] === $value ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>

                                <button wire:click="syncNow({{ $integration['id'] }})"
                                    wire:loading.attr="disabled" wire:target="syncNow({{ $integration['id'] }})"
                                    class="px-3 py-1.5 bg-emerald-700 hover:bg-emerald-600 text-emerald-100 rounded text-xs font-medium transition-colors">
                                    <span wire:loading.remove wire:target="syncNow({{ $integration['id'] }})">🔄 Sync Now</span>
                                    <span wire:loading wire:target="syncNow({{ $integration['id'] }})">Queuing…</span>
                                </button>

                                <button wire:click="retestConnection({{ $integration['id'] }})"
                                    class="px-3 py-1.5 bg-blue-800 hover:bg-blue-700 text-blue-200 rounded text-xs font-medium transition-colors">
                                    🧪 Re-test
                                </button>

                                <button wire:click="openDetailPanel({{ $integration['id'] }})"
                                    class="px-3 py-1.5 bg-slate-700 hover:bg-slate-600 text-slate-300 rounded text-xs font-medium transition-colors">
                                    Logs
                                </button>

                                <button wire:click="deleteIntegration({{ $integration['id'] }})"
                                    wire:confirm="Delete this integration? Synced machines remain but future syncs stop."
                                    class="px-3 py-1.5 bg-red-900 hover:bg-red-800 text-red-300 rounded text-xs font-medium transition-colors">
                                    Delete
                                </button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="bg-slate-800 border border-slate-700 rounded-xl p-16 text-center">
                <div class="text-5xl mb-4">🔌</div>
                <h3 class="text-xl font-semibold text-white mb-2">No integrations yet</h3>
                <p class="text-slate-400 mb-6">Add your first OEM API connection to start syncing machines automatically.</p>
                <button wire:click="openAddModal"
                    class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors">
                    + Add Integration
                </button>
            </div>
        @endif
    </div>

    {{-- Add Integration Modal --}}
    @if($showAddModal)
        <div class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 p-4">
            <div class="bg-slate-800 border border-slate-700 rounded-xl shadow-2xl w-full max-w-lg">
                <div class="px-6 py-4 border-b border-slate-700 flex items-center justify-between">
                    <h2 class="text-lg font-bold text-white">Add Integration</h2>
                    <button wire:click="closeAddModal" class="text-slate-400 hover:text-white text-xl leading-none">✕</button>
                </div>

                <div class="p-6 space-y-4 max-h-[70vh] overflow-y-auto">
                    @error('general')
                        <div class="p-3 bg-red-900/50 border border-red-700 rounded text-red-300 text-sm">{{ $message }}</div>
                    @enderror

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1.5">Manufacturer *</label>
                        <select wire:model.live="formData.provider"
                            class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 focus:outline-none focus:border-blue-500 text-sm">
                            <option value="">Select a manufacturer…</option>
                            @foreach($availableProviders as $slug => $provider)
                                <option value="{{ $slug }}">{{ $provider['icon'] }} {{ $provider['name'] }}</option>
                            @endforeach
                        </select>
                        @error('formData.provider') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1.5">Integration Name *</label>
                        <input type="text" wire:model="formData.name"
                            placeholder="e.g. Site A — Bell Fleet"
                            class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 focus:outline-none focus:border-blue-500 text-sm placeholder-slate-500">
                        @error('formData.name') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    @if($formData['provider'] && count($credentialFields) > 0)
                        <div class="border-t border-slate-700 pt-4">
                            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-3">API Credentials</p>
                            @foreach($credentialFields as $field)
                                <div class="mb-3">
                                    <label class="block text-sm font-medium text-slate-300 mb-1.5">
                                        {{ $field['label'] }}{{ ($field['required'] ?? false) ? ' *' : '' }}
                                    </label>
                                    <input
                                        type="{{ $field['type'] }}"
                                        wire:model="formData.credentials.{{ $field['key'] }}"
                                        placeholder="{{ $field['placeholder'] ?? '' }}"
                                        class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 focus:outline-none focus:border-blue-500 text-sm placeholder-slate-500">
                                    @if(!empty($field['hint']))
                                        <p class="text-slate-500 text-xs mt-1">{{ $field['hint'] }}</p>
                                    @endif
                                    @error("credentials.{$field['key']}") <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1.5">Sync Frequency</label>
                        <select wire:model="formData.sync_frequency"
                            class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-3 py-2 focus:outline-none focus:border-blue-500 text-sm">
                            <option value="every_5_minutes">Every 5 minutes</option>
                            <option value="every_15_minutes">Every 15 minutes</option>
                            <option value="hourly">Hourly</option>
                            <option value="every_6_hours">Every 6 hours</option>
                            <option value="daily">Daily</option>
                            <option value="manual">Manual only</option>
                        </select>
                    </div>

                    <div class="bg-blue-900/20 border border-blue-700/40 rounded-lg p-3 text-xs text-blue-300">
                        <strong>After saving:</strong> we'll immediately test your credentials. If successful,
                        machines appear in your Fleet page within one sync cycle. Credentials are encrypted at rest.
                    </div>
                </div>

                <div class="px-6 py-4 border-t border-slate-700 flex gap-3">
                    <button wire:click="closeAddModal"
                        class="flex-1 px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg text-sm font-medium transition-colors">
                        Cancel
                    </button>
                    <button wire:click="createIntegration"
                        wire:loading.attr="disabled" wire:target="createIntegration"
                        class="flex-1 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium transition-colors">
                        <span wire:loading.remove wire:target="createIntegration">Save &amp; Test Connection</span>
                        <span wire:loading wire:target="createIntegration">Saving…</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Sync Logs Panel --}}
    @if($showDetailPanel && $detailIntegrationId !== null)
        @php $detailIntegration = collect($integrations)->firstWhere('id', $detailIntegrationId); @endphp
        <div class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 p-4">
            <div class="bg-slate-800 border border-slate-700 rounded-xl shadow-2xl w-full max-w-2xl">
                <div class="px-6 py-4 border-b border-slate-700 flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-white">Sync History</h2>
                        <p class="text-slate-400 text-sm">{{ $detailIntegration['name'] ?? '' }}</p>
                    </div>
                    <button wire:click="closeDetailPanel" class="text-slate-400 hover:text-white text-xl leading-none">✕</button>
                </div>

                <div class="p-6 max-h-[60vh] overflow-y-auto">
                    @if(count($syncLogs) > 0)
                        <table class="w-full text-sm">
                            <thead class="border-b border-slate-700">
                                <tr>
                                    <th class="text-left px-2 py-2 text-slate-400 text-xs">Started</th>
                                    <th class="text-left px-2 py-2 text-slate-400 text-xs">Duration</th>
                                    <th class="text-left px-2 py-2 text-slate-400 text-xs">Status</th>
                                    <th class="text-left px-2 py-2 text-slate-400 text-xs">Machines</th>
                                    <th class="text-left px-2 py-2 text-slate-400 text-xs">Error</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-700/50">
                                @foreach($syncLogs as $log)
                                    <tr class="hover:bg-slate-700/30">
                                        <td class="px-2 py-2 text-slate-300 font-mono text-xs">{{ $log['started_at'] }}</td>
                                        <td class="px-2 py-2 text-slate-400 text-xs">{{ $log['duration'] }}</td>
                                        <td class="px-2 py-2">
                                            @php
                                                $c = match($log['status']) {
                                                    'success' => 'text-emerald-400',
                                                    'running' => 'text-amber-400',
                                                    'failed'  => 'text-red-400',
                                                    default   => 'text-slate-400',
                                                };
                                            @endphp
                                            <span class="text-xs font-semibold {{ $c }}">{{ ucfirst($log['status']) }}</span>
                                        </td>
                                        <td class="px-2 py-2 text-slate-300 text-xs">{{ $log['machines_synced'] }}</td>
                                        <td class="px-2 py-2 text-red-400 text-xs truncate max-w-xs">{{ $log['error_message'] ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <div class="text-center py-8 text-slate-400 text-sm">No sync history yet.</div>
                    @endif
                </div>

                <div class="px-6 py-4 border-t border-slate-700">
                    <button wire:click="closeDetailPanel"
                        class="w-full px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg text-sm font-medium transition-colors">
                        Close
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>

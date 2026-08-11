<div class="px-6 py-8">
    <!-- Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-3xl font-display font-semibold text-[var(--stone)]">Integrations</h2>
                <p class="text-[var(--sand)] mt-2">Manage your equipment manufacturer connections</p>
            </div>
            <button
                wire:click="openAddModal"
                class="px-6 py-3 bg-[var(--gold)] hover:bg-[var(--gold-soft)] text-[var(--ink)] rounded-lg font-display font-semibold transition"
            >
                + Add Integration
            </button>
        </div>

        <div class="mt-4 p-4 bg-[var(--gold)]/10 border border-[var(--gold)]/20 rounded-lg text-[var(--sand)] text-sm">
            <strong class="text-[var(--stone)]">Integration Setup Guide:</strong><br>
            1. Select your equipment manufacturer.<br>
            2. Enter your API credentials (get these from your provider dashboard).<br>
            3. Optionally set a custom API endpoint, sync frequency, and connection type.<br>
            4. Test the connection before saving.<br>
            5. For webhook integrations, copy the provided URL and set it in your provider dashboard.<br>
            6. You will receive alerts at your notification email if sync fails.
        </div>
    </div>

    <!-- Available Manufacturers Info -->
    <div class="grid grid-cols-1 lg:grid-cols-7 gap-4 mb-8">
        @foreach($availableManufacturers as $key => $mfr)
            <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg p-4 text-center">
                <div class="text-3xl mb-2">{{ $mfr['icon'] }}</div>
                <h3 class="text-[var(--stone)] font-semibold">{{ $mfr['name'] }}</h3>
                <p class="text-[var(--sand)] text-xs mt-1">{{ $mfr['description'] }}</p>
                <div class="mt-3">
                    @if(in_array($key, array_map(fn($i) => $i['provider'], $integrations)))
                        <span class="inline-block px-2 py-1 bg-green-900 text-green-200 text-xs rounded">Connected</span>
                    @elseif(($mfr['status'] ?? 'available') === 'coming_soon')
                        <span class="inline-block px-2 py-1 bg-yellow-900 text-yellow-200 text-xs rounded">Coming Soon</span>
                    @else
                        <span class="inline-block px-2 py-1 bg-white/10 text-[var(--sand)] text-xs rounded">Available</span>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    <!-- Integrations List -->
    @if(count($integrations) > 0)
        <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-[var(--ink)] border-b border-[var(--line)]">
                        <tr>
                            <th class="px-6 py-4 text-left text-[var(--stone)] font-semibold">Manufacturer</th>
                            <th class="px-6 py-4 text-left text-[var(--stone)] font-semibold">Status</th>
                            <th class="px-6 py-4 text-left text-[var(--stone)] font-semibold">Last Sync</th>
                            <th class="px-6 py-4 text-left text-[var(--stone)] font-semibold">Created</th>
                            <th class="px-6 py-4 text-right text-[var(--stone)] font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($integrations as $integration)
                            <tr class="border-t border-[var(--line)] hover:bg-white/5 transition">
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <span class="text-2xl mr-3">
                                            {{ $availableManufacturers[$integration['provider']]['icon'] ?? '📦' }}
                                        </span>
                                        <div>
                                            <p class="text-[var(--stone)] font-medium">
                                                {{ $availableManufacturers[$integration['provider']]['name'] ?? ucfirst($integration['provider']) }}
                                            </p>
                                            <p class="text-[var(--sand)] text-sm">{{ $integration['provider'] }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    @if($integration['status'] === 'connected')
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-900 text-green-200">
                                            <span class="w-2 h-2 bg-green-400 rounded-full mr-2"></span>
                                            Connected
                                        </span>
                                    @elseif($integration['status'] === 'pending')
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-900 text-yellow-200">
                                            <span class="w-2 h-2 bg-yellow-400 rounded-full mr-2"></span>
                                            Pending
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-900 text-red-200">
                                            <span class="w-2 h-2 bg-red-400 rounded-full mr-2"></span>
                                            Disconnected
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    <div>
                                        <p class="text-[var(--stone)] text-sm">{{ $integration['last_sync_at'] }}</p>
                                        <p class="text-[var(--sand)] text-xs">
                                            @if($integration['last_sync_status'] === 'success')
                                                <span class="text-green-400">Success</span>
                                            @elseif($integration['last_sync_status'] === 'failed')
                                                <span class="text-red-400" @if($integration['last_error']) title="{{ $integration['last_error'] }}" @endif>Failed</span>
                                            @else
                                                <span class="text-[var(--sand)]">Not synced</span>
                                            @endif
                                        </p>
                                        <p class="text-[var(--sand)] text-xs mt-0.5">{{ $integration['machines_count'] }} machine{{ $integration['machines_count'] === 1 ? '' : 's' }} synced</p>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-[var(--sand)] text-sm">
                                    {{ $integration['created_at'] }}
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button 
                                            wire:click="testConnection({{ $integration['id'] }})"
                                            class="px-3 py-2 bg-white/10 hover:bg-white/20 text-[var(--stone)] rounded text-sm transition flex items-center gap-1"
                                            title="Test connection"
                                            wire:loading.attr="disabled"
                                            wire:target="testConnection({{ $integration['id'] }})"
                                        >
                                            <span wire:loading.remove wire:target="testConnection({{ $integration['id'] }})">Test</span>
                                            <span wire:loading wire:target="testConnection({{ $integration['id'] }})" class="flex items-center gap-1">
                                                <svg class="animate-spin h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                                Testing...
                                            </span>
                                        </button>
                                        <button 
                                            wire:click="syncMachines({{ $integration['id'] }})"
                                            class="px-3 py-2 bg-green-900/60 hover:bg-green-800 text-green-200 rounded text-sm transition flex items-center gap-1"
                                            title="Sync machines"
                                            wire:loading.attr="disabled"
                                            wire:target="syncMachines({{ $integration['id'] }})"
                                        >
                                            <span wire:loading.remove wire:target="syncMachines({{ $integration['id'] }})">Sync</span>
                                            <span wire:loading wire:target="syncMachines({{ $integration['id'] }})" class="flex items-center gap-1">
                                                <svg class="animate-spin h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                                Syncing...
                                            </span>
                                        </button>
                                        <button 
                                            wire:click="deleteIntegration({{ $integration['id'] }})"
                                            wire:confirm="Are you sure you want to delete this integration?"
                                            class="px-3 py-2 bg-red-900/60 hover:bg-red-800 text-red-200 rounded text-sm transition"
                                            title="Delete integration"
                                        >
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg p-12 text-center">
            <div class="text-4xl mb-4">📦</div>
            <h3 class="text-xl font-semibold text-[var(--stone)] mb-2">No Integrations Yet</h3>
            <p class="text-[var(--sand)] mb-6">Get started by adding your first equipment manufacturer integration</p>
            <button 
                wire:click="openAddModal"
                class="px-6 py-3 bg-[var(--gold)] hover:bg-[var(--gold-soft)] text-[var(--ink)] rounded-lg font-display font-semibold transition"
            >
                + Add Your First Integration
            </button>
        </div>
    @endif

    <!-- Add Integration Modal -->
    @if($showAddModal)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" style="backdrop-filter: blur(4px);">
            <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg shadow-xl max-w-md w-full mx-4">
                <div class="p-6 border-b border-[var(--line)]">
                    <h3 class="text-xl font-bold text-[var(--stone)]">Add New Integration</h3>
                </div>

                <div class="p-6 space-y-4">
                    @error('general')
                        <div class="p-4 bg-red-900 border border-red-700 rounded text-red-200 text-sm">
                            {{ $message }}
                        </div>
                    @enderror

                    <!-- Provider Selection -->
                    <div>
                        <label class="block text-[var(--stone)] font-medium mb-2">Manufacturer *</label>
                        <select 
                            wire:model="formData.provider"
                            class="w-full px-4 py-2 bg-white/5 border border-[var(--line)] rounded text-[var(--stone)] focus:outline-none focus:border-[var(--gold)]"
                        >
                            <option value="">Select a manufacturer...</option>
                            @foreach($availableManufacturers as $key => $mfr)
                                <option value="{{ $key }}">
                                    {{ $mfr['icon'] }} {{ $mfr['name'] }}@if(($mfr['status'] ?? 'available') === 'coming_soon') (Coming Soon){{ ' ' }}@endif
                                </option>
                            @endforeach
                        </select>
                        @error('provider')
                            <p class="text-red-400 text-sm mt-1">{{ $message }}</p>
                        @enderror
                        @if($formData['provider'])
                            <div class="mt-3 text-xs text-[var(--sand)] bg-[var(--gold)]/10 rounded p-2">
                                <strong>Integration requirements for {{ $availableManufacturers[$formData['provider']]['name'] ?? $formData['provider'] }}:</strong>
                                @switch($formData['provider'])
                                    @case('volvo')
                                    @case('cat')
                                    @case('komatsu')
                                        <div>Requires API Key and Secret from OEM portal.</div>
                                        @break
                                    @case('bell')
                                        <div>Requires an ISO 15143-3 export account from Bell Equipment (username, password, and client secret for the ISO_Export_Service client).</div>
                                        @break
                                    @case('ctrack')
                                        <div>Requires API Key, Secret, and custom endpoint URL.</div>
                                        @break
                                    @case('john-deere')
                                        <div>Requires OAuth Client ID/Secret and endpoint.</div>
                                        @break
                                    @case('liebherr')
                                    @case('hyundai')
                                    @case('doosan')
                                    @case('jcb')
                                    @case('case')
                                    @case('sany')
                                    @case('xcmg')
                                    @case('kobelco')
                                    @case('new-holland')
                                    @case('takeuchi')
                                    @case('kubota')
                                    @case('bobcat')
                                    @case('yanmar')
                                        <div>Requires API Key and Secret from manufacturer.</div>
                                        @break
                                    @case('atlas-copco')
                                    @case('sandvik')
                                    @case('epiroc')
                                        <div>Requires API Key, Secret, and site code.</div>
                                        @break
                                    @default
                                        <div>Standard API credentials required.</div>
                                @endswitch
                            </div>
                        @endif
                    </div>

                    <!-- API Key -->
                    <div>
                        @if($formData['provider'] === 'ctrack')
                            <label class="block text-[var(--stone)] font-medium mb-2 mt-4">API Key *</label>
                            <input type="password" wire:model="formData.credentials.api_key" placeholder="Enter your API key" class="w-full px-4 py-2 bg-white/5 border border-[var(--line)] rounded text-[var(--stone)] placeholder-[var(--sand)]/60 focus:outline-none focus:border-[var(--gold)]" />
                            <label class="block text-[var(--stone)] font-medium mb-2 mt-4">API Secret *</label>
                            <input type="password" wire:model="formData.credentials.api_secret" placeholder="Enter your API secret" class="w-full px-4 py-2 bg-white/5 border border-[var(--line)] rounded text-[var(--stone)] placeholder-[var(--sand)]/60 focus:outline-none focus:border-[var(--gold)]" />
                            <label class="block text-[var(--stone)] font-medium mb-2 mt-4">Endpoint URL *</label>
                            <input type="text" wire:model="formData.credentials.endpoint" placeholder="https://api.ctrack.com/..." class="w-full px-4 py-2 bg-white/5 border border-[var(--line)] rounded text-[var(--stone)] placeholder-[var(--sand)]/60 focus:outline-none focus:border-[var(--gold)]" />
                        @elseif($formData['provider'] === 'john-deere')
                            <label class="block text-[var(--stone)] font-medium mb-2 mt-4">OAuth Client ID *</label>
                            <input type="text" wire:model="formData.credentials.client_id" placeholder="Enter Client ID" class="w-full px-4 py-2 bg-white/5 border border-[var(--line)] rounded text-[var(--stone)] placeholder-[var(--sand)]/60 focus:outline-none focus:border-[var(--gold)]" />
                            <label class="block text-[var(--stone)] font-medium mb-2 mt-4">OAuth Client Secret *</label>
                            <input type="password" wire:model="formData.credentials.client_secret" placeholder="Enter Client Secret" class="w-full px-4 py-2 bg-white/5 border border-[var(--line)] rounded text-[var(--stone)] placeholder-[var(--sand)]/60 focus:outline-none focus:border-[var(--gold)]" />
                            <label class="block text-[var(--stone)] font-medium mb-2 mt-4">Endpoint URL *</label>
                            <input type="text" wire:model="formData.credentials.endpoint" placeholder="https://api.deere.com/..." class="w-full px-4 py-2 bg-white/5 border border-[var(--line)] rounded text-[var(--stone)] placeholder-[var(--sand)]/60 focus:outline-none focus:border-[var(--gold)]" />
                        @elseif($formData['provider'] === 'bell')
                            <label class="block text-[var(--stone)] font-medium mb-2 mt-4">ISO Export Username *</label>
                            <input type="text" wire:model="formData.credentials.username" placeholder="yourcompany-fleetauth@bell.co.za" class="w-full px-4 py-2 bg-white/5 border border-[var(--line)] rounded text-[var(--stone)] placeholder-[var(--sand)]/60 focus:outline-none focus:border-[var(--gold)]" />
                            <label class="block text-[var(--stone)] font-medium mb-2 mt-4">Password *</label>
                            <input type="password" wire:model="formData.credentials.password" placeholder="Enter your Bell ISO export password" class="w-full px-4 py-2 bg-white/5 border border-[var(--line)] rounded text-[var(--stone)] placeholder-[var(--sand)]/60 focus:outline-none focus:border-[var(--gold)]" />
                            <label class="block text-[var(--stone)] font-medium mb-2 mt-4">Client Secret *</label>
                            <input type="password" wire:model="formData.credentials.client_secret" placeholder="Enter your Client Secret" class="w-full px-4 py-2 bg-white/5 border border-[var(--line)] rounded text-[var(--stone)] placeholder-[var(--sand)]/60 focus:outline-none focus:border-[var(--gold)]" />
                            <label class="block text-[var(--stone)] font-medium mb-2 mt-4">Client ID</label>
                            <input type="text" wire:model="formData.credentials.client_id" placeholder="ISO_Export_Service" class="w-full px-4 py-2 bg-white/5 border border-[var(--line)] rounded text-[var(--stone)] placeholder-[var(--sand)]/60 focus:outline-none focus:border-[var(--gold)]" />
                            <p class="text-[var(--sand)] text-xs mt-1">Bell issues this to every ISO 15143-3 export consumer -- leave as-is unless Bell told you otherwise.</p>
                        @elseif(in_array($formData['provider'], ['atlas-copco','sandvik','epiroc']))
                            <label class="block text-[var(--stone)] font-medium mb-2 mt-4">API Key *</label>
                            <input type="password" wire:model="formData.credentials.api_key" placeholder="Enter your API key" class="w-full px-4 py-2 bg-white/5 border border-[var(--line)] rounded text-[var(--stone)] placeholder-[var(--sand)]/60 focus:outline-none focus:border-[var(--gold)]" />
                            <label class="block text-[var(--stone)] font-medium mb-2 mt-4">API Secret *</label>
                            <input type="password" wire:model="formData.credentials.api_secret" placeholder="Enter your API secret" class="w-full px-4 py-2 bg-white/5 border border-[var(--line)] rounded text-[var(--stone)] placeholder-[var(--sand)]/60 focus:outline-none focus:border-[var(--gold)]" />
                            <label class="block text-[var(--stone)] font-medium mb-2 mt-4">Site Code *</label>
                            <input type="text" wire:model="formData.credentials.site_code" placeholder="Enter site code" class="w-full px-4 py-2 bg-white/5 border border-[var(--line)] rounded text-[var(--stone)] placeholder-[var(--sand)]/60 focus:outline-none focus:border-[var(--gold)]" />
                        @else
                            <label class="block text-[var(--stone)] font-medium mb-2 mt-4">API Key *</label>
                            <input type="password" wire:model="formData.credentials.api_key" placeholder="Enter your API key" class="w-full px-4 py-2 bg-white/5 border border-[var(--line)] rounded text-[var(--stone)] placeholder-[var(--sand)]/60 focus:outline-none focus:border-[var(--gold)]" />
                            <label class="block text-[var(--stone)] font-medium mb-2 mt-4">API Secret *</label>
                            <input type="password" wire:model="formData.credentials.api_secret" placeholder="Enter your API secret" class="w-full px-4 py-2 bg-white/5 border border-[var(--line)] rounded text-[var(--stone)] placeholder-[var(--sand)]/60 focus:outline-none focus:border-[var(--gold)]" />
                        @endif
                        <p class="text-[var(--sand)] text-sm mt-2">
                            Tip: Your credentials are encrypted and stored securely. You can test the connection before saving.
                        </p>
                </div>

                <div class="p-6 border-t border-[var(--line)] flex gap-3">
                    <button 
                        wire:click="closeAddModal"
                        class="flex-1 px-4 py-2 bg-white/5 hover:bg-white/10 border border-[var(--line)] text-[var(--stone)] rounded font-medium transition"
                    >
                        Cancel
                    </button>
                    <button 
                        wire:click="createIntegration"
                        class="flex-1 px-4 py-2 bg-[var(--gold)] hover:bg-[var(--gold-soft)] text-[var(--ink)] rounded font-display font-semibold transition"
                    >
                        Create Integration
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Test Connection Modal -->
    @if($showTestModal && $testResult)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" style="backdrop-filter: blur(4px);">
            <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg shadow-xl max-w-md w-full mx-4">
                <div class="p-6 text-center">
                    @if($testResult['success'])
                        <div class="text-5xl mb-4">✅</div>
                        <h3 class="text-xl font-bold text-green-400 mb-2">Connection Successful</h3>
                        <p class="text-[var(--sand)]">{{ $testResult['message'] }}</p>
                    @else
                        <div class="text-5xl mb-4">❌</div>
                        <h3 class="text-xl font-bold text-red-400 mb-2">Connection Failed</h3>
                        <p class="text-[var(--sand)]">{{ $testResult['message'] }}</p>
                    @endif
                </div>
                <div class="p-6 border-t border-[var(--line)]">
                    <button 
                        wire:click="$set('showTestModal', false)"
                        class="w-full px-4 py-2 bg-[var(--gold)] hover:bg-[var(--gold-soft)] text-[var(--ink)] rounded font-display font-semibold transition"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>

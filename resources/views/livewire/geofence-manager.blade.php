<div>
    <!-- Header Section -->
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-[var(--stone)]">Geofence Management</h1>
        @if($mineAreas->count() > 0)
            <button wire:click="openCreateModal" class="px-4 py-2 bg-[var(--gold)] hover:bg-[var(--gold-soft)] text-[var(--ink)] rounded-lg transition-colors flex items-center gap-2 font-display font-semibold">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                <span>Create Geofence</span>
            </button>
        @else
            <div class="flex items-center gap-3">
                <div class="bg-red-600/20 border border-red-600 rounded-lg px-4 py-2 text-red-300 text-sm flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                    <span>Create a mine area first</span>
                </div>
                <a href="{{ route('mine-areas') }}" class="px-4 py-2 bg-white/5 hover:bg-white/10 border border-[var(--line)] text-[var(--stone)] rounded-lg transition-colors flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    <span>Create Mine Area</span>
                </a>
            </div>
        @endif
    </div>

    <!-- AI-Powered Mine Area Detection -->
    @if($aiRecommendations->count() > 0 || $aiInsights->count() > 0)
    <div class="mb-6">
        <div class="flex items-center gap-2 mb-4">
            <svg class="w-6 h-6 text-[var(--gold)]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
            </svg>
            <h2 class="text-2xl font-display font-semibold text-[var(--stone)]">AI Mine Area Detection</h2>
            <span class="badge badge-primary">AI-Powered</span>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- AI Area Detection Recommendations -->
            @if($aiRecommendations->count() > 0)
            <div class="card bg-gradient-to-br from-[var(--umber)] to-[var(--ink)] text-[var(--stone)] border border-[var(--line)]">
                <div class="card-body">
                    <h3 class="text-xl font-bold mb-4 flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path>
                        </svg>
                        Auto-Detected Areas
                    </h3>
                    <div class="space-y-3">
                        @foreach($aiRecommendations as $recommendation)
                        <div class="bg-white/10 backdrop-blur-sm rounded-lg p-4 border border-white/20">
                            <div class="flex items-start justify-between mb-2">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="font-semibold">{{ $recommendation['title'] }}</span>
                                    </div>
                                </div>
                                <span class="badge ml-2
                                    @if($recommendation['priority'] === 'critical') badge-error
                                    @elseif($recommendation['priority'] === 'high') badge-warning
                                    @else badge-info
                                    @endif
                                ">{{ ucfirst($recommendation['priority']) }}</span>
                            </div>
                            <p class="text-sm text-[var(--sand)] mb-3">{{ $recommendation['description'] }}</p>
                            
                            @if(isset($recommendation['data']['center_latitude']) && isset($recommendation['data']['center_longitude']))
                            <div class="bg-[var(--gold)]/10 border border-[var(--gold)]/20 rounded p-2 mb-2">
                                <div class="text-sm space-y-1">
                                    <div class="flex items-center gap-2">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        </svg>
                                        <span><strong>Coordinates:</strong> {{ $recommendation['data']['center_latitude'] }}, {{ $recommendation['data']['center_longitude'] }}</span>
                                    </div>
                                    @if(isset($recommendation['data']['activity_points']))
                                        <div class="flex items-center gap-2">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                                            </svg>
                                            <span><strong>Activity Points:</strong> {{ $recommendation['data']['activity_points'] }} GPS records</span>
                                        </div>
                                    @endif
                                    @if(isset($recommendation['data']['unique_machines']))
                                        <div class="flex items-center gap-2">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                            </svg>
                                            <span><strong>Machines:</strong> {{ $recommendation['data']['unique_machines'] }} different machines</span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                            @endif

                            @if(isset($recommendation['estimated_savings']) && $recommendation['estimated_savings'] > 0)
                            <div class="flex items-center gap-2 text-green-300 text-sm mb-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <span>Saves: R{{ number_format($recommendation['estimated_savings'], 2) }} (vs manual surveying)</span>
                            </div>
                            @endif

                            @if(isset($recommendation['impact_analysis']))
                            <details class="collapse collapse-arrow bg-white/5 mt-2">
                                <summary class="collapse-title text-sm font-medium py-2 min-h-0">Impact Analysis</summary>
                                <div class="collapse-content px-2 pb-2">
                                    <ul class="text-xs space-y-1 text-[var(--sand)]">
                                        @if(isset($recommendation['impact_analysis']['benefit']))
                                            <li><strong>Benefit:</strong> {{ $recommendation['impact_analysis']['benefit'] }}</li>
                                        @endif
                                        @if(isset($recommendation['impact_analysis']['recommended_action']))
                                            <li><strong>Action:</strong> {{ $recommendation['impact_analysis']['recommended_action'] }}</li>
                                        @endif
                                        @if(isset($recommendation['impact_analysis']['estimated_area_size']))
                                            <li><strong>Est. Size:</strong> {{ $recommendation['impact_analysis']['estimated_area_size'] }}</li>
                                        @endif
                                        @if(isset($recommendation['impact_analysis']['issue']))
                                            <li><strong>Issue:</strong> {{ $recommendation['impact_analysis']['issue'] }}</li>
                                        @endif
                                    </ul>
                                </div>
                            </details>
                            @endif

                            <div class="flex items-center justify-between mt-3 pt-3 border-t border-white/10">
                                <span class="text-xs text-[var(--sand)]">AI Confidence: {{ number_format($recommendation['confidence_score'] * 100, 0) }}%</span>
                                @if(isset($recommendation['data']['center_latitude']))
                                    <button wire:click="openCreateModal" class="btn btn-xs btn-primary gap-1">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                        </svg>
                                        Create Area
                                    </button>
                                @endif
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif

            <!-- AI Coverage Insights -->
            @if($aiInsights->count() > 0)
            <div class="card bg-gradient-to-br from-[var(--umber)] to-[var(--ink)] text-[var(--stone)] border border-[var(--line)]">
                <div class="card-body">
                    <h3 class="text-xl font-bold mb-4 flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                        </svg>
                        Coverage Insights
                    </h3>
                    <div class="space-y-3">
                        @foreach($aiInsights as $insight)
                        <div class="bg-white/10 backdrop-blur-sm rounded-lg p-4 border border-white/20">
                            <div class="flex items-start justify-between mb-2">
                                <span class="font-semibold">{{ $insight['title'] }}</span>
                                <span class="badge
                                    @if($insight['severity'] === 'critical') badge-error
                                    @elseif($insight['severity'] === 'warning') badge-warning
                                    @elseif($insight['severity'] === 'success') badge-success
                                    @else badge-info
                                    @endif
                                ">{{ ucfirst($insight['type']) }}</span>
                            </div>
                            <p class="text-sm text-[var(--sand)]">{{ $insight['description'] }}</p>
                            
                            @if(isset($insight['data']['coverage_percentage']))
                            <div class="mt-2">
                                <div class="flex items-center justify-between text-xs mb-1">
                                    <span>Geofence Coverage</span>
                                    <span class="font-bold">{{ number_format($insight['data']['coverage_percentage'], 0) }}%</span>
                                </div>
                                <progress class="progress progress-success w-full" value="{{ $insight['data']['coverage_percentage'] }}" max="100"></progress>
                            </div>
                            @endif

                            @if(isset($insight['data']['unmapped_areas']))
                            <div class="mt-2 text-xs text-[var(--sand)]">
                                <strong>Unmapped Areas:</strong> {{ $insight['data']['unmapped_areas'] }}
                            </div>
                            @endif

                            @if(isset($insight['data']['total_areas']))
                            <div class="mt-2 text-xs text-[var(--sand)]">
                                <strong>Total Defined Areas:</strong> {{ $insight['data']['total_areas'] }}
                            </div>
                            @endif
                        </div>
                        @endforeach
                    </div>

                    <!-- AI Info Footer -->
                    <div class="mt-4 p-3 bg-[var(--gold)]/10 border border-[var(--gold)]/20 rounded-lg">
                        <p class="text-xs text-[var(--sand)]">
                            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <strong>AI Analysis:</strong> Detects unmapped areas by analyzing GPS patterns from machine movements over the last 7 days.
                        </p>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
    @endif

    <!-- Filters Section -->
    <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg p-4 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Search -->
            <div>
                <label class="block text-sm font-medium text-[var(--sand)] mb-2">Search Geofences</label>
                <input 
                    type="text" 
                    wire:model.live="search" 
                    placeholder="Name or description..."
                    class="w-full px-4 py-2 bg-white/5 border border-[var(--line)] rounded-lg text-[var(--stone)] placeholder-[var(--sand)]/60 focus:outline-none focus:border-[var(--gold)]"
                />
            </div>

            <!-- Sort By -->
            <div>
                <label class="block text-sm font-medium text-[var(--sand)] mb-2">Sort By</label>
                <select 
                    wire:model.live="sortBy"
                    class="w-full px-4 py-2 bg-white/5 border border-[var(--line)] rounded-lg text-[var(--stone)] focus:outline-none focus:border-[var(--gold)]"
                >
                    <option value="name">Name</option>
                    <option value="created_at">Date Created</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Geofences Table -->
    <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg overflow-hidden">
        @if ($geofences->count() > 0)
            <table class="w-full">
                <thead class="bg-white/5 border-b border-[var(--line)]">
                    <tr>
                        <th class="px-6 py-4 text-left text-sm font-semibold text-[var(--sand)]">
                            <button wire:click="toggleSort('name')" class="hover:text-[var(--stone)] flex items-center gap-1">
                                Name
                                @if ($sortBy === 'name')
                                    @if ($sortDirection === 'asc')
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>
                                    @else
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd"></path></svg>
                                    @endif
                                @endif
                            </button>
                        </th>
                        <th class="px-6 py-4 text-left text-sm font-semibold text-[var(--sand)]">Mine Area</th>
                        <th class="px-6 py-4 text-left text-sm font-semibold text-[var(--sand)]">Description</th>
                        <th class="px-6 py-4 text-left text-sm font-semibold text-[var(--sand)]">Entries</th>
                        <th class="px-6 py-4 text-left text-sm font-semibold text-[var(--sand)]">Machines Tracked</th>
                        <th class="px-6 py-4 text-left text-sm font-semibold text-[var(--sand)]">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--line)]">
                    @foreach ($geofences as $geofence)
                        <tr class="hover:bg-white/5 transition-colors">
                            <td class="px-6 py-4">
                                <a href="{{ route('geofences.show', $geofence) }}" class="text-[var(--gold)] hover:text-[var(--gold-soft)] font-medium">
                                    {{ $geofence->name }}
                                </a>
                            </td>
                            <td class="px-6 py-4 text-[var(--sand)] text-sm">
                                @if($geofence->mineArea)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-500/20 text-purple-400">
                                        {{ $geofence->mineArea->name }}
                                    </span>
                                @else
                                    <span class="text-[var(--sand)]/60 text-xs">Not assigned</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-[var(--sand)] text-sm">{{ $geofence->description ? Str::limit($geofence->description, 50) : 'N/A' }}</td>
                            <td class="px-6 py-4 text-[var(--sand)]">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-500/20 text-blue-400">
                                    {{ $geofenceStats[$geofence->id]['entries'] ?? 0 }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-[var(--sand)]">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-500/20 text-green-400">
                                    {{ $geofenceStats[$geofence->id]['machines'] ?? 0 }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <button wire:click="editGeofence({{ $geofence->id }})" class="px-3 py-1 bg-white/10 hover:bg-white/20 text-[var(--stone)] text-sm rounded transition-colors">
                                        Edit
                                    </button>
                                    <button wire:click="deleteGeofence({{ $geofence->id }})" wire:confirm="Are you sure?" class="px-3 py-1 bg-red-600 hover:bg-red-700 text-[var(--stone)] text-sm rounded transition-colors flex items-center gap-1" wire:loading.attr="disabled" wire:target="deleteGeofence({{ $geofence->id }})">
                                        <span wire:loading.remove wire:target="deleteGeofence({{ $geofence->id }})">Delete</span>
                                        <span wire:loading wire:target="deleteGeofence({{ $geofence->id }})" class="flex items-center gap-1">
                                            <svg class="animate-spin h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                            Deleting
                                        </span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <!-- Pagination -->
            <div class="bg-white/5 px-6 py-4 border-t border-[var(--line)]">
                {{ $geofences->links('pagination::tailwind') }}
            </div>
        @else
            <div class="p-8 text-center">
                <svg class="mx-auto h-12 w-12 text-[var(--sand)]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6 3m-6-3v-13m6 3l5.553-2.776A1 1 0 0121 5.618v10.764a1 1 0 01-1.447.894L15 20m0-13v13"></path>
                </svg>
                <p class="text-[var(--sand)] text-lg mt-4">No geofences found</p>
                <p class="text-[var(--sand)] text-sm mt-1">Create a new geofence to get started</p>
            </div>
        @endif
    </div>

    <!-- Create/Edit Modal -->
    @if ($showCreateModal)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" wire:click="closeModal">
            <div class="bg-[var(--ink-soft)] rounded-lg border border-[var(--line)] p-6 w-full max-w-2xl" @click.stop>
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-2xl font-bold text-[var(--stone)]">
                        {{ $editingGeofenceId ? 'Edit Geofence' : 'Create New Geofence' }}
                    </h2>
                    <button wire:click="closeModal" class="text-[var(--sand)] hover:text-[var(--stone)]">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <form wire:submit="saveGeofence" class="space-y-4">
                    <!-- Mine Area Selection -->
                    <div>
                        <label class="block text-sm font-medium text-[var(--sand)] mb-2">Mine Area *</label>
                        <select 
                            wire:model="mineAreaId"
                            class="w-full px-4 py-2 bg-white/5 border border-[var(--line)] rounded-lg text-[var(--stone)] focus:outline-none focus:border-[var(--gold)]"
                            required
                        >
                            <option value="">Select a mine area...</option>
                            @foreach($mineAreas as $area)
                                <option value="{{ $area->id }}">{{ $area->name }} ({{ ucfirst($area->type) }})</option>
                            @endforeach
                        </select>
                        @error('mineAreaId') <span class="text-red-400 text-xs mt-1">{{ $message }}</span> @enderror
                        <p class="text-xs text-[var(--sand)] mt-1">Geofences must be associated with a mine area for proper organization</p>
                    </div>

                    <!-- Name -->
                    <div>
                        <label class="block text-sm font-medium text-[var(--sand)] mb-2">Geofence Name *</label>
                        <input 
                            type="text" 
                            wire:model="name" 
                            placeholder="e.g., North Pit Zone"
                            class="w-full px-4 py-2 bg-white/5 border border-[var(--line)] rounded-lg text-[var(--stone)] placeholder-[var(--sand)]/60 focus:outline-none focus:border-[var(--gold)]"
                        />
                        @error('name') <span class="text-red-400 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>

                    <!-- Description -->
                    <div>
                        <label class="block text-sm font-medium text-[var(--sand)] mb-2">Description</label>
                        <textarea 
                            wire:model="description" 
                            placeholder="Optional description..."
                            rows="3"
                            class="w-full px-4 py-2 bg-white/5 border border-[var(--line)] rounded-lg text-[var(--stone)] placeholder-[var(--sand)]/60 focus:outline-none focus:border-[var(--gold)]"
                        ></textarea>
                        @error('description') <span class="text-red-400 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>

                    <!-- Type -->
                    <div>
                        <label class="block text-sm font-medium text-[var(--sand)] mb-2">Geofence Type *</label>
                        <select 
                            wire:model="type"
                            class="w-full px-4 py-2 bg-white/5 border border-[var(--line)] rounded-lg text-[var(--stone)] focus:outline-none focus:border-[var(--gold)]"
                        >
                            <option value="pit">Pit</option>
                            <option value="stockpile">Stockpile</option>
                            <option value="dump">Dump</option>
                            <option value="facility">Facility</option>
                        </select>
                        @error('type') <span class="text-red-400 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>

                    <!-- Center Coordinates -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-[var(--sand)] mb-2">Center Latitude *</label>
                            <input 
                                type="number" 
                                wire:model="centerLatitude" 
                                step="0.0001"
                                placeholder="e.g., -25.5095"
                                class="w-full px-4 py-2 bg-white/5 border border-[var(--line)] rounded-lg text-[var(--stone)] placeholder-[var(--sand)]/60 focus:outline-none focus:border-[var(--gold)]"
                            />
                            @error('centerLatitude') <span class="text-red-400 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-[var(--sand)] mb-2">Center Longitude *</label>
                            <input 
                                type="number" 
                                wire:model="centerLongitude" 
                                step="0.0001"
                                placeholder="e.g., 131.0044"
                                class="w-full px-4 py-2 bg-white/5 border border-[var(--line)] rounded-lg text-[var(--stone)] placeholder-[var(--sand)]/60 focus:outline-none focus:border-[var(--gold)]"
                            />
                            @error('centerLongitude') <span class="text-red-400 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="bg-[var(--gold)]/10 border border-[var(--gold)]/25 rounded-lg p-3 text-[var(--sand)] text-sm">
                        <p>💡 <strong>Tip:</strong> You can define the exact boundary coordinates by editing in map view after creation.</p>
                    </div>

                    <!-- Form Actions -->
                    <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-[var(--line)]">
                        <button 
                            type="button" 
                            wire:click="closeModal"
                            class="px-4 py-2 bg-white/5 hover:bg-white/10 border border-[var(--line)] text-[var(--stone)] rounded-lg transition-colors"
                        >
                            Cancel
                        </button>
                        <button 
                            type="submit"
                            class="px-4 py-2 bg-[var(--gold)] hover:bg-[var(--gold-soft)] text-[var(--ink)] rounded-lg transition-colors"
                        >
                            {{ $editingGeofenceId ? 'Update Geofence' : 'Create Geofence' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>

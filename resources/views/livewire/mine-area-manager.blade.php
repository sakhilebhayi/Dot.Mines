<div>
<div class="h-screen flex flex-col bg-gray-900">
    <link rel="stylesheet" href="/vendor/leaflet.css" />
    <link rel="stylesheet" href="/vendor/leaflet-draw/leaflet.draw.css" />

    <script>
    (function injectMineAreaStyles() {
        if (document.getElementById('mine-area-styles')) return;
        var s = document.createElement('style');
        s.id = 'mine-area-styles';
        s.textContent = [
            '#mine-area-map,#mine-area-draw-map{background:#1f2937;min-height:400px;height:100%;width:100%}',
            '.leaflet-container{background:#1f2937!important}',
            '.mine-area-search-input{width:100%;padding:0.5rem 1rem;border:1px solid #4b5563;border-radius:0.5rem;background-color:#374151;color:#111827;outline:none}',
            '.dark .mine-area-search-input{color:#fff}',
            '.mine-area-search-input::placeholder{color:#9ca3af;opacity:1}',
            '.mine-area-search-input:focus{border-color:#3b82f6;box-shadow:0 0 0 2px rgba(59,130,246,0.4)}',
        ].join('');
        document.head.appendChild(s);
    })();
    </script>

    <!-- Header -->
    <div class="bg-gray-800 border-b border-gray-700 p-6">
            <div class="w-full px-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h1 class="text-3xl font-bold text-white">Mine Areas</h1>
                    <p class="mt-2 text-gray-400">Manage and organize your mining operation areas</p>
                </div>
                <div class="flex gap-2">
                    @if($viewMode === 'map' && $isDrawing)
                        <button type="button"
                            wire:click="switchToListMode" 
                            class="inline-flex items-center gap-2 px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                            </svg>
                            View List
                        </button>
                    @elseif($viewMode === 'list')
                        <button type="button"
                            wire:click="openCreateMapModal" 
                            class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6 3m-6-3v-13m6 3l5.553-2.776A1 1 0 0121 5.618v10.764a1 1 0 01-1.447.894L15 20m0-13v13"></path>
                            </svg>
                            Draw on Map
                        </button>
                        <button type="button"
                            wire:click="openCreateModal" 
                            class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                            Add Manually
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Content -->
    <div class="flex-1 flex overflow-hidden">
        @if($viewMode === 'list')
            <!-- List View -->
            <div class="w-full flex flex-col overflow-auto">
                <div class="bg-gray-800 w-full px-6 py-6 rounded-lg shadow space-y-4">
                    <!-- Stats Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 p-6 border-b border-gray-700">
                        <div class="bg-gray-800 rounded-lg p-4 shadow border border-gray-700">
                            <p class="text-gray-400 text-sm">Total Areas</p>
                            <p class="text-3xl font-bold text-white">{{ $stats['total_areas'] }}</p>
                        </div>
                        <div class="bg-gray-800 rounded-lg p-4 shadow border border-gray-700">
                            <p class="text-gray-400 text-sm">Active Areas</p>
                            <p class="text-3xl font-bold text-blue-400">{{ $stats['active_areas'] }}</p>
                        </div>
                        <div class="bg-gray-800 rounded-lg p-4 shadow border border-gray-700">
                            <p class="text-gray-400 text-sm">Total Area Size</p>
                            <p class="text-3xl font-bold text-white">{{ number_format($stats['total_area_hectares'], 1) }} ha</p>
                        </div>
                        <div class="bg-gray-800 rounded-lg p-4 shadow border border-gray-700">
                            <p class="text-gray-400 text-sm">With Managers</p>
                            <p class="text-3xl font-bold text-white">{{ $stats['areas_with_manager'] }}</p>
                        </div>
                    </div>

                    <!-- Filters -->
                    <div class="p-6 border-b border-gray-700">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <input 
                                    type="text"
                                    wire:model.live="search"
                                    placeholder="Search mine areas..."
                                    class="mine-area-search-input text-gray-900"
                                >
                            </div>
                            <div>
                                <select 
                                    wire:model.live="statusFilter"
                                    class="mine-area-search-input animated input-animate text-gray-900"
                                >
                                    <option value="">All Statuses</option>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                    <option value="planning">Planning</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Table -->
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-700 border-b border-gray-600">
                                <tr>
                                    <th class="px-6 py-3 text-left">
                                        <button type="button" wire:click="toggleSort('name')" class="flex items-center gap-2 font-semibold text-white">
                                            Name
                                            @if($sortBy === 'name')
                                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                    @if($sortDirection === 'asc')
                                                        <path fill-rule="evenodd" d="M3 10a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z"></path>
                                                    @else
                                                        <path fill-rule="evenodd" d="M5.293 7.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 11-1.414 1.414L11 5.414V15a1 1 0 11-2 0V5.414L6.707 7.707a1 1 0 01-1.414 0z"></path>
                                                    @endif
                                                </svg>
                                            @endif
                                        </button>
                                    </th>
                                    <th class="px-6 py-3 text-left font-semibold text-white">Location</th>
                                    <th class="px-6 py-3 text-center font-semibold text-white">Machines</th>
                                    <th class="px-6 py-3 text-center font-semibold text-white">Geofences</th>
                                    <th class="px-6 py-3 text-center font-semibold text-white">Alerts</th>
                                    <th class="px-6 py-3 text-center font-semibold text-white">Plans</th>
                                    <th class="px-6 py-3 text-left">
                                        <button type="button" wire:click="toggleSort('status')" class="flex items-center gap-2 font-semibold text-white">
                                            Status
                                            @if($sortBy === 'status')
                                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                    @if($sortDirection === 'asc')
                                                        <path fill-rule="evenodd" d="M3 10a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z"></path>
                                                    @else
                                                        <path fill-rule="evenodd" d="M5.293 7.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 11-1.414 1.414L11 5.414V15a1 1 0 11-2 0V5.414L6.707 7.707a1 1 0 01-1.414 0z"></path>
                                                    @endif
                                                </svg>
                                            @endif
                                        </button>
                                    </th>
                                    <th class="px-6 py-3 text-right font-semibold text-white">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-700">
                                @forelse($mineAreas as $area)
                                    <tr class="hover:bg-gray-700 transition-colors cursor-pointer" onclick="window.location='{{ route('mine-areas.show', $area->id) }}'">
                                        <td class="px-6 py-4">
                                            <div>
                                                <p class="font-semibold text-white">{{ $area->name }}</p>
                                                <p class="text-sm text-gray-400">{{ Str::limit($area->description, 50) }}</p>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-gray-300">
                                            {{ $area->location ?? '-' }}
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-blue-900 text-blue-200">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                                {{ $area->machines_count ?? 0 }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-purple-900 text-purple-200">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path></svg>
                                                {{ $area->geofences_count ?? 0 }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            @if(($area->alerts_count ?? 0) > 0)
                                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-red-900 text-red-200 animate-pulse">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12c0 4.97-4.03 9-9 9s-9-4.03-9-9 4.03-9 9-9 9 4.03 9 9z"></path></svg>
                                                    {{ $area->alerts_count }}
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-gray-700 text-gray-400">0</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-amber-900 text-amber-200">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                                {{ $area->mine_plan_uploads_count ?? 0 }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                                                @if($area->status === 'active')
                                                    bg-green-900 text-green-200
                                                @elseif($area->status === 'inactive')
                                                    bg-red-900 text-red-200
                                                @else
                                                    bg-yellow-900 text-yellow-200
                                                @endif
                                            ">
                                                {{ ucfirst($area->status) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <div class="flex items-center justify-end gap-2" onclick="event.stopPropagation()">
                                                <a 
                                                    href="{{ route('mine-areas.show', $area->id) }}"
                                                    class="inline-flex items-center px-3 py-1 text-sm bg-amber-900 text-amber-200 rounded hover:bg-amber-800 transition-colors"
                                                >
                                                    View
                                                </a>
                                                <button type="button"
                                                    wire:click="openEditModal({{ $area->id }})"
                                                    class="inline-flex items-center px-3 py-1 text-sm bg-blue-900 text-blue-200 rounded hover:bg-blue-800 transition-colors"
                                                >
                                                    Edit
                                                </button>
                                                <button type="button"
                                                    wire:click="deleteMineArea({{ $area->id }})"
                                                    wire:confirm="Are you sure you want to delete this mine area?"
                                                    class="inline-flex items-center px-3 py-1 text-sm bg-red-900 text-red-200 rounded hover:bg-red-800 transition-colors"
                                                >
                                                    Delete
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="px-6 py-8 text-center text-gray-400">
                                            <svg class="w-12 h-12 mx-auto mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                                            </svg>
                                            <p>No mine areas found. Create one to get started!</p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="p-6 border-t border-gray-700">
                        {{ $mineAreas->links() }}
                    </div>
                </div>
            </div>

        @elseif($viewMode === 'map' && !$isDrawing)
            <!-- Map View (Browse Mode) -->
            <div class="w-full flex">
                <div class="flex-1 relative">
                    <div id="mine-area-map" wire:ignore></div>
                </div>
            </div>

        @else
            <!-- Map View (Drawing Mode) -->
            <div class="w-full flex">
                <!-- Left Sidebar - Form -->
                <div class="w-96 bg-gray-800 border-r border-gray-700 overflow-y-auto p-6 space-y-4">
                    <div>
                        <h2 class="text-xl font-bold text-white mb-2">Create Mine Area</h2>
                        <p class="text-gray-400 text-sm">Draw a boundary on the map, then fill in the details</p>
                    </div>

                    @if($boundaryCoordinates)
                        <div class="bg-green-600/20 border border-green-600 rounded-lg p-3">
                            <p class="text-green-300 text-sm">✓ Boundary drawn on map</p>
                        </div>
                    @else
                        <div class="bg-yellow-600/20 border border-yellow-600 rounded-lg p-3">
                            <p class="text-yellow-300 text-sm">⚠ Draw a boundary on the map to get started</p>
                        </div>
                    @endif

                    <form wire:submit.prevent="saveMineAreaWithBoundary" class="space-y-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">Name *</label>
                            <input 
                                type="text"
                                wire:model="name"
                                placeholder="Mine area name"
                                class="w-full px-3 py-2 border border-gray-600 rounded-lg bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 outline-none"
                            >
                            @error('name') <span class="text-red-400 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">Description</label>
                            <textarea 
                                wire:model="description"
                                placeholder="Area description"
                                rows="2"
                                class="w-full px-3 py-2 border border-gray-600 rounded-lg bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 outline-none"
                            ></textarea>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">Location</label>
                            <input 
                                type="text"
                                wire:model="location"
                                placeholder="Location details"
                                class="w-full px-3 py-2 border border-gray-600 rounded-lg bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 outline-none"
                            >
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">Status *</label>
                            <select 
                                wire:model="status"
                                class="w-full px-3 py-2 border border-gray-600 rounded-lg bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 outline-none"
                            >
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="planning">Planning</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">Manager Name</label>
                            <input 
                                type="text"
                                wire:model="manager_name"
                                placeholder="Manager name"
                                class="w-full px-3 py-2 border border-gray-600 rounded-lg bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 outline-none"
                            >
                        </div>

                        <div class="flex gap-2 pt-4 border-t border-gray-700">
                            <button 
                                type="button"
                                wire:click="closeMapModal"
                                class="flex-1 px-3 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition-colors text-sm"
                            >
                                Cancel
                            </button>
                            <button 
                                type="submit"
                                class="flex-1 px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors text-sm font-medium"
                            >
                                Save Area
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Right: Map -->
                <div class="flex-1 relative">
                    <div id="mine-area-draw-map" wire:ignore style="min-height:400px; height:520px; width:100%;"></div>
                </div>
            </div>
        @endif
    </div>

    <!-- Create/Edit Modal -->
    @if($showCreateModal || $showEditModal)
        <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
            <div class="bg-gray-800 rounded-lg shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto border border-gray-700">
                <div class="sticky top-0 bg-gray-700 border-b border-gray-600 p-6 flex items-center justify-between">
                    <h2 class="text-xl font-bold text-white">
                        {{ $editingMineAreaId ? 'Edit Mine Area' : 'Create New Mine Area' }}
                    </h2>
                    <button type="button"
                        wire:click="@if($editingMineAreaId) closeEditModal @else closeCreateModal @endif"
                        class="text-gray-400 hover:text-gray-300"
                    >
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <form wire:submit="saveMineArea" class="p-6 space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">
                                Name <span class="text-red-500">*</span>
                            </label>
                            <input 
                                type="text"
                                wire:model="name"
                                placeholder="Enter mine area name"
                                class="w-full px-3 py-2 border border-gray-600 rounded-lg bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 outline-none"
                            >
                            @error('name') <span class="text-red-400 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">
                                Status <span class="text-red-500">*</span>
                            </label>
                            <select 
                                wire:model="status"
                                class="w-full px-3 py-2 border border-gray-600 rounded-lg bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 outline-none"
                            >
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="planning">Planning</option>
                            </select>
                            @error('status') <span class="text-red-400 text-sm">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">Description</label>
                        <textarea 
                            wire:model="description"
                            placeholder="Describe the mine area"
                            rows="3"
                            class="w-full px-3 py-2 border border-gray-600 rounded-lg bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 outline-none"
                        ></textarea>
                        @error('description') <span class="text-red-400 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">Location</label>
                            <input 
                                type="text"
                                wire:model="location"
                                placeholder="e.g., North Pit, South Zone"
                                class="w-full px-3 py-2 border border-gray-600 rounded-lg bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 outline-none"
                            >
                            @error('location') <span class="text-red-400 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">Area Size (hectares)</label>
                            <input 
                                type="number"
                                step="0.01"
                                wire:model.live="area_size_hectares"
                                placeholder="0.00"
                                class="w-full px-3 py-2 border border-gray-600 rounded-lg bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 outline-none"
                            >
                            @error('area_size_hectares') <span class="text-red-400 text-sm">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">Manager Name</label>
                            <input 
                                type="text"
                                wire:model="manager_name"
                                placeholder="Mining operations manager"
                                class="w-full px-3 py-2 border border-gray-600 rounded-lg bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 outline-none"
                            >
                            @error('manager_name') <span class="text-red-400 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">Manager Contact</label>
                            <input 
                                type="text"
                                wire:model="manager_contact"
                                placeholder="Phone or email"
                                class="w-full px-3 py-2 border border-gray-600 rounded-lg bg-gray-700 text-white focus:ring-2 focus:ring-blue-500 outline-none"
                            >
                            @error('manager_contact') <span class="text-red-400 text-sm">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-600">
                        <button 
                            type="button"
                            wire:click="@if($editingMineAreaId) closeEditModal @else closeCreateModal @endif"
                            class="px-4 py-2 text-gray-300 bg-gray-700 rounded-lg hover:bg-gray-600 transition-colors"
                        >
                            Cancel
                        </button>
                        <button 
                            type="submit"
                            class="px-4 py-2 text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors"
                        >
                            {{ $editingMineAreaId ? 'Update' : 'Create' }} Mine Area
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif


    <!-- Map Loading Indicator -->
    <div id="map-loading" class="absolute inset-0 flex items-center justify-center bg-gray-800 z-[999]" wire:ignore style="display: none;">
        <div class="text-center">
            <svg class="animate-spin h-12 w-12 text-amber-500 mx-auto mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <p class="text-gray-300 text-sm">Loading map...</p>
        </div>
    </div>

    <!-- Map Hint (temporary visual hint) -->
    <div id="map-hint" class="pointer-events-none fixed top-6 right-6 z-[1000] bg-yellow-600 text-black text-sm px-3 py-2 rounded shadow-lg" style="display: none;">
        <span id="map-hint-text">Map detected but failed to initialize.</span>
    </div>

    <!-- Leaflet and Drawing Tools -->
    <script src="/vendor/leaflet.js"></script>
    <script src="/vendor/leaflet-draw/leaflet.draw.umd.js"></script>

    <script>
        let mineAreaMap = null;
        let drawnItems = null;
        let geofenceLayerGroup;
        let initRetryCount = 0;
        const MAX_INIT_RETRIES = 50;
        // These server-side flags are initial state only; determine active container dynamically
        let isMapDrawMode = @json($isDrawing);
        let isMapViewMode = @json($viewMode === 'map');
        let geofences = [];
        try {
            geofences = @json($geofences ?? []);
        } catch(e) {
            geofences = [];
        }

        function renderGeofences() {
            if (!geofenceLayerGroup) return;
            geofenceLayerGroup.clearLayers();
            geofences.forEach(geofence => {
                try {
                    let coords;
                    if (typeof geofence.coordinates === 'string') {
                        coords = JSON.parse(geofence.coordinates);
                    } else {
                        coords = geofence.coordinates;
                    }
                    if (coords && coords.length > 0) {
                        const latlngs = coords.map(c => [c.lat, c.lng]);
                        const color = geofence.geofence_type === 'restricted' ? '#ef4444' : geofence.geofence_type === 'safe' ? '#22c55e' : '#f59e0b';
                        const polygon = L.polygon(latlngs, {
                            color: color,
                            fillColor: color,
                            fillOpacity: 0.2,
                            weight: 2
                        }).addTo(geofenceLayerGroup);
                        polygon.bindPopup(`<strong>${geofence.name}</strong><br>${geofence.geofence_type}`);
                    }
                } catch (e) {
                    console.error('Error rendering geofence:', geofence.name, e);
                }
            });
        }

        function getActiveMapContainerId() {
            const drawEl = document.getElementById('mine-area-draw-map');
            const browseEl = document.getElementById('mine-area-map');
            function isVisible(el) {
                if (!el) return false;
                const rect = el.getBoundingClientRect();
                return rect.width > 0 && rect.height > 0 && window.getComputedStyle(el).display !== 'none' && el.offsetParent !== null;
            }

            if (isVisible(drawEl)) return 'mine-area-draw-map';
            if (isVisible(browseEl)) return 'mine-area-map';
            // fallback: prefer draw if present
            if (drawEl) return 'mine-area-draw-map';
            return 'mine-area-map';
        }

        function initializeMineAreaMap() {
            const targetId = getActiveMapContainerId();
            const mapContainer = document.getElementById(targetId);
            const loadingEl = document.getElementById('map-loading');
            if (!mapContainer) return;
            // If an existing map was created on a different container, remove it so we can recreate on the current container
            if (mineAreaMap && mineAreaMap._container && mineAreaMap._container.id !== targetId) {
                try {
                    mineAreaMap.remove();
                } catch (e) {
                    console.warn('Error removing previous map instance', e);
                }
                mineAreaMap = null;
                drawnItems = null;
                geofenceLayerGroup = null;
            }
            if (mineAreaMap) {
                mineAreaMap.invalidateSize();
                if (loadingEl) loadingEl.style.display = 'none';
                return;
            }
            if (loadingEl) loadingEl.style.display = '';
            // Check if Leaflet is loaded
            if (typeof window.L === 'undefined' && typeof L === 'undefined') {
                initRetryCount++;
                if (initRetryCount > MAX_INIT_RETRIES) {
                    if (loadingEl) {
                        loadingEl.innerHTML = '<div class="text-center"><p class="text-red-400 mb-2">Map library failed to load</p><p class="text-gray-400 text-sm">Leaflet library could not be loaded from CDN</p><button onclick="location.reload()" class="mt-2 px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded">Retry</button></div>';
                    }
                    // show a short visual hint to the user
                    if (window.showMapHint) window.showMapHint('Map detected but failed to initialize. Check console for details.');
                    return;
                }
                setTimeout(initializeMineAreaMap, 200);
                return;
            }
            if (typeof L === 'undefined' && typeof window.L !== 'undefined') {
                window.L = window.L;
            }
            try {
                mineAreaMap = L.map(targetId).setView([-26.2041, 28.0473], 10);
                const osmLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '© OpenStreetMap contributors'
                });
                const satelliteLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                    maxZoom: 19,
                    attribution: 'Esri, Maxar, Earthstar Geographics'
                });
                osmLayer.addTo(mineAreaMap);
                L.control.layers({
                    'Standard': osmLayer,
                    'Satellite': satelliteLayer
                }).addTo(mineAreaMap);
                // Ensure default icon/image path points to the packaged draw images
                try {
                    if (L.Icon && L.Icon.Default) {
                        L.Icon.Default.imagePath = '/vendor/leaflet-draw/images/';
                        if (L.Icon.Default.prototype && L.Icon.Default.prototype.options) {
                            L.Icon.Default.prototype.options.imagePath = '/vendor/leaflet-draw/images/';
                        }
                    }
                } catch (e) {
                    console.warn('Could not set Leaflet default image path', e);
                }

                geofenceLayerGroup = L.layerGroup().addTo(mineAreaMap);
                renderGeofences();
                if (loadingEl) loadingEl.style.display = 'none';
                    // Determine if we're in draw mode based on active container
                    const drawModeActive = targetId === 'mine-area-draw-map';
                    // Drawing mode
                    if (drawModeActive) {
                        // Create a feature group to hold drawn layers
                        drawnItems = new L.FeatureGroup();
                        mineAreaMap.addLayer(drawnItems);

                        function createDrawControlsAndHandlers() {
                            try {
                                if (!L.Control || !L.Control.Draw) {
                                    console.warn('Leaflet.Draw not available on L.Control');
                                    return false;
                                }
                                const drawControl = new L.Control.Draw({
                                    position: 'topleft',
                                    draw: {
                                        polygon: true,
                                        rectangle: false,
                                        circle: false,
                                        marker: false,
                                        circlemarker: false,
                                        polyline: false
                                    },
                                    edit: {
                                        featureGroup: drawnItems,
                                        remove: true
                                    }
                                });
                                mineAreaMap.addControl(drawControl);
                                mineAreaMap.on('draw:created', function(e) {
                                    const layer = e.layer;
                                    drawnItems.addLayer(layer);
                                    if (layer.getLatLngs) {
                                        const coords = layer.getLatLngs()[0].map(point => ({
                                            lat: point.lat,
                                            lng: point.lng
                                        }));
                                        @this.setBoundary(coords);
                                    }
                                });
                                mineAreaMap.on('draw:edited', function(e) {
                                    e.layers.eachLayer(function(layer) {
                                        if (layer.getLatLngs) {
                                            const coords = layer.getLatLngs()[0].map(point => ({
                                                lat: point.lat,
                                                lng: point.lng
                                            }));
                                            @this.setBoundary(coords);
                                        }
                                    });
                                });
                                mineAreaMap.on('draw:deleted', function(e) {
                                    @this.clearBoundary();
                                });
                                return true;
                            } catch (err) {
                                console.error('Error creating draw controls:', err);
                                return false;
                            }
                        }

                        // If Draw plugin isn't attached yet, try to load the non-UMD build dynamically
                        if (typeof L.Control === 'undefined' || typeof L.Control.Draw === 'undefined') {
                            // Try to load the plain browser build if present
                            const drawScriptPath = '/vendor/leaflet-draw/leaflet.draw.js';
                            console.debug('Leaflet.Draw missing — attempting to load', drawScriptPath);
                            const s = document.createElement('script');
                            s.src = drawScriptPath;
                            s.onload = function() {
                                setTimeout(() => {
                                    if (!createDrawControlsAndHandlers()) {
                                        console.error('Leaflet.Draw loaded but controls still unavailable');
                                        window.showMapHint && window.showMapHint('Drawing tools unavailable');
                                    }
                                }, 50);
                            };
                            s.onerror = function() {
                                console.error('Failed to load Leaflet.Draw from', drawScriptPath);
                                window.showMapHint && window.showMapHint('Failed to load drawing tools');
                            };
                            document.head.appendChild(s);
                        } else {
                            createDrawControlsAndHandlers();
                        }
                    }
                console.debug('initializeMineAreaMap: targetId=', targetId, ' drawMode=', drawModeActive);
            } catch (error) {
                console.error('Error initializing map:', error);
                // show visual hint
                if (window.showMapHint) window.showMapHint('Map initialization error — check console');
                if (loadingEl) {
                    loadingEl.innerHTML = '<div class="text-center"><p class="text-red-400 mb-2">Failed to load map</p><p class="text-gray-400 text-sm">Please refresh the page</p></div>';
                }
            }
            setTimeout(() => {
                if (mineAreaMap) {
                    mineAreaMap.invalidateSize();
                }
            }, 250);
        }

        // Initialize on load
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => {
                setTimeout(initializeMineAreaMap, 100);
            });
        } else {
            setTimeout(initializeMineAreaMap, 100);
        }

        // Re-initialize on Livewire updates
        document.addEventListener('livewire:updated', () => {
            setTimeout(() => {
                initializeMineAreaMap();
            }, 100);
        });

        // Small helper to show a temporary visual hint overlay
        window.showMapHint = function(message, duration = 6000) {
            try {
                const hint = document.getElementById('map-hint');
                const hintText = document.getElementById('map-hint-text');
                if (!hint || !hintText) return;
                hintText.textContent = message;
                hint.style.display = '';
                hint.style.opacity = '1';
                setTimeout(() => {
                    hint.style.transition = 'opacity 400ms ease';
                    hint.style.opacity = '0';
                    setTimeout(() => { hint.style.display = 'none'; hint.style.transition = ''; }, 450);
                }, duration);
            } catch (e) {
                console.warn('showMapHint failed', e);
            }
        };

        // Observe the draw-map element and initialize when it appears / becomes visible.
        (function observeDrawMapInit() {
            const drawId = 'mine-area-draw-map';

            function isElementVisible(el) {
                if (!el) return false;
                const rect = el.getBoundingClientRect();
                return rect.width > 0 && rect.height > 0 && window.getComputedStyle(el).display !== 'none' && el.offsetParent !== null;
            }

            function tryInit() {
                const el = document.getElementById(drawId);
                const visible = isElementVisible(el);
                console.debug('observeDrawMapInit: check', { found: !!el, visible });
                if (visible) {
                    // initialize map for draw container
                    initializeMineAreaMap();
                    return true;
                }
                return false;
            }

            // Quick initial check shortly after load
            setTimeout(() => { tryInit(); }, 150);

            // MutationObserver: watch for DOM changes (Livewire swaps) and init when element appears or its attributes change
            const root = document.body;
            const mo = new MutationObserver(() => {
                if (tryInit()) {
                    mo.disconnect();
                    console.debug('observeDrawMapInit: MutationObserver disconnected after init');
                }
            });
            mo.observe(root, { childList: true, subtree: true, attributes: true, attributeFilter: ['style', 'class'] });

            // ResizeObserver: when the element exists, watch for non-zero size then init and disconnect
            (function waitForElementAndObserveResize() {
                const el = document.getElementById(drawId);
                if (!el) return setTimeout(waitForElementAndObserveResize, 200);
                try {
                    const ro = new ResizeObserver(entries => {
                        for (const entry of entries) {
                            const cr = entry.contentRect || {};
                            console.debug('observeDrawMapInit: ResizeObserver', { width: cr.width, height: cr.height });
                            if ((cr.width || el.clientWidth) > 0 && (cr.height || el.clientHeight) > 0) {
                                initializeMineAreaMap();
                                try { ro.disconnect(); } catch (e) {}
                            }
                        }
                    });
                    ro.observe(el);
                } catch (e) {
                    console.warn('observeDrawMapInit: ResizeObserver not available', e);
                }
            })();
        })();
    </script>
</div>
</div>
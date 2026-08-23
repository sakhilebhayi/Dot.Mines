<div>
<div class="h-screen flex flex-col bg-[var(--ink)]"
     data-path-coords="{{ json_encode($pathCoordinates ?? []) }}"
     data-geofences="{{ json_encode($geofences ?? []) }}"
     data-routes="{{ json_encode($routes ?? []) }}"
     data-machine-type="{{ $selectedMachineDetails->machine_type ?? '' }}"
     data-replay-center-lat="{{ $centerLat ?? -26.2041 }}"
     data-replay-center-lng="{{ $centerLng ?? 28.0473 }}"
     data-replay-zoom-level="{{ $zoomLevel ?? 10 }}">
    {{-- R7 decompose: this file was 1,676 lines with a ~1,240-line inline
         script and two inline style blocks. The script now ships as the
         resources/js/fleet-movement-replay.js Vite entry (same pattern as
         live-map), the styles as resources/css/fleet-movement-replay.css,
         and the markup as the livewire/replay/_* partials below. --}}

    @include('livewire.replay._header')

    <div class="flex-1 flex flex-col md:flex-row overflow-hidden">
        <!-- Left Sidebar - Controls -->
        <div class="w-full md:w-96 bg-[var(--ink-soft)] border-b md:border-b-0 md:border-r border-[var(--line)] overflow-y-auto p-4 md:p-6">
            @if ($isLoading)
                <div class="flex justify-center items-center h-96" x-init="window.scrollTo(0, 0)">
                    <svg class="animate-spin h-12 w-12 text-[var(--gold)]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                    </svg>
                </div>
            @else
                @include('livewire.replay._controls')
                @include('livewire.replay._player')
            @endif
        </div>

        @include('livewire.replay._map')
    </div>

    @vite(['resources/js/fleet-movement-replay.js'])
</div>
</div>

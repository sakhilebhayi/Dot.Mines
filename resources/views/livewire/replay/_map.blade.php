{{-- Map container and empty-state hover overlay (R7 split). The overlay
     show/hide helpers live in resources/js/fleet-movement-replay.js. --}}
    <!-- Map Container -->
    <div class="flex-1 relative bg-[var(--ink-soft)]" style="min-height: 400px;" wire:ignore>
        <!-- Map always visible -->
        <div id="replay-map" class="w-full h-full absolute inset-0" style="min-height: 400px;"></div>
        
        <!-- Hover overlay when no data loaded -->
        @if(!$selectedMachine || $totalPositions == 0)
        <div class="absolute inset-0 bg-[var(--ink)]/90 backdrop-blur-sm flex items-center justify-center opacity-0 hover:opacity-100 transition-opacity duration-300 pointer-events-none z-[400]" id="map-overlay">
            <div class="text-center p-8 pointer-events-auto">
                @if(!$selectedMachine)
                    <div class="text-6xl mb-4">🚜</div>
                    <h3 class="text-xl font-semibold text-[var(--stone)] mb-2">Select a Machine</h3>
                    <p class="text-[var(--sand)] mb-2">Choose a machine and date range to replay its movement history.</p>
                @else
                    <div class="text-6xl mb-4">📉</div>
                    <h3 class="text-xl font-semibold text-[var(--stone)] mb-2">No Data Available</h3>
                    <p class="text-[var(--sand)] mb-2">No movement data found for the selected time range.</p>
                @endif
            </div>
        </div>
        @endif
    </div>

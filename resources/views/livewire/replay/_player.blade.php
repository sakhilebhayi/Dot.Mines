{{-- Playback player: progress, transport controls, speed and options (R7 split) --}}
    <!-- Enhanced Playback Player -->
    <div class="bg-gradient-to-br from-[var(--ink-soft)] to-[var(--ink)] rounded-xl p-6 shadow-2xl border border-[var(--line)] mb-6">
        @if($selectedMachine && $totalPositions > 0)
            <!-- Player Header -->
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 bg-gradient-to-br from-[var(--gold)] to-[var(--gold-soft)] rounded-lg flex items-center justify-center shadow-lg">
                        <svg class="w-6 h-6 text-[var(--ink)]" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M8 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM15 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z"/>
                            <path fill-rule="evenodd" d="M4 5a2 2 0 00-2 2v6h16V7a2 2 0 00-2-2H4z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-[var(--stone)]">Movement Replay</h3>
                        <p class="text-sm text-[var(--sand)]">{{ $totalPositions }} recorded positions</p>
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-xs text-[var(--sand)] mb-1">Current Time</div>
                    <div class="text-sm font-mono text-[var(--gold)]" id="current-timestamp">--:--:--</div>
                </div>
            </div>

            <!-- Progress Bar with Timeline -->
            <div class="mb-6">
                <div class="relative h-3 bg-white/10 rounded-full overflow-hidden mb-2">
                    <!-- Background progress -->
                    <div class="absolute inset-0 bg-gradient-to-r from-[var(--gold)]/20 to-[var(--gold-soft)]/20"></div>
                    <!-- Active progress -->
                    <div class="absolute inset-y-0 left-0 bg-gradient-to-r from-[var(--gold)] to-[var(--gold-soft)] transition-all duration-300" 
                         style="width: {{ $totalPositions > 0 ? (($currentPosition + 1) / $totalPositions * 100) : 0 }}%">
                    </div>
                    <!-- Glow effect -->
                    <div class="absolute inset-y-0 bg-gradient-to-r from-transparent via-white/30 to-transparent animate-shimmer" 
                         style="width: {{ $totalPositions > 0 ? (($currentPosition + 1) / $totalPositions * 100) : 0 }}%; left: 0;">
                    </div>
                </div>
                
                <!-- Seekbar -->
                <input type="range" 
                       id="replay-slider"
                       min="0" 
                       max="{{ max(0, $totalPositions - 1) }}" 
                       value="{{ $currentPosition }}" 
                       class="w-full h-2 bg-transparent rounded-lg appearance-none cursor-pointer slider-thumb"
                       style="margin-top: -10px;">
                
                <!-- Timeline markers -->
                <div class="flex justify-between text-xs text-[var(--sand)]/70 mt-1 px-1">
                    <span id="start-time">{{ $startDate }}</span>
                    <span class="text-[var(--gold)] font-mono">{{ $currentPosition + 1 }} / {{ $totalPositions }}</span>
                    <span id="end-time">{{ $endDate }}</span>
                </div>
            </div>

            <!-- Main Controls -->
            <div class="flex items-center justify-center gap-3 mb-6">
                <!-- Previous Frame -->
                <button wire:click="previousFrame" 
                        class="p-3 bg-white/10 hover:bg-white/20 rounded-lg transition-all transform hover:scale-105 group"
                        title="Previous Frame">
                    <svg class="w-5 h-5 text-[var(--sand)] group-hover:text-[var(--stone)]" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M8.445 14.832A1 1 0 0010 14v-2.798l5.445 3.63A1 1 0 0017 14V6a1 1 0 00-1.555-.832L10 8.798V6a1 1 0 00-1.555-.832l-6 4a1 1 0 000 1.664l6 4z"/>
                    </svg>
                </button>

                <!-- Play/Pause Button -->
                @if($isPlaying)
                    <button wire:click="pause"
                            class="p-3 bg-gradient-to-br from-yellow-500 to-yellow-600 hover:from-yellow-600 hover:to-yellow-700 rounded-lg transition-all transform hover:scale-105 group"
                            title="Pause">
                        <svg class="w-5 h-5 text-[var(--stone)]" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M5 4a2 2 0 012-2h2a2 2 0 012 2v12a2 2 0 01-2 2H7a2 2 0 01-2-2V4zm8 0a2 2 0 012-2h2a2 2 0 012 2v12a2 2 0 01-2 2h-2a2 2 0 01-2-2V4z"/>
                        </svg>
                    </button>
                @else
                    <button wire:click="play" data-speed="{{ $playbackSpeed }}"
                            class="p-3 bg-gradient-to-br from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 rounded-lg transition-all transform hover:scale-105 group"
                            title="Play">
                        <svg class="w-5 h-5 text-[var(--stone)]" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M6.3 2.84A1.5 1.5 0 004 4.11v11.78a1.5 1.5 0 002.3 1.27l9.34-5.89a1.5 1.5 0 000-2.54L6.3 2.84z"/>
                        </svg>
                    </button>
                @endif

                <!-- Stop Button -->
                <button wire:click="stop"
                        class="p-3 bg-red-600 hover:bg-red-700 rounded-lg transition-all transform hover:scale-105 group"
                        title="Stop & Reset">
                    <svg class="w-5 h-5 text-[var(--stone)]" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8 7a1 1 0 00-1 1v4a1 1 0 001 1h4a1 1 0 001-1V8a1 1 0 00-1-1H8z" clip-rule="evenodd"/>
                    </svg>
                </button>

                <!-- Next Frame -->
                <button wire:click="nextFrame" 
                        class="p-3 bg-white/10 hover:bg-white/20 rounded-lg transition-all transform hover:scale-105 group"
                        title="Next Frame">
                    <svg class="w-5 h-5 text-[var(--sand)] group-hover:text-[var(--stone)]" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M4.555 5.168A1 1 0 003 6v8a1 1 0 001.555.832L10 11.202V14a1 1 0 001.555.832l6-4a1 1 0 000-1.664l-6-4A1 1 0 0010 6v2.798l-5.445-3.63z"/>
                    </svg>
                </button>
            </div>

            <!-- Speed Control & Additional Options -->
            <div class="space-y-4">
                <!-- Playback Speed -->
                <div class="bg-[var(--ink-soft)]/50 rounded-lg p-4 border border-[var(--line)]">
                    <div class="flex items-center justify-between mb-3">
                        <label class="text-sm font-medium text-[var(--sand)] flex items-center gap-2">
                            <svg class="w-4 h-4 text-[var(--gold)]" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
                            </svg>
                            Playback Speed
                        </label>
                        <span class="text-[var(--gold)] font-bold text-lg">{{ $playbackSpeed }}x</span>
                    </div>
                    <div class="flex gap-2">
                        @foreach([0.25, 0.5, 1, 2, 4, 8] as $speed)
                            <button wire:click="setSpeed({{ $speed }})" 
                                    class="flex-1 px-2 py-2 rounded-lg text-sm font-medium transition-all {{ $playbackSpeed == $speed ? 'bg-[var(--gold)] text-[var(--ink)] shadow-lg' : 'bg-white/10 text-[var(--sand)] hover:bg-white/20' }}">
                                {{ $speed }}x
                            </button>
                        @endforeach
                    </div>
                </div>

                <!-- Playback Options -->
                <div class="bg-[var(--ink-soft)]/50 rounded-lg p-4 border border-[var(--line)]">
                    <label class="text-sm font-medium text-[var(--sand)] mb-3 block flex items-center gap-2">
                        <svg class="w-4 h-4 text-[var(--gold)]" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"/>
                        </svg>
                        Options
                    </label>
                    <div class="space-y-2">
                        <label class="flex items-center gap-2 cursor-pointer group">
                            <input type="checkbox" wire:model="autoReplay" class="w-4 h-4 rounded bg-white/10 border-[var(--line)] text-[var(--gold)] focus:ring-[var(--gold)]">
                            <span class="text-sm text-[var(--sand)] group-hover:text-[var(--sand)]">Loop replay</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer group">
                            <input type="checkbox" wire:model="showTrail" checked class="w-4 h-4 rounded bg-white/10 border-[var(--line)] text-[var(--gold)] focus:ring-[var(--gold)]">
                            <span class="text-sm text-[var(--sand)] group-hover:text-[var(--sand)]">Show trail</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer group">
                            <input type="checkbox" wire:model="smoothPan" checked class="w-4 h-4 rounded bg-white/10 border-[var(--line)] text-[var(--gold)] focus:ring-[var(--gold)]">
                            <span class="text-sm text-[var(--sand)] group-hover:text-[var(--sand)]">Smooth camera</span>
                        </label>
                    </div>
                </div>
            </div>
        @else
            <!-- Empty State - Waiting for Data -->
            <div class="text-center py-12">
                <div class="w-20 h-20 bg-white/10 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-10 h-10 text-[var(--sand)]/60" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M8 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM15 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z"/>
                        <path fill-rule="evenodd" d="M4 5a2 2 0 00-2 2v6h16V7a2 2 0 00-2-2H4z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-[var(--stone)] mb-2">Ready to Replay</h3>
                <p class="text-[var(--sand)] text-sm">Select a machine, date range, and click "Load Replay" to view historical movements</p>
            </div>
        @endif
    </div>

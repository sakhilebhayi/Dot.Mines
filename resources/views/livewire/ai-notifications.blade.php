<div class="relative" x-data="{ open: @entangle('showPanel') }">
    <!-- Notification Bell -->
    <button @click="open = !open" class="relative p-2 text-[var(--sand)] hover:text-[var(--stone)] focus:outline-none transition-colors">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
        </svg>
        @if($unreadCount > 0)
        <span class="absolute top-0 right-0 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-[var(--stone)] transform translate-x-1/2 -translate-y-1/2 bg-red-600 rounded-full animate-pulse">
            {{ $unreadCount }}
        </span>
        @endif
    </button>

    <!-- Notification Panel -->
    <div x-show="open" 
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 transform scale-95"
         x-transition:enter-end="opacity-100 transform scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 transform scale-100"
         x-transition:leave-end="opacity-0 transform scale-95"
         @click.away="open = false"
         class="absolute right-0 mt-2 w-96 bg-[var(--ink-soft)] rounded-lg shadow-xl border border-[var(--line)] z-50"
         style="display: none;">
        
        <!-- Header -->
        <div class="p-4 border-b border-[var(--line)]">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-[var(--stone)]">
                    Notifications
                </h3>
                @if($unreadCount > 0)
                <button wire:click="acknowledgeAll" class="text-xs text-[var(--gold)] hover:text-[var(--gold-soft)] font-medium">
                    Mark all as read
                </button>
                @endif
            </div>
        </div>

        <!-- Notifications List -->
        <div class="max-h-96 overflow-y-auto">
            @forelse($notifications as $notification)
            <div class="p-4 border-b border-[var(--line)] hover:bg-white/5 transition-colors">
                <div class="flex items-start gap-3">
                    <!-- Severity Icon -->
                    <div class="flex-shrink-0 mt-1">
                        @if($notification['severity'] === 'critical')
                            <div class="p-2 bg-red-500/15 rounded-lg">
                                <svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                </svg>
                            </div>
                        @elseif($notification['severity'] === 'high')
                            <div class="p-2 bg-orange-500/15 rounded-lg">
                                <svg class="w-5 h-5 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                        @else
                            <div class="p-2 bg-yellow-500/15 rounded-lg">
                                <svg class="w-5 h-5 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                        @endif
                    </div>

                    <!-- Content -->
                    <div class="flex-1 min-w-0">
                        <div class="flex items-start justify-between gap-2 mb-1">
                            <h4 class="text-sm font-semibold text-[var(--stone)]">
                                {{ $notification['title'] }}
                            </h4>
                            <span class="px-2 py-0.5 text-xs font-medium rounded-full whitespace-nowrap
                                @if($notification['severity'] === 'critical') bg-red-900 text-red-200
                                @elseif($notification['severity'] === 'high') bg-orange-900 text-orange-200
                                @else bg-yellow-900 text-yellow-200 @endif">
                                {{ strtoupper($notification['severity']) }}
                            </span>
                        </div>
                        <p class="text-xs text-[var(--sand)] mb-2 line-clamp-2">
                            {{ $notification['message'] }}
                        </p>
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-xs text-[var(--sand)] truncate">
                                {{ $notification['created_at']?->diffForHumans() }}
                                @if($notification['location'])
                                    · {{ $notification['location'] }}
                                @endif
                            </span>
                            <button wire:click="acknowledge({{ $notification['id'] }}, '{{ $notification['source'] }}')"
                                class="text-xs text-[var(--gold)] hover:text-[var(--gold-soft)] font-medium flex-shrink-0">
                                Acknowledge
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            @empty
            <div class="p-8 text-center">
                <div class="text-4xl mb-2">✅</div>
                <p class="text-sm text-[var(--sand)]">No unread alerts</p>
                <p class="text-xs text-[var(--sand)]/70 mt-1">All systems running smoothly</p>
            </div>
            @endforelse
        </div>

        <!-- Footer -->
        @if($notifications->count() > 0)
        <div class="p-3 border-t border-[var(--line)]">
            <a href="{{ route('alerts') }}"
               class="block text-center text-sm text-[var(--gold)] hover:text-[var(--gold-soft)] font-medium"
               @click="open = false">
                View all alerts →
            </a>
        </div>
        @endif
    </div>
</div>

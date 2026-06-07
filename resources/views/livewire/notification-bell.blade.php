<div class="relative" x-data="{ open: @entangle('open') }">
    <!-- Bell Button -->
    <button wire:click="toggle"
            class="relative p-2 text-gray-400 hover:text-white transition-colors focus:outline-none"
            aria-label="Notifications">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
        </svg>
        @if ($unreadCount > 0)
            <span class="absolute top-1 right-1 inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 text-xs font-bold leading-none text-white bg-red-600 rounded-full animate-pulse">
                {{ $unreadCount > 99 ? '99+' : $unreadCount }}
            </span>
        @else
            <span class="absolute top-1 right-1 w-2 h-2 bg-green-500 rounded-full"></span>
        @endif
    </button>

    <!-- Dropdown Panel -->
    <div x-show="open"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-1"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-1"
         @click.away="open = false"
         class="absolute right-0 top-12 w-96 bg-gray-800 rounded-lg shadow-xl border border-gray-700 z-50"
         style="display:none;">

        <!-- Header -->
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-700">
            <h3 class="font-semibold text-white">
                Notifications
                @if ($unreadCount > 0)
                    <span class="ml-2 px-2 py-0.5 text-xs bg-red-600 text-white rounded-full">{{ $unreadCount }}</span>
                @endif
            </h3>
            @if ($unreadCount > 0)
                <button wire:click="markAllAsRead"
                        class="text-xs text-amber-400 hover:text-amber-300 font-medium transition-colors">
                    Mark all read
                </button>
            @endif
        </div>

        <!-- List -->
        <div class="max-h-[420px] overflow-y-auto divide-y divide-gray-700">
            @forelse ($notifications as $item)
                <div wire:key="notif-{{ $item['id'] }}"
                     class="flex items-start gap-3 px-4 py-3 hover:bg-gray-700/50 transition-colors {{ $item['is_read'] ? 'opacity-60' : '' }}">

                    <!-- Level indicator -->
                    <div class="flex-shrink-0 mt-0.5">
                        @php
                            $dot = match($item['alert_level']) {
                                'critical' => 'bg-red-500',
                                'high'     => 'bg-orange-500',
                                'warning'  => 'bg-yellow-500',
                                default    => 'bg-blue-500',
                            };
                        @endphp
                        <span class="inline-block w-2.5 h-2.5 rounded-full mt-1 {{ $dot }}"></span>
                    </div>

                    <!-- Content -->
                    <div class="flex-1 min-w-0">
                        <div class="flex items-start justify-between gap-2">
                            @if ($item['action_url'])
                                <a href="{{ $item['action_url'] }}"
                                   wire:click="markAsRead({{ $item['id'] }})"
                                   class="text-sm font-medium text-white hover:text-amber-400 transition-colors leading-snug line-clamp-1">
                                    {{ $item['title'] }}
                                </a>
                            @else
                                <span class="text-sm font-medium text-white leading-snug line-clamp-1">{{ $item['title'] }}</span>
                            @endif
                            @if (! $item['is_read'])
                                <button wire:click="markAsRead({{ $item['id'] }})"
                                        class="flex-shrink-0 text-xs text-gray-400 hover:text-white transition-colors"
                                        title="Mark as read">
                                    &times;
                                </button>
                            @endif
                        </div>
                        <p class="text-xs text-gray-400 mt-0.5 line-clamp-2">{{ $item['message'] }}</p>
                        <p class="text-xs text-gray-500 mt-1">{{ $item['created_at'] }}</p>
                    </div>
                </div>
            @empty
                <div class="px-4 py-8 text-center">
                    <p class="text-sm text-gray-400">No notifications yet</p>
                    <p class="text-xs text-gray-500 mt-1">You're all caught up!</p>
                </div>
            @endforelse
        </div>

        <!-- Footer -->
        @if (count($notifications) > 0)
            <div class="px-4 py-2 border-t border-gray-700 text-center">
                <a href="{{ route('dashboard') }}"
                   class="text-xs text-amber-400 hover:text-amber-300 font-medium transition-colors">
                    View all notifications
                </a>
            </div>
        @endif
    </div>
</div>

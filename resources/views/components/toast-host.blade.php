{{--
    The single toast host for the whole app -- included by both layouts, so
    the two copies can never drift again (they already had: one layout
    shipped an id-collision bug and a contrast failure the other had fixed).

    Contract: listens for the `notify` browser event with NAMED payload
    fields ({type, message}) -- exactly what App\Livewire\Concerns\
    NotifiesUser::notify() dispatches. Positional-array dispatches land one
    level deeper (detail[0]) and render a blank pill; the trait plus
    NotificationContractTest exist so that shape can't come back.

    Durations are per type: a success confirmation can be glanceable, but an
    error or warning must survive long enough to be read and acted on.
    Everything is manually dismissible; Escape clears the stack.
--}}
<div x-data="{
        notifications: [],
        durationFor(type) {
            return { success: 5000, info: 6000, warning: 8000, error: 10000 }[type] ?? 6000;
        },
        addNotification(type, message) {
            // Guard the contract at the last line of defence too: a blank
            // toast tells the user nothing and looks broken.
            if (!message) { console.error('notify event without a message', type); return; }
            // Date.now() alone collides when two notify events fire in the
            // same millisecond (confirmed live: three simultaneous toasts
            // shared one id and Alpine dropped all but one).
            const id = Date.now() + Math.random();
            this.notifications.push({ id, type, message });
            setTimeout(() => this.removeNotification(id), this.durationFor(type));
        },
        removeNotification(id) {
            this.notifications = this.notifications.filter(n => n.id !== id);
        }
    }"
    @notify.window="addNotification($event.detail.type, $event.detail.message)"
    @keydown.escape.window="notifications = []"
    aria-label="Notifications"
    class="fixed top-24 right-4 z-[1200] space-y-2 max-w-md">
    <template x-for="notification in notifications" :key="notification.id">
        <div
            x-show="true"
            :role="notification.type === 'error' ? 'alert' : 'status'"
            :aria-live="notification.type === 'error' ? 'assertive' : 'polite'"
            aria-atomic="true"
            x-transition:enter="transition ease-out duration-300 motion-reduce:duration-0"
            x-transition:enter-start="opacity-0 transform translate-x-8 motion-reduce:translate-x-0"
            x-transition:enter-end="opacity-100 transform translate-x-0"
            x-transition:leave="transition ease-in duration-200 motion-reduce:duration-0"
            x-transition:leave-start="opacity-100 transform translate-x-0"
            x-transition:leave-end="opacity-0 transform translate-x-8 motion-reduce:translate-x-0"
            class="relative rounded-lg shadow-2xl p-4 flex items-start gap-3 backdrop-blur-sm"
            :class="{
                'bg-green-600/90 border border-green-500': notification.type === 'success',
                'bg-red-600/90 border border-red-500': notification.type === 'error',
                'bg-yellow-600/90 border border-yellow-500': notification.type === 'warning',
                'bg-[var(--gold)]/90 border border-[var(--gold)]': notification.type === 'info'
            }">
            {{-- Icon. The info toast sits on gold (a mid tone), so its icon and
                 text must be dark -- near-white on gold fails contrast. --}}
            <div class="flex-shrink-0" aria-hidden="true">
                <template x-if="notification.type === 'success'">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </template>
                <template x-if="notification.type === 'error'">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </template>
                <template x-if="notification.type === 'warning'">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                </template>
                <template x-if="notification.type === 'info'">
                    <svg class="w-6 h-6 text-[var(--ink)]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </template>
                <span class="sr-only" x-text="{success: 'Success', error: 'Error', warning: 'Warning', info: 'Info'}[notification.type]"></span>
            </div>

            <div class="flex-1 font-medium" :class="notification.type === 'info' ? 'text-[var(--ink)]' : 'text-[var(--stone)]'" x-text="notification.message"></div>

            <button
                @click="removeNotification(notification.id)"
                aria-label="Dismiss notification"
                class="flex-shrink-0 transition-colors"
                :class="notification.type === 'info' ? 'text-[var(--ink)]/70 hover:text-[var(--ink)]' : 'text-[var(--stone)]/70 hover:text-[var(--stone)]'">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
    </template>
</div>

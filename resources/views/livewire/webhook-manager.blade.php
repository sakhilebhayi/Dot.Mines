<div class="px-6 py-8">
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-3xl font-display font-semibold text-[var(--stone)]">Webhooks</h2>
                <p class="text-[var(--sand)] mt-2">Have Mines push events to your own systems, instead of polling for them</p>
            </div>
            <button
                wire:click="$set('showCreateModal', true)"
                class="px-6 py-3 bg-[var(--gold)] hover:bg-[var(--gold-soft)] text-[var(--ink)] rounded-lg font-display font-semibold transition"
            >
                + Add Endpoint
            </button>
        </div>
    </div>

    {{-- The one moment the plaintext secret exists outside the encrypted column. --}}
    @if($newSecret)
        <div class="mb-8 p-5 bg-[var(--gold)]/10 border border-[var(--gold)]/40 rounded-lg">
            <h3 class="text-[var(--stone)] font-display font-semibold text-lg">Copy your signing secret now</h3>
            <p class="text-[var(--sand)] text-sm mt-1">
                This is the only time it is shown. Your receiver needs it to verify that a request really came from Mines.
            </p>
            <div class="mt-3 flex items-center gap-3 flex-wrap">
                <code class="px-3 py-2 bg-[var(--ink)] border border-[var(--line)] rounded text-[var(--stone)] text-sm break-all">{{ $newSecret }}</code>
                <button wire:click="dismissSecret" class="px-4 py-2 border border-[var(--line-strong)] text-[var(--sand)] hover:text-[var(--stone)] rounded-lg text-sm transition">
                    I have stored it
                </button>
            </div>
        </div>
    @endif

    @if($this->endpoints->isEmpty())
        <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg p-10 text-center">
            <h3 class="text-[var(--stone)] font-display font-semibold text-lg">No endpoints yet</h3>
            <p class="text-[var(--sand)] mt-2 max-w-xl mx-auto">
                Add a URL and Mines will POST events to it as they happen &mdash; alerts, geofence crossings,
                machines going offline. Every request is signed so you can verify it came from us.
            </p>
        </div>
    @else
        <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-[var(--ink)] border-b border-[var(--line)]">
                        <tr>
                            <th class="px-6 py-4 text-left text-[var(--stone)] font-semibold">Endpoint</th>
                            <th class="px-6 py-4 text-left text-[var(--stone)] font-semibold">Events</th>
                            <th class="px-6 py-4 text-left text-[var(--stone)] font-semibold">Status</th>
                            <th class="px-6 py-4 text-left text-[var(--stone)] font-semibold">Last delivery</th>
                            <th class="px-6 py-4 text-right text-[var(--stone)] font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($this->endpoints as $endpoint)
                            <tr wire:key="endpoint-{{ $endpoint->id }}" class="border-b border-[var(--line)]">
                                <td class="px-6 py-4">
                                    <div class="text-[var(--stone)] break-all">{{ $endpoint->url }}</div>
                                    @if($endpoint->description)
                                        <div class="text-[var(--sand)] text-xs mt-1">{{ $endpoint->description }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-[var(--sand)] text-sm">
                                    @if(in_array('*', $endpoint->events, true))
                                        All events
                                    @else
                                        {{ implode(', ', $endpoint->events) }}
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    @if($endpoint->is_active)
                                        <span class="inline-block px-2 py-1 bg-green-900 text-green-200 text-xs rounded">Active</span>
                                    @elseif($endpoint->auto_disabled_at)
                                        <span class="inline-block px-2 py-1 bg-red-900 text-red-200 text-xs rounded">Auto-disabled</span>
                                    @else
                                        <span class="inline-block px-2 py-1 bg-white/10 text-[var(--sand)] text-xs rounded">Paused</span>
                                    @endif
                                    @if($endpoint->last_failure_reason && ! $endpoint->is_active)
                                        <div class="text-[var(--sand)] text-xs mt-1">{{ $endpoint->last_failure_reason }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-[var(--sand)] text-sm">
                                    @if($endpoint->last_success_at)
                                        Delivered {{ $endpoint->last_success_at->diffForHumans() }}
                                    @elseif($endpoint->last_failure_at)
                                        Failed {{ $endpoint->last_failure_at->diffForHumans() }}
                                    @else
                                        &mdash;
                                    @endif
                                    @if($endpoint->consecutive_failures > 0)
                                        <div class="text-red-300 text-xs mt-1">{{ $endpoint->consecutive_failures }} in a row failed</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-right whitespace-nowrap">
                                    <button wire:click="sendTest({{ $endpoint->id }})" wire:loading.attr="disabled" class="px-3 py-1.5 text-sm border border-[var(--line-strong)] text-[var(--sand)] hover:text-[var(--stone)] rounded transition">
                                        Send test
                                    </button>
                                    <button wire:click="showDeliveries({{ $endpoint->id }})" class="px-3 py-1.5 text-sm border border-[var(--line-strong)] text-[var(--sand)] hover:text-[var(--stone)] rounded transition">
                                        {{ $viewingDeliveriesFor === $endpoint->id ? 'Hide' : 'Deliveries' }}
                                    </button>
                                    <button wire:click="toggleActive({{ $endpoint->id }})" class="px-3 py-1.5 text-sm border border-[var(--line-strong)] text-[var(--sand)] hover:text-[var(--stone)] rounded transition">
                                        {{ $endpoint->is_active ? 'Pause' : 'Enable' }}
                                    </button>
                                    <button wire:click="delete({{ $endpoint->id }})" wire:confirm="Delete this endpoint? Events will stop being sent to it." class="px-3 py-1.5 text-sm border border-red-900 text-red-300 hover:text-red-200 rounded transition">
                                        Delete
                                    </button>
                                </td>
                            </tr>

                            @if($viewingDeliveriesFor === $endpoint->id)
                                <tr wire:key="deliveries-{{ $endpoint->id }}">
                                    <td colspan="5" class="px-6 py-4 bg-[var(--ink)]">
                                        @if($this->deliveries->isEmpty())
                                            <p class="text-[var(--sand)] text-sm">Nothing sent to this endpoint yet.</p>
                                        @else
                                            <table class="w-full text-sm">
                                                <thead>
                                                    <tr class="text-[var(--sand)]">
                                                        <th class="text-left py-2">Event</th>
                                                        <th class="text-left py-2">When</th>
                                                        <th class="text-left py-2">Result</th>
                                                        <th class="text-left py-2">Attempts</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($this->deliveries as $delivery)
                                                        <tr wire:key="delivery-{{ $delivery->id }}" class="border-t border-[var(--line)]">
                                                            <td class="py-2 text-[var(--stone)]">{{ $delivery->event }}</td>
                                                            <td class="py-2 text-[var(--sand)]">{{ $delivery->created_at->diffForHumans() }}</td>
                                                            <td class="py-2">
                                                                @if($delivery->status === 'delivered')
                                                                    <span class="text-green-300">{{ $delivery->response_status }} in {{ $delivery->duration_ms }}ms</span>
                                                                @elseif($delivery->status === 'failed')
                                                                    <span class="text-red-300">{{ $delivery->error }}</span>
                                                                @else
                                                                    {{-- "Retrying" only once something has actually been tried. --}}
                                                                    <span class="text-[var(--sand)]">
                                                                        @if($delivery->attempts === 0)
                                                                            Queued
                                                                        @else
                                                                            {{ $delivery->error }}
                                                                            @if($delivery->next_attempt_at)
                                                                                &mdash; retrying {{ $delivery->next_attempt_at->diffForHumans() }}
                                                                            @endif
                                                                        @endif
                                                                    </span>
                                                                @endif
                                                            </td>
                                                            <td class="py-2 text-[var(--sand)]">{{ $delivery->attempts }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        @endif
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if($showCreateModal)
        <x-dialog-modal wire:model.live="showCreateModal">
            <x-slot name="title">Add a webhook endpoint</x-slot>

            <x-slot name="content">
                <label class="block text-sm text-[var(--stone)] mb-1">URL</label>
                <input type="url" wire:model="url" placeholder="https://your-system.example.com/mines-events"
                       class="w-full px-3 py-2 bg-[var(--ink)] border border-[var(--line)] rounded text-[var(--stone)]">
                <p class="text-[var(--sand)] text-xs mt-1">Must be https, and must be reachable from the public internet.</p>
                @error('url') <p class="text-red-300 text-sm mt-1">{{ $message }}</p> @enderror

                <label class="block text-sm text-[var(--stone)] mb-1 mt-4">Description <span class="text-[var(--sand)]">(optional)</span></label>
                <input type="text" wire:model="description" placeholder="Ops pager"
                       class="w-full px-3 py-2 bg-[var(--ink)] border border-[var(--line)] rounded text-[var(--stone)]">
                @error('description') <p class="text-red-300 text-sm mt-1">{{ $message }}</p> @enderror

                <div class="mt-4">
                    <label class="flex items-center gap-2 text-[var(--stone)]">
                        <input type="checkbox" wire:model.live="subscribeToAll" class="rounded">
                        <span>Send me everything, including events added later</span>
                    </label>
                </div>

                @if(! $subscribeToAll)
                    <div class="mt-3 space-y-2">
                        @foreach($this->availableEvents as $eventName => $eventDescription)
                            <label class="flex items-start gap-2 text-sm" wire:key="event-{{ $eventName }}">
                                <input type="checkbox" wire:model="selectedEvents" value="{{ $eventName }}" class="mt-1 rounded">
                                <span>
                                    <code class="text-[var(--stone)]">{{ $eventName }}</code>
                                    <span class="block text-[var(--sand)] text-xs">{{ $eventDescription }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('selectedEvents') <p class="text-red-300 text-sm mt-1">{{ $message }}</p> @enderror
                @endif
            </x-slot>

            <x-slot name="footer">
                <button wire:click="$set('showCreateModal', false)" class="px-4 py-2 text-[var(--sand)] hover:text-[var(--stone)] transition">
                    Cancel
                </button>
                <button wire:click="create" wire:loading.attr="disabled"
                        class="ml-3 px-5 py-2 bg-[var(--gold)] hover:bg-[var(--gold-soft)] text-[var(--ink)] rounded-lg font-semibold transition">
                    Create endpoint
                </button>
            </x-slot>
        </x-dialog-modal>
    @endif
</div>

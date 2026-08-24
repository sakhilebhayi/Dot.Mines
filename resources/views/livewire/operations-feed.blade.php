<div class="px-4 sm:px-6 py-8 max-w-3xl mx-auto" wire:poll.60s>
    <div class="mb-6 flex items-center justify-between gap-3">
        <div>
            <h2 class="text-3xl font-display font-semibold text-[var(--stone)]">Operations Feed</h2>
            <p class="text-[var(--sand)] mt-1">What is happening across the mine — live events and team announcements</p>
        </div>
        @if($this->canPost)
            <button wire:click="$toggle('showComposer')"
                    class="shrink-0 px-5 py-2.5 bg-[var(--gold)] hover:bg-[var(--gold-soft)] text-[var(--ink)] rounded-lg font-display font-semibold transition">
                + Post
            </button>
        @endif
    </div>

    {{-- Composer --}}
    @if($showComposer && $this->canPost)
        <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg p-4 mb-6">
            <input type="text" wire:model="postTitle" placeholder="What does the team need to know?"
                   class="w-full px-3 py-2 bg-[var(--ink)] border border-[var(--line)] rounded text-[var(--stone)]">
            @error('postTitle') <p class="text-red-300 text-sm mt-1">{{ $message }}</p> @enderror
            <textarea wire:model="postBody" rows="2" placeholder="Details (optional) — e.g. Dump Area B closed until 15:00, redirect ADTs to Dump Area A."
                      class="w-full mt-2 px-3 py-2 bg-[var(--ink)] border border-[var(--line)] rounded text-[var(--stone)]"></textarea>
            <div class="flex items-center justify-between mt-2">
                <select wire:model="postCategory" class="px-3 py-1.5 bg-[var(--ink)] border border-[var(--line)] rounded text-[var(--stone)] text-sm">
                    @foreach(\App\Models\FeedItem::CATEGORIES as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <button wire:click="post" wire:loading.attr="disabled"
                        class="px-5 py-2 bg-[var(--gold)] hover:bg-[var(--gold-soft)] text-[var(--ink)] rounded-lg font-semibold transition">
                    Publish
                </button>
            </div>
        </div>
    @endif

    {{-- Filters --}}
    <div class="flex flex-wrap items-center gap-2 mb-4">
        <button wire:click="$set('category', '')"
                class="px-3 py-1.5 rounded-full text-xs font-medium transition {{ $category === '' ? 'bg-[var(--gold)] text-[var(--ink)]' : 'bg-white/5 text-[var(--sand)] hover:text-[var(--stone)] border border-[var(--line)]' }}">
            All
        </button>
        @foreach(\App\Models\FeedItem::CATEGORIES as $value => $label)
            <button wire:click="$set('category', '{{ $value }}')" wire:key="cat-{{ $value }}"
                    class="px-3 py-1.5 rounded-full text-xs font-medium transition {{ $category === $value ? 'bg-[var(--gold)] text-[var(--ink)]' : 'bg-white/5 text-[var(--sand)] hover:text-[var(--stone)] border border-[var(--line)]' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>
    <div class="flex flex-wrap gap-2 mb-6">
        <input type="search" wire:model.live.debounce.400ms="search" placeholder="Search the feed…"
               class="flex-1 min-w-48 px-3 py-2 bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg text-[var(--stone)] text-sm">
        <select wire:model.live="timeWindow" class="px-3 py-2 bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg text-[var(--stone)] text-sm">
            <option value="">All time</option>
            <option value="today">Today</option>
            <option value="24h">Last 24 hours</option>
            <option value="week">This week</option>
        </select>
        <select wire:model.live="machineFilter" class="px-3 py-2 bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg text-[var(--stone)] text-sm">
            <option value="">All machines</option>
            @foreach($this->machines as $machine)
                <option value="{{ $machine->id }}">{{ $machine->name }}</option>
            @endforeach
        </select>
    </div>

    {{-- Pinned announcements --}}
    @foreach($this->pinned as $item)
        <div wire:key="pinned-{{ $item->id }}" class="mb-3 bg-[var(--gold)]/10 border border-[var(--gold)]/40 rounded-lg p-4">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <span class="text-[var(--gold)] text-xs font-semibold uppercase tracking-wide">📌 Pinned</span>
                    <h3 class="text-[var(--stone)] font-display font-semibold mt-0.5">{{ $item->title }}</h3>
                    @if($item->body)<p class="text-[var(--sand)] text-sm mt-1 whitespace-pre-line">{{ $item->body }}</p>@endif
                    <p class="text-[var(--sand)] text-xs mt-2">
                        @if($item->user) {{ $item->user->name }} · @endif
                        {{ $item->occurred_at->diffForHumans() }} · pinned until {{ $item->pinned_until?->format('d/m H:i') }}
                    </p>
                </div>
                @if($this->canPin)
                    <button wire:click="unpin({{ $item->id }})" class="text-[var(--sand)] hover:text-[var(--stone)] text-xs shrink-0">Unpin</button>
                @endif
            </div>
        </div>
    @endforeach

    {{-- Stream --}}
    @php $stream = $this->stream; @endphp
    @if($stream['items']->isEmpty() && $this->pinned->isEmpty())
        <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg p-10 text-center">
            <h3 class="text-[var(--stone)] font-display font-semibold text-lg">Nothing here yet</h3>
            <p class="text-[var(--sand)] mt-2 max-w-md mx-auto">
                Operational events — alerts, geofence crossings, machines going offline, operator assignments —
                appear here as they happen, alongside announcements from the team.
            </p>
        </div>
    @else
        <div class="space-y-3">
            @foreach($stream['items'] as $item)
                <div wire:key="item-{{ $item->id }}" class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                @if($item->isSystem())
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold uppercase tracking-wide bg-white/10 text-[var(--sand)]">System</span>
                                @else
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold uppercase tracking-wide bg-[var(--gold)]/20 text-[var(--gold)]">👤 {{ $item->user?->name ?? 'Team member' }}</span>
                                @endif
                                <span class="text-[var(--sand)] text-xs">{{ \App\Models\FeedItem::CATEGORIES[$item->category] ?? ucfirst($item->category) }}</span>
                                <span class="text-[var(--sand)] text-xs" title="{{ $item->occurred_at->toDayDateTimeString() }}">{{ $item->occurred_at->diffForHumans() }}</span>
                            </div>
                            <h3 class="text-[var(--stone)] font-semibold mt-1.5">{{ $item->title }}</h3>
                            @if($item->body)<p class="text-[var(--sand)] text-sm mt-1 whitespace-pre-line">{{ $item->body }}</p>@endif
                            <div class="flex items-center gap-3 mt-2">
                                @if($item->machine)
                                    <a href="{{ route('fleet.show', $item->machine) }}" class="text-[var(--gold)] text-xs hover:underline">View {{ $item->machine->name }}</a>
                                @endif
                                @if($item->operator)
                                    <a href="{{ route('operators.show', $item->operator) }}" class="text-[var(--gold)] text-xs hover:underline">View {{ $item->operator->name }}</a>
                                @endif
                                @if($item->action_url && ! $item->machine && ! $item->operator)
                                    <a href="{{ $item->action_url }}" class="text-[var(--gold)] text-xs hover:underline">View</a>
                                @endif
                            </div>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            @if($this->canPin && ! $item->isPinned())
                                <button wire:click="pin({{ $item->id }})" class="text-[var(--sand)] hover:text-[var(--stone)] text-xs" title="Pin for 24 hours">📌</button>
                            @endif
                            @if(! $item->isSystem() && ($item->user_id === auth()->id() || $this->canPin))
                                <button wire:click="deleteItem({{ $item->id }})" wire:confirm="Remove this post?" class="text-[var(--sand)] hover:text-red-300 text-xs">✕</button>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        @if($stream['hasMore'])
            <div class="mt-4 text-center">
                <button wire:click="loadMore" wire:loading.attr="disabled"
                        class="px-5 py-2 border border-[var(--line-strong)] text-[var(--sand)] hover:text-[var(--stone)] rounded-lg text-sm transition">
                    Load older items
                </button>
            </div>
        @endif
    @endif

    @push('scripts')
        <script nonce="{{ request()->attributes->get('csp_nonce') }}">
            // New feed items arrive over the team's existing private channel;
            // a refresh re-renders through Livewire so authorisation and
            // filtering stay server-side.
            document.addEventListener('livewire:init', () => {
                const teamId = {{ (int) (auth()->user()?->current_team_id ?? 0) }};
                if (teamId && window.Echo) {
                    window.Echo.private(`team.${teamId}`).listen('.feed.item.posted', () => Livewire.dispatch('feed-refresh'));
                }
            });
        </script>
    @endpush
</div>

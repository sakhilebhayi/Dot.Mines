<div class="px-4 sm:px-6 py-8 sm:py-10 max-w-3xl mx-auto" wire:poll.60s>
    {{--
        Spacing system for this page (audited, not ad hoc):
        page sections mb-8 · cards p-5 rounded-xl · stream rhythm space-y-4 ·
        zones inside a card separated by mt-3/pt-3 with a hairline where the
        zone changes purpose (content -> actions) · meta text-xs, body
        text-sm leading-relaxed, title base semibold. Matches the fleet
        cards' p-5/rounded-xl so the app keeps one card language.
    --}}

    <div class="mb-8 flex items-start justify-between gap-4">
        <div class="min-w-0">
            <h2 class="text-3xl font-display font-semibold text-[var(--stone)]">Operations Feed</h2>
            <p class="text-[var(--sand)] mt-1.5 leading-relaxed">What is happening across the mine — live events and team announcements</p>
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
        <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-xl p-5 mb-8">
            <input type="text" wire:model="postTitle" placeholder="What does the team need to know?"
                   class="w-full px-3.5 py-2.5 bg-[var(--ink)] border border-[var(--line)] rounded-lg text-[var(--stone)]">
            @error('postTitle') <p class="text-red-300 text-sm mt-2">{{ $message }}</p> @enderror
            <textarea wire:model="postBody" rows="2" placeholder="Details (optional) — e.g. Dump Area B closed until 15:00, redirect ADTs to Dump Area A."
                      class="w-full mt-3 px-3.5 py-2.5 bg-[var(--ink)] border border-[var(--line)] rounded-lg text-[var(--stone)] leading-relaxed"></textarea>
            <div class="flex items-center justify-between gap-3 mt-4">
                <select wire:model="postCategory" class="px-3 py-2 bg-[var(--ink)] border border-[var(--line)] rounded-lg text-[var(--stone)] text-sm">
                    @foreach(\App\Models\FeedItem::CATEGORIES as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <button wire:click="post" wire:loading.attr="disabled"
                        class="px-5 py-2.5 bg-[var(--gold)] hover:bg-[var(--gold-soft)] text-[var(--ink)] rounded-lg font-semibold transition">
                    Publish
                </button>
            </div>
        </div>
    @endif

    {{-- Filters --}}
    <div class="mb-8">
        <div class="flex flex-wrap items-center gap-2.5 mb-4">
            <button wire:click="$set('category', '')"
                    class="px-4 py-2 rounded-full text-sm font-medium transition {{ $category === '' ? 'bg-[var(--gold)] text-[var(--ink)]' : 'bg-white/5 text-[var(--sand)] hover:text-[var(--stone)] border border-[var(--line)]' }}">
                All
            </button>
            @foreach(\App\Models\FeedItem::CATEGORIES as $value => $label)
                <button wire:click="$set('category', '{{ $value }}')" wire:key="cat-{{ $value }}"
                        class="px-4 py-2 rounded-full text-sm font-medium transition {{ $category === $value ? 'bg-[var(--gold)] text-[var(--ink)]' : 'bg-white/5 text-[var(--sand)] hover:text-[var(--stone)] border border-[var(--line)]' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>
        <div class="flex flex-wrap gap-3">
            <input type="search" wire:model.live.debounce.400ms="search" placeholder="Search the feed…"
                   class="flex-1 min-w-48 px-3.5 py-2 bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg text-[var(--stone)] text-sm">
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
    </div>

    {{-- Pinned announcements --}}
    @if($this->pinned->isNotEmpty())
        <div class="space-y-4 mb-8">
            @foreach($this->pinned as $item)
                <div wire:key="pinned-{{ $item->id }}" class="bg-[var(--gold)]/10 border border-[var(--gold)]/40 rounded-xl p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <span class="text-[var(--gold)] text-xs font-semibold uppercase tracking-wide">📌 Pinned</span>
                            <h3 class="text-[var(--stone)] font-display font-semibold text-base mt-1.5 break-words">{{ $item->title }}</h3>
                            @if($item->body)<p class="text-[var(--sand)] text-sm mt-2 leading-relaxed whitespace-pre-line break-words">{{ $item->body }}</p>@endif
                            <p class="text-[var(--sand)] text-xs mt-3">
                                @if($item->user) {{ $item->user->name }} · @endif
                                {{ $item->occurred_at->diffForHumans() }} · pinned until {{ $item->pinned_until?->format('d/m H:i') }}
                            </p>
                        </div>
                        @if($this->canPin)
                            <button wire:click="unpin({{ $item->id }})" class="text-[var(--sand)] hover:text-[var(--stone)] text-xs shrink-0 px-2 py-1 rounded transition">Unpin</button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Stream --}}
    @php $stream = $this->stream; @endphp
    @if($stream['items']->isEmpty() && $this->pinned->isEmpty())
        <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-xl p-12 text-center">
            <h3 class="text-[var(--stone)] font-display font-semibold text-lg">Nothing here yet</h3>
            <p class="text-[var(--sand)] mt-3 max-w-md mx-auto leading-relaxed">
                Operational events — alerts, geofence crossings, machines going offline, operator assignments —
                appear here as they happen, alongside announcements from the team.
            </p>
        </div>
    @else
        <div class="space-y-4">
            @foreach($stream['items'] as $item)
                <div wire:key="item-{{ $item->id }}" class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-xl p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0 flex-1">
                            {{-- Meta eyebrow: source, category, time --}}
                            <div class="flex items-center gap-2.5 flex-wrap mb-2">
                                @if($item->isSystem())
                                    <span class="px-2 py-0.5 rounded text-[10px] font-semibold uppercase tracking-wide bg-white/10 text-[var(--sand)]">System</span>
                                @else
                                    <span class="px-2 py-0.5 rounded text-[10px] font-semibold uppercase tracking-wide bg-[var(--gold)]/20 text-[var(--gold)]">👤 {{ $item->user?->name ?? 'Team member' }}</span>
                                @endif
                                <span class="text-[var(--sand)] text-xs">{{ \App\Models\FeedItem::CATEGORIES[$item->category] ?? ucfirst($item->category) }}</span>
                                <span class="text-[var(--sand)]/60 text-xs">·</span>
                                <span class="text-[var(--sand)] text-xs" title="{{ $item->occurred_at->toDayDateTimeString() }}">{{ $item->occurred_at->diffForHumans() }}</span>
                            </div>

                            {{-- Primary: what happened --}}
                            <h3 class="text-[var(--stone)] font-display font-semibold text-base break-words">{{ $item->title }}</h3>

                            {{-- Secondary: the detail --}}
                            @if($item->body)
                                <p class="text-[var(--sand)] text-sm mt-2 leading-relaxed whitespace-pre-line break-words">{{ $item->body }}</p>
                            @endif

                            @if($item->machine || $item->operator || $item->action_url)
                                <div class="flex items-center gap-4 mt-3">
                                    @if($item->machine)
                                        <a href="{{ route('fleet.show', $item->machine) }}" class="text-[var(--gold)] text-xs font-medium hover:underline">View {{ $item->machine->name }}</a>
                                    @endif
                                    @if($item->operator)
                                        <a href="{{ route('operators.show', $item->operator) }}" class="text-[var(--gold)] text-xs font-medium hover:underline">View {{ $item->operator->name }}</a>
                                    @endif
                                    @if($item->action_url && ! $item->machine && ! $item->operator)
                                        <a href="{{ $item->action_url }}" class="text-[var(--gold)] text-xs font-medium hover:underline">View</a>
                                    @endif
                                </div>
                            @endif

                            {{-- Action zone: separated from content by a hairline --}}
                            <div class="flex items-center gap-2 mt-4 pt-4 border-t border-[var(--line)]">
                                @foreach(\App\Models\FeedItem::REACTIONS as $emoji)
                                    @php
                                        $reactionCount = $item->reactions->where('emoji', $emoji)->count();
                                        $mine = $item->reactions->where('emoji', $emoji)->where('user_id', auth()->id())->isNotEmpty();
                                    @endphp
                                    @if($this->canComment || $reactionCount > 0)
                                        <button @if($this->canComment) wire:click="toggleReaction({{ $item->id }}, '{{ $emoji }}')" @endif
                                                wire:key="react-{{ $item->id }}-{{ $loop->index }}"
                                                class="px-3 py-1.5 rounded-full text-sm border transition {{ $mine ? 'border-[var(--gold)] bg-[var(--gold)]/15 text-[var(--gold)]' : 'border-[var(--line)] text-[var(--sand)] hover:text-[var(--stone)]' }}">
                                            {{ $emoji }}@if($reactionCount > 0) {{ $reactionCount }}@endif
                                        </button>
                                    @endif
                                @endforeach
                                <button wire:click="toggleComments({{ $item->id }})"
                                        class="px-3 py-1.5 rounded-full text-sm border border-[var(--line)] text-[var(--sand)] hover:text-[var(--stone)] transition">
                                    💬 {{ $item->comments_count ?? 0 }}
                                </button>
                            </div>

                            @if($openCommentsFor === $item->id)
                                {{-- The thread lives in its own inset panel: hairlines are
                                     too subtle on this theme to contain a zone, so the
                                     containment is a real surface with its own padding. --}}
                                <div class="mt-4 bg-[var(--ink)]/70 border border-[var(--line)] rounded-lg p-4">
                                    <div class="space-y-5">
                                        @forelse($this->openComments as $comment)
                                            <div wire:key="comment-{{ $comment->id }}">
                                                {{-- Root comment --}}
                                                <div class="flex items-start justify-between gap-3">
                                                    <div class="min-w-0 flex-1 text-sm">
                                                        <div class="flex items-baseline gap-2">
                                                            <span class="text-[var(--stone)] font-semibold">{{ $comment->user?->name ?? 'Team member' }}</span>
                                                            <span class="text-[var(--sand)] text-xs">{{ $comment->created_at->diffForHumans() }}</span>
                                                        </div>
                                                        <p class="text-[var(--sand)] mt-1.5 leading-relaxed break-words">{{ $comment->body }}</p>

                                                        {{-- Respond: like / acknowledge / reject / reply --}}
                                                        <div class="flex items-center gap-2 mt-2.5">
                                                            @foreach(\App\Models\FeedComment::REACTIONS as $emoji)
                                                                @php
                                                                    $rc = $comment->reactions->where('emoji', $emoji)->count();
                                                                    $mineR = $comment->reactions->where('emoji', $emoji)->where('user_id', auth()->id())->isNotEmpty();
                                                                @endphp
                                                                @if($this->canComment || $rc > 0)
                                                                    <button @if($this->canComment) wire:click="toggleCommentReaction({{ $comment->id }}, '{{ $emoji }}')" @endif
                                                                            wire:key="creact-{{ $comment->id }}-{{ $loop->index }}"
                                                                            class="px-2 py-0.5 rounded-full text-xs border transition {{ $mineR ? 'border-[var(--gold)] bg-[var(--gold)]/15 text-[var(--gold)]' : 'border-[var(--line)] text-[var(--sand)] hover:text-[var(--stone)]' }}">
                                                                        {{ $emoji }}@if($rc > 0) {{ $rc }}@endif
                                                                    </button>
                                                                @endif
                                                            @endforeach
                                                            @if($this->canComment)
                                                                <button wire:click="startReply({{ $comment->id }})"
                                                                        class="text-xs font-medium transition {{ $replyingTo === $comment->id ? 'text-[var(--gold)]' : 'text-[var(--sand)] hover:text-[var(--stone)]' }}">
                                                                    Reply
                                                                </button>
                                                            @endif
                                                        </div>
                                                    </div>
                                                    @if($comment->user_id === auth()->id() || $this->canPin)
                                                        <button wire:click="deleteComment({{ $comment->id }})" class="text-[var(--sand)] hover:text-red-300 text-xs shrink-0 px-1.5 py-1">✕</button>
                                                    @endif
                                                </div>

                                                {{-- Replies, indented under the root --}}
                                                @if($comment->replies->isNotEmpty())
                                                    <div class="mt-3 ml-4 pl-3 border-l-2 border-[var(--line)] space-y-3">
                                                        @foreach($comment->replies as $reply)
                                                            <div wire:key="reply-{{ $reply->id }}" class="flex items-start justify-between gap-3">
                                                                <div class="min-w-0 flex-1 text-sm">
                                                                    <div class="flex items-baseline gap-2">
                                                                        <span class="text-[var(--stone)] font-semibold">{{ $reply->user?->name ?? 'Team member' }}</span>
                                                                        <span class="text-[var(--sand)] text-xs">{{ $reply->created_at->diffForHumans() }}</span>
                                                                    </div>
                                                                    <p class="text-[var(--sand)] mt-1 leading-relaxed break-words">{{ $reply->body }}</p>
                                                                    <div class="flex items-center gap-2 mt-2">
                                                                        @foreach(\App\Models\FeedComment::REACTIONS as $emoji)
                                                                            @php
                                                                                $rrc = $reply->reactions->where('emoji', $emoji)->count();
                                                                                $mineRr = $reply->reactions->where('emoji', $emoji)->where('user_id', auth()->id())->isNotEmpty();
                                                                            @endphp
                                                                            @if($this->canComment || $rrc > 0)
                                                                                <button @if($this->canComment) wire:click="toggleCommentReaction({{ $reply->id }}, '{{ $emoji }}')" @endif
                                                                                        wire:key="rreact-{{ $reply->id }}-{{ $loop->index }}"
                                                                                        class="px-2 py-0.5 rounded-full text-xs border transition {{ $mineRr ? 'border-[var(--gold)] bg-[var(--gold)]/15 text-[var(--gold)]' : 'border-[var(--line)] text-[var(--sand)] hover:text-[var(--stone)]' }}">
                                                                                    {{ $emoji }}@if($rrc > 0) {{ $rrc }}@endif
                                                                                </button>
                                                                            @endif
                                                                        @endforeach
                                                                        @if($this->canComment)
                                                                            <button wire:click="startReply({{ $reply->id }})"
                                                                                    class="text-xs font-medium transition {{ $replyingTo === $reply->id ? 'text-[var(--gold)]' : 'text-[var(--sand)] hover:text-[var(--stone)]' }}">
                                                                                Reply
                                                                            </button>
                                                                        @endif
                                                                    </div>
                                                                </div>
                                                                @if($reply->user_id === auth()->id() || $this->canPin)
                                                                    <button wire:click="deleteComment({{ $reply->id }})" class="text-[var(--sand)] hover:text-red-300 text-xs shrink-0 px-1.5 py-1">✕</button>
                                                                @endif
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @endif

                                                {{-- Inline reply composer --}}
                                                @if($replyingTo === $comment->id || $comment->replies->contains('id', $replyingTo))
                                                    <div class="mt-3 ml-4 pl-3 flex gap-2.5">
                                                        <input type="text" wire:model="replyBody" wire:keydown.enter="addReply" placeholder="Reply…"
                                                               class="flex-1 px-3.5 py-2 bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg text-[var(--stone)] text-sm">
                                                        <button wire:click="addReply" wire:loading.attr="disabled"
                                                                class="px-4 py-2 bg-[var(--gold)] hover:bg-[var(--gold-soft)] text-[var(--ink)] rounded-lg text-sm font-semibold transition">Reply</button>
                                                    </div>
                                                    @error('replyBody') <p class="text-red-300 text-xs mt-1.5 ml-7">{{ $message }}</p> @enderror
                                                @endif
                                            </div>
                                        @empty
                                            <p class="text-[var(--sand)] text-sm">No comments yet — start the thread.</p>
                                        @endforelse
                                    </div>
                                    @if($this->canComment)
                                        <div class="flex gap-2.5 mt-4 pt-4 border-t border-[var(--line)]">
                                            <input type="text" wire:model="commentBody" wire:keydown.enter="addComment" placeholder="Add a comment…"
                                                   class="flex-1 px-3.5 py-2.5 bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg text-[var(--stone)] text-sm">
                                            <button wire:click="addComment" wire:loading.attr="disabled"
                                                    class="px-4 py-2.5 bg-[var(--gold)] hover:bg-[var(--gold-soft)] text-[var(--ink)] rounded-lg text-sm font-semibold transition">Send</button>
                                        </div>
                                        @error('commentBody') <p class="text-red-300 text-xs mt-1.5">{{ $message }}</p> @enderror
                                    @endif
                                </div>
                            @endif
                        </div>

                        {{-- Curator controls, kept out of the content column --}}
                        @if(($this->canPin && ! $item->isPinned()) || (! $item->isSystem() && ($item->user_id === auth()->id() || $this->canPin)))
                            <div class="flex items-center gap-1 shrink-0">
                                @if($this->canPin && ! $item->isPinned())
                                    <button wire:click="pin({{ $item->id }})" class="text-[var(--sand)] hover:text-[var(--stone)] text-xs px-1.5 py-1 rounded transition" title="Pin for 24 hours">📌</button>
                                @endif
                                @if(! $item->isSystem() && ($item->user_id === auth()->id() || $this->canPin))
                                    <button wire:click="deleteItem({{ $item->id }})" wire:confirm="Remove this post?" class="text-[var(--sand)] hover:text-red-300 text-xs px-1.5 py-1 rounded transition">✕</button>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        @if($stream['hasMore'])
            <div class="mt-6 text-center">
                <button wire:click="loadMore" wire:loading.attr="disabled"
                        class="px-6 py-2.5 border border-[var(--line-strong)] text-[var(--sand)] hover:text-[var(--stone)] rounded-lg text-sm transition">
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

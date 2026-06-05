<div class="min-h-screen bg-slate-900 p-6"
     x-data="{}"
     x-init="
        @this.on('notify', (event) => {
            // handled by parent notification component if present
        });
     ">
    <div class="max-w-7xl mx-auto">
        <div class="grid grid-cols-1 xl:grid-cols-[1fr_320px] gap-6 items-start">

        {{-- ── LEFT: main feed column ─────────────────────────────────── --}}
        <div class="min-w-0">

        {{-- ── Header ──────────────────────────────────────────────────── --}}
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-white">Operations Feed</h1>
                <p class="text-slate-400 text-sm mt-1">Real-time activity stream for {{ auth()->user()->currentTeam->name }}</p>
            </div>
            <button
                wire:click="openCompose"
                class="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold px-5 py-2.5 rounded-lg transition-colors"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                </svg>
                New Post
            </button>
        </div>


        {{-- ── Filter Bar ───────────────────────────────────────────────── --}}
        <div class="bg-slate-800 border border-slate-700 rounded-xl p-4 mb-6">
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                {{-- Category --}}
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Category</label>
                    <select wire:model.live="filterCategory"
                        class="w-full bg-slate-700 text-white text-sm px-3 py-2 rounded-lg border border-slate-600 focus:border-blue-500 focus:outline-none">
                        <option value="all">All</option>
                        <option value="breakdown">Breakdown</option>
                        <option value="shift_update">Shift Update</option>
                        <option value="safety_alert">Safety Alert</option>
                        <option value="production">Production</option>
                        <option value="general">General</option>
                    </select>
                </div>

                {{-- Section --}}
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Section</label>
                    <select wire:model.live="filterSection"
                        class="w-full bg-slate-700 text-white text-sm px-3 py-2 rounded-lg border border-slate-600 focus:border-blue-500 focus:outline-none">
                        <option value="all">All Sections</option>
                        @foreach ($mineAreas as $area)
                            <option value="{{ $area->id }}">{{ $area->name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Shift --}}
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Shift</label>
                    <select wire:model.live="filterShift"
                        class="w-full bg-slate-700 text-white text-sm px-3 py-2 rounded-lg border border-slate-600 focus:border-blue-500 focus:outline-none">
                        <option value="all">All Shifts</option>
                        <option value="A">Shift A</option>
                        <option value="B">Shift B</option>
                        <option value="C">Shift C</option>
                    </select>
                </div>

                {{-- Approval Status --}}
                @if ($canApprove)
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Approval</label>
                    <select wire:model.live="filterApproval"
                        class="w-full bg-slate-700 text-white text-sm px-3 py-2 rounded-lg border border-slate-600 focus:border-blue-500 focus:outline-none">
                        <option value="all">All</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
                @endif

                {{-- Date From --}}
                <div>
                    <label class="block text-xs text-slate-400 mb-1">From</label>
                    <input type="date" wire:model.live="filterDateFrom"
                        class="w-full bg-slate-700 text-white text-sm px-3 py-2 rounded-lg border border-slate-600 focus:border-blue-500 focus:outline-none">
                </div>

                {{-- Date To --}}
                <div>
                    <label class="block text-xs text-slate-400 mb-1">To</label>
                    <input type="date" wire:model.live="filterDateTo"
                        class="w-full bg-slate-700 text-white text-sm px-3 py-2 rounded-lg border border-slate-600 focus:border-blue-500 focus:outline-none">
                </div>
            </div>
        </div>

        {{-- ── Posts Timeline ───────────────────────────────────────────── --}}
        <div class="space-y-4">
            @forelse ($posts as $post)
                @php
                    $categoryMeta = [
                        'breakdown'    => ['label' => 'Breakdown',    'bg' => 'bg-red-900/50',    'border' => 'border-red-700',    'text' => 'text-red-300',    'badge' => 'bg-red-700 text-red-100'],
                        'shift_update' => ['label' => 'Shift Update', 'bg' => 'bg-amber-900/30',  'border' => 'border-amber-700',  'text' => 'text-amber-300',  'badge' => 'bg-amber-700 text-amber-100'],
                        'safety_alert' => ['label' => 'Safety Alert', 'bg' => 'bg-red-900/60',    'border' => 'border-red-500',    'text' => 'text-red-200',    'badge' => 'bg-red-600 text-white animate-pulse'],
                        'production'   => ['label' => 'Production',   'bg' => 'bg-green-900/30',  'border' => 'border-green-700',  'text' => 'text-green-300',  'badge' => 'bg-green-700 text-green-100'],
                        'general'      => ['label' => 'General',      'bg' => 'bg-slate-800',     'border' => 'border-slate-700',  'text' => 'text-slate-300',  'badge' => 'bg-slate-600 text-slate-200'],
                    ];
                    $cm = $categoryMeta[$post->category] ?? $categoryMeta['general'];
                    $approvalStatus = $post->approval?->status ?? 'pending';
                @endphp

                <div class="{{ $cm['bg'] }} border {{ $cm['border'] }} rounded-xl p-5
                    {{ $post->is_pinned ? 'ring-2 ring-yellow-400' : '' }}">

                    {{-- Pinned badge --}}
                    @if ($post->is_pinned)
                        <div class="flex items-center gap-1 text-yellow-400 text-xs font-semibold mb-2">
                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M11 3a1 1 0 10-2 0v1H7.5A1.5 1.5 0 006 5.5v2A1.5 1.5 0 007.5 9H9v5.586l-1.707 1.707A1 1 0 008 18h4a1 1 0 00.707-1.707L11 14.586V9h1.5A1.5 1.5 0 0014 7.5v-2A1.5 1.5 0 0012.5 4H11V3z"/>
                            </svg>
                            Pinned
                        </div>
                    @endif

                    {{-- Post Header --}}
                    <div class="flex items-start justify-between gap-3 mb-3">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-full bg-slate-600 flex items-center justify-center text-white font-bold text-sm shrink-0">
                                {{ strtoupper(substr($post->author->name, 0, 1)) }}
                            </div>
                            <div>
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="text-white font-semibold text-sm">{{ $post->author->name }}</span>
                                    <span class="{{ $cm['badge'] }} text-xs px-2 py-0.5 rounded-full font-medium">{{ $cm['label'] }}</span>
                                    @if ($post->priority === 'critical')
                                        <span class="bg-red-600 text-white text-xs px-2 py-0.5 rounded-full font-semibold">CRITICAL</span>
                                    @elseif ($post->priority === 'high')
                                        <span class="bg-orange-600 text-white text-xs px-2 py-0.5 rounded-full font-semibold">HIGH</span>
                                    @endif
                                </div>
                                <div class="flex items-center gap-2 mt-0.5 text-xs text-slate-400 flex-wrap">
                                    @if ($post->mineArea)
                                        <span>{{ $post->mineArea->name }}</span>
                                        <span>•</span>
                                    @endif
                                    @if ($post->shift)
                                        <span>Shift {{ $post->shift }}</span>
                                        <span>•</span>
                                    @endif
                                    <span>{{ $post->created_at->diffForHumans() }}</span>
                                </div>
                            </div>
                        </div>

                        {{-- Approval badge --}}
                        <div class="shrink-0">
                            @if ($approvalStatus === 'approved')
                                <span class="bg-green-800 text-green-200 text-xs px-2 py-1 rounded-full font-medium">✓ Approved</span>
                            @elseif ($approvalStatus === 'rejected')
                                <span class="bg-red-800 text-red-200 text-xs px-2 py-1 rounded-full font-medium">✗ Rejected</span>
                            @elseif ($canApprove)
                                <span class="bg-yellow-800 text-yellow-200 text-xs px-2 py-1 rounded-full font-medium">Pending</span>
                            @endif
                        </div>
                    </div>

                    {{-- Body --}}
                    <p class="{{ $cm['text'] }} text-sm leading-relaxed mb-3">{!! app(\App\Services\MentionParser::class)->highlight(e($post->body)) !!}</p>

                    {{-- Category meta (breakdown / shift_update fields) --}}
                    @if ($post->meta)
                        <div class="bg-black/20 rounded-lg p-3 mb-3 text-xs text-slate-300 space-y-1">
                            @foreach ($post->meta as $key => $value)
                                <div><span class="text-slate-400">{{ ucwords(str_replace('_', ' ', $key)) }}:</span> {{ $value }}</div>
                            @endforeach
                        </div>
                    @endif

                    {{-- Attachments --}}
                    @if ($post->attachments->isNotEmpty())
                        <div class="flex flex-wrap gap-2 mb-3">
                            @foreach ($post->attachments as $att)
                                @if (str_starts_with($att->file_type, 'image/'))
                                    <a href="{{ $att->url }}" target="_blank" class="block w-24 h-24 rounded-lg overflow-hidden border border-slate-600 hover:border-blue-400 transition-colors">
                                        <img src="{{ $att->url }}" alt="{{ $att->file_name }}" class="w-full h-full object-cover">
                                    </a>
                                @else
                                    <a href="{{ $att->url }}" target="_blank"
                                        class="flex items-center gap-2 bg-slate-700 hover:bg-slate-600 text-slate-200 text-xs px-3 py-2 rounded-lg transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                                        </svg>
                                        {{ $att->file_name ?? 'Attachment' }}
                                        @if($att->file_size)
                                            <span class="opacity-60">({{ $att->formattedSize() }})</span>
                                        @endif
                                    </a>
                                @endif
                            @endforeach
                        </div>
                    @endif

                    {{-- Rejection reason (if rejected and user can approve) --}}
                    @if ($approvalStatus === 'rejected' && $post->approval?->reason && $canApprove)
                        <div class="bg-red-900/30 border border-red-800 rounded-lg p-3 mb-3 text-xs text-red-300">
                            <span class="font-semibold">Rejection reason:</span> {{ $post->approval->reason }}
                        </div>
                    @endif

                    {{-- Action Bar ────────────────────────────────────────── --}}
                    <div class="flex items-center gap-4 pt-3 border-t border-white/10">

                        {{-- Acknowledge --}}
                        <button
                            wire:click="acknowledge({{ $post->id }})"
                            @if ($post->user_has_acknowledged) disabled @endif
                            class="flex items-center gap-1.5 text-xs font-medium transition-colors
                                {{ $post->user_has_acknowledged
                                    ? 'text-blue-400 cursor-default'
                                    : 'text-slate-400 hover:text-blue-400' }}"
                        >
                            <svg class="w-4 h-4" fill="{{ $post->user_has_acknowledged ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Ack ({{ $post->acknowledgement_count }})
                        </button>

                        {{-- Like --}}
                        <button
                            wire:click="toggleLike({{ $post->id }})"
                            class="flex items-center gap-1.5 text-xs font-medium transition-colors
                                {{ $post->user_has_liked
                                    ? 'text-pink-400'
                                    : 'text-slate-400 hover:text-pink-400' }}"
                        >
                            <svg class="w-4 h-4" fill="{{ $post->user_has_liked ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                            </svg>
                            {{ $post->like_count }}
                        </button>

                        {{-- Comments --}}
                        <button
                            wire:click="toggleComments({{ $post->id }})"
                            class="flex items-center gap-1.5 text-xs font-medium text-slate-400 hover:text-slate-200 transition-colors"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                            </svg>
                            {{ $post->comment_count }} {{ Str::plural('comment', $post->comment_count) }}
                        </button>

                        {{-- Approve/Reject (authorized roles) --}}
                        @if ($canApprove && $approvalStatus === 'pending')
                            <div class="ml-auto flex gap-2">
                                <button wire:click="approvePost({{ $post->id }})"
                                    class="text-xs bg-green-700 hover:bg-green-600 text-white px-3 py-1 rounded-lg font-medium transition-colors">
                                    Approve
                                </button>
                                <button wire:click="openRejectModal({{ $post->id }})"
                                    class="text-xs bg-red-800 hover:bg-red-700 text-white px-3 py-1 rounded-lg font-medium transition-colors">
                                    Reject
                                </button>
                            </div>
                        @endif

                        {{-- Admin controls: pin, override, delete ────────── --}}
                        @if (auth()->user()->hasRole('admin'))
                            <div class="ml-auto flex items-center gap-1.5">
                                {{-- Pin / Unpin --}}
                                @if ($post->is_pinned)
                                    <button wire:click="unpinPost({{ $post->id }})" title="Unpin post"
                                        class="flex items-center gap-1 text-xs text-yellow-400 hover:text-yellow-300 px-2 py-1 bg-yellow-900/30 hover:bg-yellow-900/50 rounded-lg transition-colors">
                                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path d="M11 3a1 1 0 10-2 0v1H7.5A1.5 1.5 0 006 5.5v2A1.5 1.5 0 007.5 9H9v5.586l-1.707 1.707A1 1 0 008 18h4a1 1 0 00.707-1.707L11 14.586V9h1.5A1.5 1.5 0 0014 7.5v-2A1.5 1.5 0 0012.5 4H11V3z"/></svg>
                                        Unpin
                                    </button>
                                @else
                                    <button wire:click="pinPost({{ $post->id }})" title="Pin to top"
                                        class="flex items-center gap-1 text-xs text-slate-400 hover:text-yellow-400 px-2 py-1 hover:bg-yellow-900/20 rounded-lg transition-colors">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 20 20"><path d="M11 3a1 1 0 10-2 0v1H7.5A1.5 1.5 0 006 5.5v2A1.5 1.5 0 007.5 9H9v5.586l-1.707 1.707A1 1 0 008 18h4a1 1 0 00.707-1.707L11 14.586V9h1.5A1.5 1.5 0 0014 7.5v-2A1.5 1.5 0 0012.5 4H11V3z"/></svg>
                                        Pin
                                    </button>
                                @endif

                                {{-- Override approval --}}
                                @if ($approvalStatus !== 'approved')
                                    <button wire:click="overrideApproval({{ $post->id }}, 'approved')" title="Force approve"
                                        class="text-xs text-green-400 hover:text-green-300 px-2 py-1 hover:bg-green-900/30 rounded-lg transition-colors">✓ Force</button>
                                @endif
                                @if ($approvalStatus !== 'rejected')
                                    <button wire:click="overrideApproval({{ $post->id }}, 'rejected')" title="Force reject"
                                        class="text-xs text-orange-400 hover:text-orange-300 px-2 py-1 hover:bg-orange-900/20 rounded-lg transition-colors">✗ Force</button>
                                @endif

                                {{-- Admin delete --}}
                                <button wire:click="adminDeletePost({{ $post->id }})" title="Admin delete (with audit trail)"
                                    wire:confirm="Permanently delete this post? This action is logged."
                                    class="text-xs text-red-500 hover:text-red-400 px-2 py-1 hover:bg-red-900/20 rounded-lg transition-colors">
                                    🗑 Delete
                                </button>
                            </div>
                        @endif
                    </div>

                    {{-- Comments Section ─────────────────────────────────── --}}
                    @if (in_array($post->id, $expandedComments))
                        <div class="mt-4 border-t border-white/10 pt-4 space-y-3">

                            @foreach ($this->getComments($post->id) as $comment)
                                <div class="flex gap-3">
                                    <div class="w-7 h-7 rounded-full bg-slate-600 flex items-center justify-center text-white font-bold text-xs shrink-0 mt-0.5">
                                        {{ strtoupper(substr($comment->author->name, 0, 1)) }}
                                    </div>
                                    <div class="flex-1">
                                        <div class="bg-slate-700/50 rounded-lg p-3">
                                            <div class="flex items-center gap-2 mb-1">
                                                <span class="text-white text-xs font-semibold">{{ $comment->author->name }}</span>
                                                <span class="text-slate-500 text-xs">{{ $comment->created_at->diffForHumans() }}</span>
                                                @if ($comment->is_edited)
                                                    <span class="text-slate-500 text-xs italic">(edited)</span>
                                                @endif
                                            </div>

                                            @if (isset($editingComment[$comment->id]))
                                                <div class="flex gap-2 mt-1">
                                                    <input type="text"
                                                        wire:model="editingComment.{{ $comment->id }}"
                                                        class="flex-1 bg-slate-600 text-white text-xs px-3 py-1.5 rounded-lg border border-slate-500 focus:border-blue-500 focus:outline-none">
                                                    <button wire:click="saveEditComment({{ $comment->id }})"
                                                        class="bg-blue-600 hover:bg-blue-700 text-white text-xs px-3 py-1.5 rounded-lg transition-colors">Save</button>
                                                    <button wire:click="$set('editingComment', {{ json_encode(array_diff_key($editingComment, [$comment->id => ''])) }})"
                                                        class="bg-slate-600 hover:bg-slate-500 text-white text-xs px-3 py-1.5 rounded-lg transition-colors">Cancel</button>
                                                </div>
                                            @else
                                                <p class="text-slate-300 text-xs leading-relaxed">{!! app(\App\Services\MentionParser::class)->highlight(e($comment->body)) !!}</p>
                                            @endif
                                        </div>

                                        {{-- Comment actions --}}
                                        <div class="flex items-center gap-3 mt-1 ml-1">
                                            <button wire:click="$set('replyTo.{{ $post->id }}', {{ $comment->id }})"
                                                class="text-xs text-slate-500 hover:text-slate-300 transition-colors">Reply</button>
                                            @if ($comment->author_id === auth()->id() && $comment->isEditableBy(auth()->user()))
                                                <button wire:click="startEditComment({{ $comment->id }})"
                                                    class="text-xs text-slate-500 hover:text-slate-300 transition-colors">Edit</button>
                                            @endif
                                            @if ($comment->author_id === auth()->id() || $canApprove)
                                                <button wire:click="deleteComment({{ $comment->id }})"
                                                    class="text-xs text-slate-500 hover:text-red-400 transition-colors">Delete</button>
                                            @endif
                                        </div>

                                        {{-- Nested replies --}}
                                        @foreach ($comment->replies as $reply)
                                            <div class="flex gap-3 mt-2 ml-2">
                                                <div class="w-6 h-6 rounded-full bg-slate-600 flex items-center justify-center text-white font-bold text-xs shrink-0 mt-0.5">
                                                    {{ strtoupper(substr($reply->author->name, 0, 1)) }}
                                                </div>
                                                <div class="flex-1">
                                                    <div class="bg-slate-700/40 rounded-lg p-2.5">
                                                        <div class="flex items-center gap-2 mb-1">
                                                            <span class="text-white text-xs font-semibold">{{ $reply->author->name }}</span>
                                                            <span class="text-slate-500 text-xs">{{ $reply->created_at->diffForHumans() }}</span>
                                                            @if ($reply->is_edited)
                                                                <span class="text-slate-500 text-xs italic">(edited)</span>
                                                            @endif
                                                        </div>
                                                        <p class="text-slate-300 text-xs leading-relaxed">{!! app(\App\Services\MentionParser::class)->highlight(e($reply->body)) !!}</p>
                                                    </div>
                                                    <div class="flex items-center gap-3 mt-1 ml-1">
                                                        @if ($reply->author_id === auth()->id() || $canApprove)
                                                            <button wire:click="deleteComment({{ $reply->id }})"
                                                                class="text-xs text-slate-500 hover:text-red-400 transition-colors">Delete</button>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach

                            {{-- Add Comment / Reply Input --}}
                            <div class="flex gap-3 mt-2">
                                <div class="w-7 h-7 rounded-full bg-blue-700 flex items-center justify-center text-white font-bold text-xs shrink-0 mt-0.5">
                                    {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                                </div>
                                <div class="flex-1">
                                    @if (isset($replyTo[$post->id]) && $replyTo[$post->id])
                                        <div class="text-xs text-blue-400 mb-1 flex items-center gap-1">
                                            Replying to comment
                                            <button wire:click="$set('replyTo.{{ $post->id }}', null)" class="text-slate-500 hover:text-red-400 ml-1">✕</button>
                                        </div>
                                    @endif
                                    <div class="flex gap-2">
                                        <input
                                            type="text"
                                            wire:model="commentBody.{{ $post->id }}"
                                            wire:keydown.enter="submitComment({{ $post->id }})"
                                            placeholder="Write a comment…"
                                            class="flex-1 bg-slate-700 text-white text-sm px-3 py-2 rounded-lg border border-slate-600 focus:border-blue-500 focus:outline-none"
                                        >
                                        <button
                                            wire:click="submitComment({{ $post->id }})"
                                            class="bg-blue-600 hover:bg-blue-700 text-white text-sm px-4 py-2 rounded-lg transition-colors font-medium">
                                            Send
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            @empty
                <div class="text-center py-16 text-slate-500">
                    <svg class="w-12 h-12 mx-auto mb-3 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/>
                    </svg>
                    <p class="text-sm">No posts yet. Be the first to post.</p>
                </div>
            @endforelse
        </div>

        {{-- Pagination --}}
        <div class="mt-6">
            {{ $posts->links() }}
        </div>
        </div>{{-- end left column --}}

        {{-- ── RIGHT: Daily Production Stats card ───────────────────── --}}
        <div class="xl:sticky xl:top-6 space-y-4">

            {{-- Stats card --}}
            <div class="bg-slate-800 border border-slate-700 rounded-xl overflow-hidden shadow-lg">
                {{-- Card header --}}
                <div class="px-5 py-4 border-b border-slate-700 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div class="w-2 h-2 bg-emerald-400 rounded-full animate-pulse"></div>
                        <span class="text-white font-semibold text-sm">Daily Production</span>
                    </div>
                    <div class="flex items-center gap-1.5 text-xs text-slate-400">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        As of {{ $dailyStats['as_of'] }}
                    </div>
                </div>

                {{-- Shift badge --}}
                <div class="px-5 pt-4">
                    <span class="inline-flex items-center gap-1.5 text-xs font-medium px-2.5 py-1 rounded-full
                        {{ $dailyStats['current_shift'] === 'Day' ? 'bg-amber-500/20 text-amber-300 border border-amber-500/30' : 'bg-indigo-500/20 text-indigo-300 border border-indigo-500/30' }}">
                        @if($dailyStats['current_shift'] === 'Day')
                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 2a8 8 0 100 16A8 8 0 0010 2zm0 14a6 6 0 110-12 6 6 0 010 12z" clip-rule="evenodd"/><path d="M10 4a1 1 0 011 1v1a1 1 0 11-2 0V5a1 1 0 011-1zM10 15a1 1 0 011 1v.5a1 1 0 11-2 0V16a1 1 0 011-1z"/></svg>
                        @else
                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"/></svg>
                        @endif
                        {{ $dailyStats['current_shift'] }} Shift
                    </span>
                </div>

                {{-- Main metrics grid --}}
                <div class="p-5 grid grid-cols-2 gap-3">
                    {{-- Loads --}}
                    <div class="bg-slate-700/50 rounded-lg p-3">
                        <p class="text-xs text-slate-400 mb-1">Loads</p>
                        <p class="text-2xl font-bold text-white">{{ number_format($dailyStats['total_loads']) }}</p>
                        <p class="text-xs text-slate-500 mt-0.5">{{ $dailyStats['shift_loads'] }} this shift</p>
                    </div>

                    {{-- Cycles --}}
                    <div class="bg-slate-700/50 rounded-lg p-3">
                        <p class="text-xs text-slate-400 mb-1">Cycles</p>
                        <p class="text-2xl font-bold text-white">{{ number_format($dailyStats['total_cycles']) }}</p>
                        <p class="text-xs text-slate-500 mt-0.5">sensor tracked</p>
                    </div>

                    {{-- Tonnage --}}
                    <div class="bg-slate-700/50 rounded-lg p-3 col-span-2">
                        <p class="text-xs text-slate-400 mb-1">Total Tonnage</p>
                        <div class="flex items-end justify-between">
                            <p class="text-2xl font-bold text-white">{{ number_format($dailyStats['total_tonnage'], 1) }}
                                <span class="text-sm font-normal text-slate-400">t</span>
                            </p>
                            @if($dailyStats['total_target'] > 0)
                                <span class="text-xs text-slate-400">target {{ number_format($dailyStats['total_target'], 0) }} t</span>
                            @endif
                        </div>
                        @if($dailyStats['total_target'] > 0)
                            <div class="mt-2 h-1.5 bg-slate-600 rounded-full overflow-hidden">
                                <div class="h-1.5 rounded-full transition-all duration-700
                                    {{ ($dailyStats['achievement'] ?? 0) >= 100 ? 'bg-emerald-500' : (($dailyStats['achievement'] ?? 0) >= 75 ? 'bg-amber-500' : 'bg-red-500') }}"
                                    style="width: {{ min(100, (int) ($dailyStats['achievement'] ?? 0)) }}%">
                                </div>
                            </div>
                            <p class="text-xs mt-1
                                {{ ($dailyStats['achievement'] ?? 0) >= 100 ? 'text-emerald-400' : (($dailyStats['achievement'] ?? 0) >= 75 ? 'text-amber-400' : 'text-red-400') }}">
                                {{ $dailyStats['achievement'] }}% of target
                            </p>
                        @endif
                    </div>
                </div>

                {{-- Shift tonnage --}}
                @if($dailyStats['shift_tonnage'] > 0)
                <div class="px-5 pb-3">
                    <div class="flex items-center justify-between text-xs text-slate-400 mb-1">
                        <span>{{ $dailyStats['current_shift'] }} shift tonnage</span>
                        <span class="font-medium text-slate-200">{{ number_format($dailyStats['shift_tonnage'], 1) }} t</span>
                    </div>
                </div>
                @endif

                {{-- Best performing trucks --}}
                @if(count($dailyStats['best_trucks']) > 0)
                <div class="border-t border-slate-700 px-5 py-4">
                    <p class="text-xs font-semibold text-slate-300 uppercase tracking-wide mb-3">Top Trucks Today</p>
                    <div class="space-y-2">
                        @foreach($dailyStats['best_trucks'] as $i => $truck)
                        <div class="flex items-center gap-3">
                            <span class="flex-shrink-0 w-5 h-5 rounded-full text-xs font-bold flex items-center justify-center
                                {{ $i === 0 ? 'bg-amber-500 text-slate-900' : ($i === 1 ? 'bg-slate-400 text-slate-900' : ($i === 2 ? 'bg-amber-700 text-white' : 'bg-slate-700 text-slate-300')) }}">
                                {{ $i + 1 }}
                            </span>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-medium text-white truncate">{{ $truck['name'] }}</p>
                                <p class="text-xs text-slate-500">{{ $truck['loads'] }} loads</p>
                            </div>
                            <span class="flex-shrink-0 text-xs font-semibold
                                {{ $i === 0 ? 'text-amber-400' : 'text-slate-300' }}">
                                {{ number_format($truck['tonnage'], 1) }} t
                            </span>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- Footer link --}}
                <div class="border-t border-slate-700 px-5 py-3">
                    <a href="{{ route('production') }}" class="flex items-center justify-center gap-1.5 text-xs text-blue-400 hover:text-blue-300 transition-colors font-medium">
                        View full production dashboard
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                </div>
            </div>

        </div>{{-- end right column --}}
        </div>{{-- end grid --}}
    </div>{{-- end max-w-7xl --}}

    {{-- ═══════════════════════════════════════════════════════════════════════
         Compose Modal
    ════════════════════════════════════════════════════════════════════════ --}}
    @if ($showCompose)
        <div class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 flex items-start justify-center p-4 pt-16"
             x-data x-on:keydown.escape.window="$wire.closeCompose()">
            <div class="bg-slate-800 border border-slate-700 rounded-2xl w-full max-w-lg shadow-2xl">

                {{-- Modal Header --}}
                <div class="flex items-center justify-between p-5 border-b border-slate-700">
                    <h2 class="text-white font-bold text-lg">New Post</h2>
                    <button wire:click="closeCompose" class="text-slate-400 hover:text-white transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="p-5 space-y-4">

                    {{-- Step 1: Category selector --}}
                    <div>
                        <label class="block text-sm text-slate-400 mb-2">Category <span class="text-red-400">*</span></label>
                        <div class="grid grid-cols-3 gap-2">
                            @foreach ([
                                'breakdown'    => ['Breakdown',    'text-red-300'],
                                'shift_update' => ['Shift Update', 'text-amber-300'],
                                'safety_alert' => ['Safety Alert', 'text-red-200'],
                                'production'   => ['Production',   'text-green-300'],
                                'general'      => ['General',      'text-slate-300'],
                            ] as $key => [$label, $textColor])
                                <button
                                    type="button"
                                    wire:click="$set('composeCategory', '{{ $key }}')"
                                    class="text-xs font-medium py-2 px-3 rounded-lg border transition-colors
                                        {{ $composeCategory === $key
                                            ? 'bg-blue-600 border-blue-500 text-white'
                                            : 'bg-slate-700 border-slate-600 ' . $textColor . ' hover:border-slate-500' }}"
                                >
                                    {{ $label }}
                                </button>
                            @endforeach
                        </div>
                        @error('composeCategory') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Section + Shift --}}
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Section</label>
                            <select wire:model="composeMineAreaId"
                                class="w-full bg-slate-700 text-white text-sm px-3 py-2 rounded-lg border border-slate-600 focus:border-blue-500 focus:outline-none">
                                <option value="">Select section</option>
                                @foreach ($mineAreas as $area)
                                    <option value="{{ $area->id }}">{{ $area->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Shift</label>
                            <select wire:model="composeShift"
                                class="w-full bg-slate-700 text-white text-sm px-3 py-2 rounded-lg border border-slate-600 focus:border-blue-500 focus:outline-none">
                                <option value="">No shift</option>
                                <option value="A">Shift A</option>
                                <option value="B">Shift B</option>
                                <option value="C">Shift C</option>
                            </select>
                        </div>
                    </div>

                    {{-- Priority (hidden for safety_alert which is auto-critical) --}}
                    @if ($composeCategory !== 'safety_alert')
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Priority</label>
                            <select wire:model="composePriority"
                                class="w-full bg-slate-700 text-white text-sm px-3 py-2 rounded-lg border border-slate-600 focus:border-blue-500 focus:outline-none">
                                <option value="normal">Normal</option>
                                <option value="high">High</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>
                    @else
                        <div class="bg-red-900/30 border border-red-700 rounded-lg p-3 text-xs text-red-300 font-medium">
                            Safety alerts are automatically marked <strong>CRITICAL</strong>.
                        </div>
                    @endif

                    {{-- Category-specific meta fields --}}
                    @if ($composeCategory === 'breakdown')
                        <div class="space-y-3 border border-slate-600 rounded-lg p-3">
                            <p class="text-slate-400 text-xs font-semibold uppercase tracking-wide">Breakdown Details</p>
                            <input type="text" wire:model="composeMeta.machine_id" placeholder="Machine ID *"
                                class="w-full bg-slate-700 text-white text-sm px-3 py-2 rounded-lg border border-slate-600 focus:border-blue-500 focus:outline-none">
                            <input type="text" wire:model="composeMeta.failure_type" placeholder="Failure type *"
                                class="w-full bg-slate-700 text-white text-sm px-3 py-2 rounded-lg border border-slate-600 focus:border-blue-500 focus:outline-none">
                            <input type="text" wire:model="composeMeta.estimated_downtime" placeholder="Estimated downtime (e.g. 4 hours) *"
                                class="w-full bg-slate-700 text-white text-sm px-3 py-2 rounded-lg border border-slate-600 focus:border-blue-500 focus:outline-none">
                        </div>
                    @endif

                    @if ($composeCategory === 'shift_update')
                        <div class="space-y-3 border border-slate-600 rounded-lg p-3">
                            <p class="text-slate-400 text-xs font-semibold uppercase tracking-wide">Shift Update Details</p>
                            <input type="number" min="0" step="0.1" wire:model="composeMeta.loads_per_hour" placeholder="Loads per hour *"
                                class="w-full bg-slate-700 text-white text-sm px-3 py-2 rounded-lg border border-slate-600 focus:border-blue-500 focus:outline-none">
                            <input type="number" min="0" wire:model="composeMeta.tonnage" placeholder="Tonnage"
                                class="w-full bg-slate-700 text-white text-sm px-3 py-2 rounded-lg border border-slate-600 focus:border-blue-500 focus:outline-none">
                            <input type="number" min="0" wire:model="composeMeta.headcount" placeholder="Headcount"
                                class="w-full bg-slate-700 text-white text-sm px-3 py-2 rounded-lg border border-slate-600 focus:border-blue-500 focus:outline-none">
                        </div>
                    @endif

                    {{-- Template picker --}}
                    @if ($composeCategory && count($categoryTemplates) > 0)
                        <div class="border border-slate-600 rounded-lg p-3 space-y-2">
                            <p class="text-xs text-slate-400 font-semibold uppercase tracking-wide">Use a Template</p>
                            <div class="space-y-1 max-h-36 overflow-y-auto">
                                @foreach ($categoryTemplates as $tpl)
                                    <button
                                        type="button"
                                        wire:click="applyTemplate({{ $tpl['id'] }})"
                                        class="w-full text-left text-xs text-slate-300 hover:text-white bg-slate-700 hover:bg-slate-600 px-3 py-2 rounded-lg transition-colors"
                                    >
                                        {{ $tpl['title'] }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Body --}}
                    <div>
                        <label class="block text-sm text-slate-400 mb-2">Message <span class="text-red-400">*</span></label>
                        <textarea
                            wire:model="composeBody"
                            rows="4"
                            placeholder="What's happening on site?"
                            class="w-full bg-slate-700 text-white text-sm px-4 py-3 rounded-lg border border-slate-600 focus:border-blue-500 focus:outline-none resize-none"
                        ></textarea>
                        @error('composeBody') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Attachments --}}
                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Attachments (optional)</label>
                        <input type="file"
                            wire:model="composeAttachments"
                            multiple
                            accept="image/*,audio/*,.pdf"
                            class="w-full text-slate-300 text-xs file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-slate-600 file:text-slate-200 file:text-xs hover:file:bg-slate-500 file:cursor-pointer">
                        <div wire:loading wire:target="composeAttachments" class="text-xs text-blue-400 mt-1">Uploading…</div>
                    </div>
                </div>

                {{-- Modal Footer --}}
                <div class="flex gap-3 p-5 pt-0">
                    <button wire:click="closeCompose"
                        class="flex-1 bg-slate-700 hover:bg-slate-600 text-white font-medium py-2.5 rounded-lg transition-colors">
                        Cancel
                    </button>
                    <button wire:click="submitPost" wire:loading.attr="disabled"
                        class="flex-1 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white font-semibold py-2.5 rounded-lg transition-colors">
                        <span wire:loading.remove wire:target="submitPost">Publish</span>
                        <span wire:loading wire:target="submitPost">Publishing…</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- ═══════════════════════════════════════════════════════════════════════
         Reject Modal
    ════════════════════════════════════════════════════════════════════════ --}}
    @if ($showRejectModal)
        <div class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 flex items-center justify-center p-4"
             x-data x-on:keydown.escape.window="$wire.set('showRejectModal', false)">
            <div class="bg-slate-800 border border-slate-700 rounded-2xl w-full max-w-md shadow-2xl p-6">
                <h2 class="text-white font-bold text-lg mb-1">Reject Post</h2>
                <p class="text-slate-400 text-sm mb-4">Provide a reason so the author can revise.</p>

                <textarea
                    wire:model="rejectReason"
                    rows="4"
                    placeholder="Reason for rejection…"
                    class="w-full bg-slate-700 text-white text-sm px-4 py-3 rounded-lg border border-slate-600 focus:border-red-500 focus:outline-none resize-none mb-1"
                ></textarea>
                @error('rejectReason') <p class="text-red-400 text-xs mb-3">{{ $message }}</p> @enderror

                <div class="flex gap-3 mt-4">
                    <button wire:click="$set('showRejectModal', false)"
                        class="flex-1 bg-slate-700 hover:bg-slate-600 text-white font-medium py-2.5 rounded-lg transition-colors">
                        Cancel
                    </button>
                    <button wire:click="submitRejection"
                        class="flex-1 bg-red-700 hover:bg-red-600 text-white font-semibold py-2.5 rounded-lg transition-colors">
                        Confirm Rejection
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>

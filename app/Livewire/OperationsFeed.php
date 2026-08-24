<?php

namespace App\Livewire;

use App\Models\FeedComment;
use App\Models\FeedItem;
use App\Models\Machine;
use App\Services\Feed\FeedPublisher;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The Mine Operations Feed page: what is happening across the mine.
 *
 * A consumer, never a calculator -- every row was published by the module
 * that owns the underlying data. This component filters, paginates and
 * renders; the only thing it writes is a human post.
 *
 * "Load more" pagination is keyed on the oldest loaded id rather than page
 * numbers: new items arrive constantly at the top, and offset pagination
 * would duplicate rows every time something was published mid-scroll.
 */
class OperationsFeed extends Component
{
    public string $category = '';

    public string $timeWindow = '';   // '', today, 24h, week

    public string $machineFilter = '';

    public string $search = '';

    /** How many items are shown; grows as the reader scrolls. */
    public int $limit = 25;

    public bool $showComposer = false;

    public string $postTitle = '';

    public string $postBody = '';

    public string $postCategory = FeedItem::CATEGORY_ANNOUNCEMENT;

    public function mount(): void
    {
        $this->authorize('viewAny', FeedItem::class);
    }

    public function updatedCategory(): void
    {
        $this->limit = 25;
    }

    public function updatedTimeWindow(): void
    {
        $this->limit = 25;
    }

    public function updatedMachineFilter(): void
    {
        $this->limit = 25;
    }

    public function updatedSearch(): void
    {
        $this->limit = 25;
    }

    /**
     * Poked from the browser when the team channel announces a new item;
     * re-rendering re-runs the queries, so nothing is trusted from the wire.
     */
    #[On('feed-refresh')]
    public function refreshFeed(): void
    {
        // Rendering is the refresh.
    }

    public function loadMore(): void
    {
        $this->limit += 25;
    }

    /**
     * Pinned announcements, always on top regardless of filters.
     *
     * @return Collection<int, FeedItem>
     */
    public function getPinnedProperty(): Collection
    {
        $query = FeedItem::query();
        $query->whereNotNull('pinned_until');
        $query->where('pinned_until', '>', now());
        $query->orderByDesc('pinned_until');
        $query->with(['user', 'machine', 'operator']);

        return $query->get();
    }

    /**
     * The stream, filtered. One page of `limit` rows plus a flag for more.
     *
     * @return array{items: Collection<int, FeedItem>, hasMore: bool}
     */
    public function getStreamProperty(): array
    {
        $query = FeedItem::query();
        $this->applyFilters($query);
        $query->orderByDesc('occurred_at');
        $query->orderByDesc('id');
        $query->with(['user', 'machine', 'operator', 'reactions']);
        $query->withCount('comments');
        $query->limit($this->limit + 1);

        $items = $query->get();
        $hasMore = $items->count() > $this->limit;

        return [
            'items' => $items->take($this->limit)->values(),
            'hasMore' => $hasMore,
        ];
    }

    /**
     * @param  Builder<FeedItem>  $query
     */
    private function applyFilters(Builder $query): void
    {
        if ($this->category !== '') {
            $query->where('category', $this->category);
        }

        if ($this->machineFilter !== '' && is_numeric($this->machineFilter)) {
            $query->where('machine_id', (int) $this->machineFilter);
        }

        match ($this->timeWindow) {
            'today' => $query->where('occurred_at', '>=', now()->startOfDay()),
            '24h' => $query->where('occurred_at', '>=', now()->subDay()),
            'week' => $query->where('occurred_at', '>=', now()->subWeek()),
            default => null,
        };

        if ($this->search !== '') {
            // Bounded LIKE over the two indexed-adjacent text columns of an
            // already team-scoped, limit-capped query -- not a full scan of
            // anything. A dedicated search index earns its place only when a
            // team's feed history outgrows this.
            $term = '%'.strtolower($this->search).'%';
            $query->where(function (Builder $q) use ($term): void {
                $q->whereRaw('LOWER(title) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(body) LIKE ?', [$term]);
            });
        }
    }

    /**
     * Machines for the filter dropdown.
     *
     * @return Collection<int, Machine>
     */
    public function getMachinesProperty(): Collection
    {
        $query = Machine::query();
        $query->orderBy('name');

        return $query->get(['id', 'name']);
    }

    public function getCanPostProperty(): bool
    {
        return auth()->user()?->can('create', FeedItem::class) ?? false;
    }

    public function getCanPinProperty(): bool
    {
        return auth()->user()?->hasPermission('pin_feed') ?? false;
    }

    /** Which item's comment thread is open, if any. */
    public ?int $openCommentsFor = null;

    public string $commentBody = '';

    public function getCanCommentProperty(): bool
    {
        return auth()->user()?->hasPermission('comment_feed') ?? false;
    }

    public function toggleComments(int $itemId): void
    {
        $this->openCommentsFor = $this->openCommentsFor === $itemId ? null : $itemId;
        $this->commentBody = '';
        $this->replyingTo = null;
        $this->replyBody = '';
    }

    /**
     * The open thread's comments, oldest first.
     *
     * @return Collection<int, FeedComment>
     */
    public function getOpenCommentsProperty(): Collection
    {
        if ($this->openCommentsFor === null) {
            return new Collection;
        }

        $item = FeedItem::query()->find($this->openCommentsFor);

        if ($item === null) {
            return new Collection;
        }

        $query = $item->comments()->getQuery();
        $query->whereNull('parent_id');
        $query->with(['user', 'reactions', 'replies' => function (Relation $q): void {
            $q->getQuery()->orderBy('id');
        }, 'replies.user', 'replies.reactions']);
        $query->orderBy('id');

        return $query->get();
    }

    /** The comment being replied to, if the composer is in reply mode. */
    public ?int $replyingTo = null;

    public string $replyBody = '';

    public function startReply(int $commentId): void
    {
        $this->replyingTo = $this->replyingTo === $commentId ? null : $commentId;
        $this->replyBody = '';
    }

    public function addComment(): void
    {
        if (! $this->getCanCommentProperty() || $this->openCommentsFor === null) {
            abort(403);
        }

        $this->validate(['commentBody' => 'required|string|max:2000']);

        // Team scope on the lookup: a foreign item id 404s before anything
        // is written.
        $item = FeedItem::query()->findOrFail($this->openCommentsFor);

        $item->comments()->create([
            'team_id' => $item->team_id,
            'user_id' => auth()->id(),
            'body' => $this->commentBody,
        ]);

        $this->commentBody = '';
    }

    public function addReply(): void
    {
        if (! $this->getCanCommentProperty() || $this->replyingTo === null) {
            abort(403);
        }

        $this->validate(['replyBody' => 'required|string|max:2000']);

        $parent = FeedComment::query()->findOrFail($this->replyingTo);

        // One level deep by design: replying to a reply attaches to the
        // root comment, which keeps the thread readable on a phone.
        $rootId = $parent->parent_id ?? $parent->id;

        FeedComment::create([
            'team_id' => $parent->team_id,
            'feed_item_id' => $parent->feed_item_id,
            'parent_id' => $rootId,
            'user_id' => auth()->id(),
            'body' => $this->replyBody,
        ]);

        $this->replyingTo = null;
        $this->replyBody = '';
    }

    public function toggleCommentReaction(int $commentId, string $emoji): void
    {
        if (! $this->getCanCommentProperty() || ! in_array($emoji, FeedComment::REACTIONS, true)) {
            abort(403);
        }

        $comment = FeedComment::query()->findOrFail($commentId);

        $existing = $comment->reactions()
            ->where('user_id', auth()->id())
            ->where('emoji', $emoji)
            ->first();

        if ($existing !== null) {
            $existing->delete();

            return;
        }

        $comment->reactions()->create([
            'team_id' => $comment->team_id,
            'user_id' => auth()->id(),
            'emoji' => $emoji,
        ]);
    }

    public function deleteComment(int $commentId): void
    {
        $comment = FeedComment::query()->findOrFail($commentId);

        // Your own comment, or a curator's clean-up.
        if ($comment->user_id !== auth()->id() && ! $this->getCanPinProperty()) {
            abort(403);
        }

        $comment->delete();
    }

    public function toggleReaction(int $itemId, string $emoji): void
    {
        if (! $this->getCanCommentProperty() || ! in_array($emoji, FeedItem::REACTIONS, true)) {
            abort(403);
        }

        $item = FeedItem::query()->findOrFail($itemId);

        $existing = $item->reactions()
            ->where('user_id', auth()->id())
            ->where('emoji', $emoji)
            ->first();

        if ($existing !== null) {
            $existing->delete();

            return;
        }

        $item->reactions()->create([
            'team_id' => $item->team_id,
            'user_id' => auth()->id(),
            'emoji' => $emoji,
        ]);
    }

    public function post(FeedPublisher $publisher): void
    {
        $this->authorize('create', FeedItem::class);

        $this->validate([
            'postTitle' => 'required|string|max:255',
            'postBody' => 'nullable|string|max:4000',
            'postCategory' => ['required', Rule::in(array_keys(FeedItem::CATEGORIES))],
        ]);

        $publisher->publish([
            'team_id' => auth()->user()?->current_team_id,
            'source' => FeedItem::SOURCE_USER,
            'category' => $this->postCategory,
            'type' => 'announcement',
            'title' => $this->postTitle,
            'body' => $this->postBody !== '' ? $this->postBody : null,
            'user_id' => auth()->id(),
        ]);

        $this->showComposer = false;
        $this->reset(['postTitle', 'postBody']);
        $this->postCategory = FeedItem::CATEGORY_ANNOUNCEMENT;
    }

    /**
     * Pin an item for a number of hours (default: until unpinned tomorrow).
     */
    public function pin(int $itemId, int $hours = 24): void
    {
        $item = FeedItem::query()->findOrFail($itemId);
        $this->authorize('pin', $item);

        $hours = max(1, min($hours, 24 * 14)); // two weeks is the ceiling

        $item->update([
            'pinned_until' => now()->addHours($hours),
            'pinned_by' => auth()->id(),
        ]);
    }

    public function unpin(int $itemId): void
    {
        $item = FeedItem::query()->findOrFail($itemId);
        $this->authorize('pin', $item);

        $item->update(['pinned_until' => null, 'pinned_by' => null]);
    }

    public function deleteItem(int $itemId): void
    {
        $item = FeedItem::query()->findOrFail($itemId);
        $this->authorize('delete', $item);

        $item->delete();
    }

    public function render(): View
    {
        return view('livewire.operations-feed');
    }
}

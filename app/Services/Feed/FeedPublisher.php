<?php

namespace App\Services\Feed;

use App\Events\FeedItemPosted;
use App\Models\FeedItem;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * The one way anything enters the Mine Operations Feed.
 *
 * Modules publish through here rather than writing feed_items rows, so the
 * two rules the feed lives by are enforced in one place:
 *
 *  - Deduplication. Integrations deliver the same event more than once; a
 *    publisher passing a dedupe_key gets exactly one row per key per team,
 *    enforced by the database unique index rather than a read-then-write
 *    race. The second insert is swallowed, not errored -- being delivered
 *    twice is normal, not exceptional.
 *
 *  - Honest time. occurred_at is when the underlying event happened. It
 *    defaults to now() only when the caller genuinely means "this moment"
 *    (a person posting); event normalisers must pass the event's own time.
 *
 * Every successful publish broadcasts on the team's existing private
 * channel, so the page updates without polling and without a new channel
 * to authorise.
 */
class FeedPublisher
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function publish(array $attributes): ?FeedItem
    {
        if (! array_key_exists('occurred_at', $attributes)) {
            $attributes['occurred_at'] = now();
        }

        if (! array_key_exists('source', $attributes)) {
            $attributes['source'] = FeedItem::SOURCE_SYSTEM;
        }

        try {
            $item = FeedItem::create($attributes);
        } catch (UniqueConstraintViolationException) {
            // Same dedupe_key, same team: this event is already in the feed.
            return null;
        }

        FeedItemPosted::dispatch($item);

        return $item;
    }
}

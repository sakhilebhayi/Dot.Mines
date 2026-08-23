<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

/**
 * One list envelope for the whole API.
 *
 * List endpoints previously returned three different shapes -- some
 * `{data, pagination}`, some the raw Laravel paginator (`current_page`,
 * `first_page_url`, `links`, ...), some `{data, meta}` -- so no integrator
 * could write a single response handler. This helper is the single source of
 * that shape; route it through here rather than hand-building an envelope.
 *
 * The shape matches Laravel's own Resource Collection output
 * (`{data, links, meta}`), which keeps it familiar to anyone who has used a
 * Laravel API and means adopting API Resources later is additive rather than
 * another breaking change.
 */
final class ApiResponse
{
    /**
     * Standard paginated list response.
     *
     * The paginator is generic over its item type: psalm's Collection and
     * paginator templates are INVARIANT, so a concrete
     * `LengthAwarePaginator<int, Machine>` is not accepted by a
     * `LengthAwarePaginator<int, mixed>` parameter. Templating the method
     * lets every caller pass its own model paginator.
     *
     * @template TItem
     *
     * @param  LengthAwarePaginator<int, TItem>  $paginator
     * @param  array<string, mixed>  $extra  Additional top-level keys (e.g. summary stats)
     */
    public static function paginated(LengthAwarePaginator $paginator, array $extra = []): JsonResponse
    {
        return response()->json(array_merge([
            'data' => $paginator->items(),
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'per_page' => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ], $extra));
    }

    /**
     * Standard response for a list that is deliberately not paginated
     * (bounded result sets: "due today", "unread", short histories).
     * Same envelope so clients read `data` and `meta.total` everywhere.
     *
     * @param  array<array-key, mixed>  $items
     * @param  array<string, mixed>  $extra
     */
    public static function collection(array $items, array $extra = []): JsonResponse
    {
        $values = array_values($items);

        return response()->json(array_merge([
            'data' => $values,
            'meta' => [
                'total' => count($values),
            ],
        ], $extra));
    }
}

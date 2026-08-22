<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Machine;
use App\Models\MineArea;
use App\Models\Notification;
use App\Models\ProductionRecord;
use App\Models\SyncTombstone;
use App\Models\User;
use App\Services\Sync\SyncSequence;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Incremental sync endpoint (hybrid spec Slice 1): returns only rows whose
 * sync_version advanced past the client's cursor, plus tombstones for
 * deletions, so the browser's IndexedDB cache stays fresh without ever
 * re-downloading unchanged data (brief §6-§7).
 *
 * Tenancy is derived exclusively from the authenticated user (brief §9):
 * every query filters by the session team server-side, and nothing the
 * client sends can widen that. When a scope overflows the page cap, the
 * returned cursor is the smallest last-included version across truncated
 * scopes -- clients repeat until has_more is false; re-received rows are
 * harmless because the client upserts by id.
 */
class SyncController extends Controller
{
    /** @var array<string, string> sync scope => table name (used for tombstone filtering) */
    private const SCOPE_TABLES = [
        'fleet' => 'machines',
        'production' => 'production_records',
        'notifications' => 'notifications',
        'reference' => 'mine_areas',
    ];

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'since' => ['sometimes', 'integer', 'min:0'],
            'scopes' => ['required', 'string'],
        ]);

        $scopes = array_values(array_unique(array_filter(explode(',', $validated['scopes']))));

        foreach ($scopes as $scope) {
            if (! array_key_exists($scope, self::SCOPE_TABLES)) {
                abort(422, "Unknown sync scope [{$scope}].");
            }
        }

        /** @var User $user */
        $user = $request->user();
        $teamId = (int) $user->current_team_id;
        $since = (int) ($validated['since'] ?? 0);
        $pageSize = (int) config('sync.page_size', 500);

        $changes = [];
        $truncatedCursors = [];

        foreach ($scopes as $scope) {
            [$rows, $lastIncluded, $truncated] = $this->changesFor($scope, $teamId, $since, $pageSize);
            $changes[$scope] = $rows;

            if ($truncated && $lastIncluded !== null) {
                $truncatedCursors[] = $lastIncluded;
            }
        }

        $deleted = $this->tombstonesFor($scopes, $teamId, $since, $pageSize);

        $version = $truncatedCursors === []
            ? max($since, SyncSequence::current())
            : min($truncatedCursors);

        return response()->json([
            'version' => $version,
            'server_time' => now()->toIso8601String(),
            'changes' => (object) $changes,
            'deleted' => $deleted,
            'has_more' => $truncatedCursors !== [],
        ]);
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: int|null, 2: bool}
     */
    private function changesFor(string $scope, int $teamId, int $since, int $pageSize): array
    {
        $query = match ($scope) {
            'fleet' => Machine::query()->with('latestMetric'),
            'production' => ProductionRecord::query(),
            'notifications' => Notification::query(),
            default => MineArea::query(),
        };

        /**
         * @var Collection<int, Model> $models
         *
         * @psalm-suppress UnnecessaryVarAnnotation -- limit() erases builder
         * generics for phpstan; psalm disagrees the annotation is needed.
         */
        $models = $query
            ->where('team_id', $teamId)
            ->where('sync_version', '>', $since)
            ->orderBy('sync_version')
            ->limit($pageSize + 1)
            ->get();

        $truncated = $models->count() > $pageSize;
        $models = $models->take($pageSize);

        $rows = array_values($models->map(fn (Model $model): array => $this->serialize($model))->all());
        $last = $models->last();

        return [$rows, $last instanceof Model ? (int) $last->getAttribute('sync_version') : null, $truncated];
    }

    /**
     * Minimal payloads on purpose (brief §22): the cache holds what the UI
     * renders, never credentials, financials, or full telemetry history.
     *
     * @return array<string, mixed>
     */
    private function serialize(Model $model): array
    {
        return match (true) {
            $model instanceof Machine => [
                'id' => $model->id,
                'name' => $model->name,
                'machine_type' => $model->machine_type,
                'status' => $model->status,
                'allocation_state' => $model->allocation_state,
                'latitude' => $model->latestMetric?->latitude,
                'longitude' => $model->latestMetric?->longitude,
                'fuel_level' => $model->latestMetric?->fuel_level,
                'engine_hours' => $model->latestMetric?->total_hours,
                'payload' => $model->latestMetric?->load_weight,
                'last_seen_at' => $model->latestMetric?->created_at?->toIso8601String(),
                'sync_version' => $model->sync_version,
            ],
            $model instanceof ProductionRecord => [
                'id' => $model->id,
                'machine_id' => $model->machine_id,
                'mine_area_id' => $model->mine_area_id,
                'record_date' => $model->record_date instanceof Carbon
                    ? $model->record_date->format('Y-m-d')
                    : $model->record_date,
                'shift' => $model->shift,
                'quantity_produced' => $model->quantity_produced,
                'unit' => $model->unit,
                'status' => $model->status,
                'sync_version' => $model->sync_version,
            ],
            $model instanceof Notification => [
                'id' => $model->id,
                'type' => $model->type,
                'title' => $model->title,
                'message' => $model->message,
                'alert_level' => $model->alert_level,
                'is_read' => (bool) $model->is_read,
                'created_at' => $model->created_at?->toIso8601String(),
                'sync_version' => $model->sync_version,
            ],
            $model instanceof MineArea => [
                'id' => $model->id,
                'name' => $model->name,
                'status' => $model->status,
                'center_latitude' => $model->center_latitude,
                'center_longitude' => $model->center_longitude,
                'coordinates' => $model->coordinates,
                'sync_version' => $model->sync_version,
            ],
            default => [],
        };
    }

    /**
     * @param  list<string>  $scopes
     * @return list<array<string, mixed>>
     */
    private function tombstonesFor(array $scopes, int $teamId, int $since, int $pageSize): array
    {
        $tables = array_map(fn (string $scope): string => self::SCOPE_TABLES[$scope], $scopes);

        /**
         * @var Collection<int, SyncTombstone> $tombstones
         *
         * @psalm-suppress UnnecessaryVarAnnotation -- limit() erases builder
         * generics for phpstan; psalm disagrees the annotation is needed.
         */
        $tombstones = SyncTombstone::query()
            ->where('team_id', $teamId)
            ->whereIn('entity_type', $tables)
            ->where('sync_version', '>', $since)
            ->orderBy('sync_version')
            ->limit($pageSize)
            ->get();

        return array_values($tombstones
            ->map(fn (SyncTombstone $tombstone): array => [
                'entity_type' => $tombstone->entity_type,
                'entity_id' => $tombstone->entity_id,
                'sync_version' => $tombstone->sync_version,
            ])
            ->all());
    }
}

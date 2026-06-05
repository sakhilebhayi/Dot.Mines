<?php

namespace App\Jobs;

use App\Models\MineArea;
use App\Models\ProductionRecord;
use App\Models\ProductionTarget;
use App\Models\Shift;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Permanently removes soft-deleted records past the SOFT_DELETE_GRACE_DAYS grace period (default 30).
 */
class PurgeExpiredSoftDeletesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    /** @var array<int> */
    public array $backoff = [60, 300];

    public function handle(): void
    {
        $graceDays = (int) env('SOFT_DELETE_GRACE_DAYS', 30);
        $cutoff = now()->subDays($graceDays);

        $totals = [];

        $models = [
            ProductionRecord::class,
            ProductionTarget::class,
            Shift::class,
            MineArea::class,
        ];

        foreach ($models as $model) {
            $count = $model::onlyTrashed()
                ->where('deleted_at', '<', $cutoff)
                ->forceDelete();

            $totals[class_basename($model)] = $count;
        }

        Log::info('PurgeExpiredSoftDeletesJob completed', [
            'cutoff' => $cutoff->toDateString(),
            'purged' => $totals,
        ]);
    }
}

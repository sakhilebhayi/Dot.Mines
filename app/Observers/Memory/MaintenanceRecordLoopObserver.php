<?php

namespace App\Observers\Memory;

use App\Models\MaintenanceRecord;
use App\Services\Memory\MemoryLoopRecorder;
use Throwable;

class MaintenanceRecordLoopObserver
{
    public function __construct(private readonly MemoryLoopRecorder $recorder) {}

    public function created(MaintenanceRecord $record): void
    {
        try {
            $this->recorder->maintenanceBooked($record);
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function updated(MaintenanceRecord $record): void
    {
        if (! $record->wasChanged('status') || $record->status !== 'completed') {
            return;
        }

        try {
            $this->recorder->maintenanceCompleted($record);
        } catch (Throwable $e) {
            report($e);
        }
    }
}

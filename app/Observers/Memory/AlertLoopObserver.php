<?php

namespace App\Observers\Memory;

use App\Models\Alert;
use App\Services\Memory\MemoryLoopRecorder;
use Throwable;

/**
 * Both the screen and the API change an alert's status through the
 * model, so the model is where the loop record is derived.
 */
class AlertLoopObserver
{
    public function __construct(private readonly MemoryLoopRecorder $recorder) {}

    public function updated(Alert $alert): void
    {
        if (! $alert->wasChanged('status')) {
            return;
        }

        try {
            match ($alert->status) {
                'acknowledged' => $this->recorder->alertAcknowledged($alert),
                'resolved' => $this->recorder->alertResolved($alert),
                default => null,
            };
        } catch (Throwable $e) {
            report($e);
        }
    }
}

<?php

namespace App\Listeners;

use App\Events\AlertTriggered;
use App\Events\MachineOffline;
use App\Events\OperatorAssigned;
use App\Events\OperatorUnassigned;
use App\Services\Memory\MemoryLoopRecorder;
use Illuminate\Events\Dispatcher;
use Throwable;

/**
 * Domain events that belong in the ecosystem intelligence loop. Runs
 * inline (an outbox insert) and never throws into the dispatching code.
 */
class MemoryLoopSubscriber
{
    public function __construct(private readonly MemoryLoopRecorder $recorder) {}

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(AlertTriggered::class, [$this, 'onAlertTriggered']);
        $events->listen(MachineOffline::class, [$this, 'onMachineOffline']);
        $events->listen(OperatorAssigned::class, [$this, 'onOperatorAssigned']);
        $events->listen(OperatorUnassigned::class, [$this, 'onOperatorUnassigned']);
    }

    public function onAlertTriggered(AlertTriggered $event): void
    {
        $this->guard(fn () => $this->recorder->alertTriggered($event->alert));
    }

    public function onMachineOffline(MachineOffline $event): void
    {
        $this->guard(fn () => $this->recorder->machineOffline($event->machine, $event->reason));
    }

    public function onOperatorAssigned(OperatorAssigned $event): void
    {
        $this->guard(fn () => $this->recorder->operatorAssigned($event->assignment));
    }

    public function onOperatorUnassigned(OperatorUnassigned $event): void
    {
        $this->guard(fn () => $this->recorder->operatorUnassigned($event->assignment));
    }

    /** @param callable(): void $record */
    private function guard(callable $record): void
    {
        try {
            $record();
        } catch (Throwable $e) {
            report($e);
        }
    }
}

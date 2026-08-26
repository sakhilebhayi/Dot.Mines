<?php

namespace App\Listeners;

use App\Services\Guardian\ErrorCounter;
use Illuminate\Log\Events\MessageLogged;

/**
 * Feed 2 of the guardian's error counter: every error-level-or-worse log
 * write, so failures that code catches and merely logs still move the
 * error_rate check (see ErrorCounter's docblock for the dedupe against the
 * report-hook feed). Auto-discovered by Laravel's event discovery.
 */
class CountGuardianLogErrors
{
    public function __construct(private readonly ErrorCounter $counter) {}

    public function handle(MessageLogged $event): void
    {
        $this->counter->recordLogRecord($event->level, $event->message, $event->context);
    }
}

<?php

namespace App\Services\Guardian\Contracts;

use App\Services\Guardian\CheckResult;

/**
 * One machine-readable production health signal.
 *
 * Implementations must be side-effect free and fast (the whole report is
 * served synchronously to the Dot.Brain guardian on every poll), and must
 * express "cannot tell" as CheckResult::unknown() rather than throwing --
 * GuardianHealthReport treats an escaped exception as unknown anyway so a
 * single broken probe can never take the whole report down.
 */
interface GuardianCheck
{
    /** Stable snake_case identifier used as the check's key in the report. */
    public function key(): string;

    public function run(): CheckResult;
}

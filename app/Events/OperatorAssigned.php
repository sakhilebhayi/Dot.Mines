<?php

namespace App\Events;

use App\Models\OperatorMachineAssignment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * An operator was put on a machine through OperatorAssignmentService (the
 * fleet page, the operator page or the API — every path ends here).
 */
class OperatorAssigned
{
    use Dispatchable, SerializesModels;

    public function __construct(public OperatorMachineAssignment $assignment) {}
}

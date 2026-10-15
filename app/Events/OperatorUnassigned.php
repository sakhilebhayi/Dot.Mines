<?php

namespace App\Events;

use App\Models\OperatorMachineAssignment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * An operator's stretch on a machine ended, by hand or because another
 * operator relieved them.
 */
class OperatorUnassigned
{
    use Dispatchable, SerializesModels;

    public function __construct(public OperatorMachineAssignment $assignment) {}
}

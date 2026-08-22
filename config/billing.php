<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trial machine allowance
    |--------------------------------------------------------------------------
    | Teams with no purchased allocations (empty ledger) may run this many
    | machines in total, across all classes. Purchasing any allocation
    | switches the team to purchased-capacity rules.
    */
    'trial_machine_allowance' => env('BILLING_TRIAL_MACHINE_ALLOWANCE', 2),

    /*
    |--------------------------------------------------------------------------
    | Machine class mapping
    |--------------------------------------------------------------------------
    | Allocations are priced per class. Every machine_type maps to exactly
    | one class; unmapped types fall back to 'adt' (the cheaper class --
    | never silently overcharge a customer for an unrecognised type).
    */
    'machine_class_fallback' => 'adt',

    'machine_class_map' => [
        'adt' => 'adt',
        'excavator' => 'heavy',
        'digger' => 'heavy',
        'dozer' => 'heavy',
        'bulldozer' => 'heavy',
        'loader' => 'heavy',
        'grader' => 'heavy',
    ],
];

<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Compliance warning windows
    |--------------------------------------------------------------------------
    |
    | How many days before a credential expires the system starts warning.
    | The 30-day warning is the one the business asked for and is treated as
    | mandatory; the shorter ones escalate as the date approaches and can be
    | turned off per site by editing this list.
    |
    | Kept in config rather than hardcoded because "how much notice do we need
    | to renew a licence" is an operational question that differs by mine and
    | by country, not a fact about the code.
    |
    */

    'warning_days' => [30, 14, 7],

    /*
    |--------------------------------------------------------------------------
    | What "compliant" means
    |--------------------------------------------------------------------------
    |
    | The credentials an operator must hold, and hold unexpired, before the
    | platform will call them compliant. Removing an entry here stops it being
    | required without deleting the data already captured against it.
    |
    | 'licence'  -- a qualification covering the equipment being assigned
    | 'medical'  -- a current occupational medical, fit for duty
    | 'induction'-- site induction training
    |
    */

    'required' => [
        'licence' => true,
        'medical' => true,
        'induction' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Training that counts as site induction
    |--------------------------------------------------------------------------
    |
    | Matched case-insensitively against the training category so a site can
    | name the course whatever it names it.
    |
    */

    'induction_category' => 'site_induction',

];

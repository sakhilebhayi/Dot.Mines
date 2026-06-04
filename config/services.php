<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'sentry' => [
        'dsn' => env('SENTRY_DSN'),
    ],

    'paystack' => [
        'secret' => env('PAYSTACK_SECRET_KEY'),
        'public_key' => env('PAYSTACK_PUBLIC_KEY'),
        'callback_url' => env('PAYSTACK_CALLBACK_URL', env('APP_URL').'/billing/success'),
        'webhook_url' => env('PAYSTACK_WEBHOOK_URL', env('APP_URL').'/webhooks/paystack'),

        // Paystack Plan Codes for subscription tiers
        'basic_monthly_plan_code' => env('PAYSTACK_BASIC_MONTHLY_PLAN_CODE'),
        'basic_yearly_plan_code' => env('PAYSTACK_BASIC_YEARLY_PLAN_CODE'),
        'pro_monthly_plan_code' => env('PAYSTACK_PRO_MONTHLY_PLAN_CODE'),
        'pro_yearly_plan_code' => env('PAYSTACK_PRO_YEARLY_PLAN_CODE'),
        'enterprise_monthly_plan_code' => env('PAYSTACK_ENTERPRISE_MONTHLY_PLAN_CODE'),
        'enterprise_yearly_plan_code' => env('PAYSTACK_ENTERPRISE_YEARLY_PLAN_CODE'),
    ],

];

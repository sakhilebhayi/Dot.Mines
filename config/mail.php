<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Mailer
    |--------------------------------------------------------------------------
    |
    | This option controls the default mailer that is used to send all email
    | messages unless another mailer is explicitly specified when sending
    | the message. All additional mailers can be configured within the
    | "mailers" array. Examples of each type of mailer are provided.
    |
    */

    // In production, set MAIL_MAILER=failover so emails continue to send
    // via AWS SES if the primary SMTP server is unavailable.
    'default' => env('MAIL_MAILER', 'log'),

    /*
    |--------------------------------------------------------------------------
    | Mailer Configurations
    |--------------------------------------------------------------------------
    |
    | Here you may configure all of the mailers used by your application plus
    | their respective settings. Several examples have been configured for
    | you and you are free to add your own as your application requires.
    |
    | Laravel supports a variety of mail "transport" drivers that can be used
    | when delivering an email. You may specify which one you're using for
    | your mailers below. You may also add additional mailers if needed.
    |
    | Supported: "smtp", "sendmail", "mailgun", "ses", "ses-v2",
    |            "postmark", "resend", "log", "array",
    |            "failover", "roundrobin"
    |
    */

    'mailers' => [

        'smtp' => [
            'transport' => 'smtp',
            // MAIL_SCHEME controls the encryption mode.
            // Use 'smtps' for port 465 (implicit TLS) or omit/null for port 587 (STARTTLS via stream options).
            // MAIL_ENCRYPTION is a legacy Laravel <11 alias — do not use; MAIL_SCHEME is the canonical key.
            'scheme' => env('MAIL_SCHEME'),
            'url' => env('MAIL_URL'),
            'host' => env('MAIL_HOST', '127.0.0.1'),
            'port' => env('MAIL_PORT', 2525),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            // MAIL_TIMEOUT: prevents queue workers from hanging indefinitely on SMTP failure.
            // Production default is 30 seconds. Set to null to disable (not recommended).
            'timeout' => env('MAIL_TIMEOUT', 30),
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        ],

        'ses' => [
            'transport' => 'ses',
        ],

        'postmark' => [
            'transport' => 'postmark',
            // 'message_stream_id' => env('POSTMARK_MESSAGE_STREAM_ID'),
            // 'client' => [
            //     'timeout' => 5,
            // ],
        ],

        'resend' => [
            'transport' => 'resend',
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs -i'),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

        'failover' => [
            'transport' => 'failover',
            // Primary: custom SMTP (mines.infodot.co.za)
            // Secondary: AWS SES — requires AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY, AWS_DEFAULT_REGION
            // IMPORTANT: do NOT use 'log' as a secondary — that causes silent message loss.
            'mailers' => [
                'smtp',
                'ses',
            ],
            'retry_after' => 60,
        ],

        'roundrobin' => [
            'transport' => 'roundrobin',
            'mailers' => [
                'ses',
                'postmark',
            ],
            'retry_after' => 60,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Global "From" Address
    |--------------------------------------------------------------------------
    |
    | You may wish for all emails sent by your application to be sent from
    | the same address. Here you may specify a name and address that is
    | used globally for all emails that are sent by your application.
    |
    */

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'info@mines.infodot.co.za'),
        'name' => env('MAIL_FROM_NAME', 'Mines Platform'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Per-Purpose From Addresses
    |--------------------------------------------------------------------------
    |
    | Different parts of the application send mail from dedicated mailboxes to
    | make routing and filtering easier for recipients.
    |
    */
    'addresses' => [
        'info' => env('MAIL_FROM_ADDRESS', 'info@mines.infodot.co.za'),
        'support' => env('MAIL_FROM_ADDRESS_SUPPORT', 'support@mines.infodot.co.za'),
        'billing' => env('MAIL_FROM_ADDRESS_BILLING', 'billing@mines.infodot.co.za'),
        'privacy' => env('MAIL_FROM_ADDRESS_PRIVACY', 'privacy@mines.infodot.co.za'),
        'legal' => env('MAIL_FROM_ADDRESS_LEGAL', 'legal@mines.infodot.co.za'),
    ],

];

<?php

namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\LogRecord;

/**
 * Log channel tap that adds a processor to redact sensitive keys in log records.
 */
class RedactSensitiveData
{
    /**
     * Invoke the tap.
     *
     * Laravel passes the channel's Illuminate\Log\Logger wrapper here, not the
     * underlying Monolog\Logger — reach the Monolog instance via getLogger()
     * to register the processor. Monolog 3.x passes an immutable LogRecord
     * value object to processors rather than a plain array.
     *
     * @return void
     */
    public function __invoke(Logger $logger)
    {
        $monolog = $logger->getLogger();

        if (! $monolog instanceof \Monolog\Logger) {
            return;
        }

        $monolog->pushProcessor(function (LogRecord $record) {
            $configured = config('logging_redaction.keys', []);
            $defaults = [
                'password', 'pass', 'pwd', 'secret', 'token', 'access_token', 'refresh_token',
                'api_key', 'apikey', 'auth', 'authorization', 'ssn', 'credit_card',
                'card_number', 'private_key', 'aws_secret', 'aws_secret_access_key', 'db_password',
                // Additional common service keys
                'sentry_auth_token', 'sentry_dsn', 'sentry_dsn_url', 'aws_access_key_id', 'aws_access_key',
                'sentry_dsn_public', 'aws_session_token', 'aws_session',
                'stripe_secret', 'stripe_token', 'stripe_key', 'stripe_api_key', 'stripe_publishable_key',
                'paystack_secret', 'paystack_secret_key', 'paystack_key', 'paystack_token',
                'pusher_key', 'pusher_secret', 'pusher_app_id', 'mailgun_api_key', 'sendgrid_api_key',
                'twilio_auth_token', 'database_url',
            ];

            $sensitiveKeys = is_array($configured) && count($configured) > 0
                ? array_merge($defaults, $configured)
                : $defaults;

            // normalize to lowercase for comparisons
            $sensitiveKeys = array_map('strtolower', $sensitiveKeys);

            $redact = function ($value) use (&$redact, $sensitiveKeys) {
                if (is_array($value)) {
                    foreach ($value as $k => $v) {
                        // If key looks sensitive, replace with placeholder
                        if (in_array(strtolower((string) $k), $sensitiveKeys, true)) {
                            $value[$k] = '[REDACTED]';
                        } else {
                            $value[$k] = $redact($v);
                        }
                    }

                    return $value;
                }

                if (is_string($value)) {
                    // redact common inline patterns
                    $value = preg_replace('/(password|pwd|pass|api_key|apikey|token|access_token)=([^&\s,;]+)/i', '$1=[REDACTED]', $value) ?? $value;
                    $value = preg_replace('/Authorization:\s*Bearer\s+([^\s,;]+)/i', 'Authorization: Bearer [REDACTED]', $value) ?? $value;

                    return $value;
                }

                return $value;
            };

            $message = $record->message;
            if ($message !== '') {
                $message = $redact($message);
            }

            return $record->with(
                message: $message,
                context: $redact($record->context),
                extra: $redact($record->extra),
            );
        });
    }

    /**
     * Public helper to redact arbitrary values (useful for tests and reuse).
     *
     * @param  mixed  $value
     * @param  list<string>  $additionalKeys
     * @return mixed
     */
    public static function redactValue($value, array $additionalKeys = [])
    {
        $defaults = [
            'password', 'pass', 'pwd', 'secret', 'token', 'access_token', 'refresh_token',
            'api_key', 'apikey', 'auth', 'authorization', 'ssn', 'credit_card',
            'card_number', 'private_key', 'aws_secret', 'aws_secret_access_key', 'db_password',
            'sentry_auth_token', 'sentry_dsn', 'sentry_dsn_url', 'aws_access_key_id', 'aws_access_key',
            'stripe_secret', 'stripe_token', 'stripe_key', 'stripe_api_key', 'stripe_publishable_key',
            'paystack_secret', 'paystack_secret_key', 'paystack_key', 'paystack_token',
            'pusher_key', 'pusher_secret', 'pusher_app_id', 'mailgun_api_key', 'sendgrid_api_key',
            'twilio_auth_token', 'database_url',
        ];
        $sensitiveKeys = array_map('strtolower', array_merge($defaults, $additionalKeys));

        $redact = function ($v) use (&$redact, $sensitiveKeys) {
            if (is_array($v)) {
                foreach ($v as $k => $val) {
                    if (in_array(strtolower((string) $k), $sensitiveKeys, true)) {
                        $v[$k] = '[REDACTED]';
                    } else {
                        $v[$k] = $redact($val);
                    }
                }

                return $v;
            }
            if (is_string($v)) {
                $v = preg_replace('/(password|pwd|pass|api_key|apikey|token|access_token)=([^&\s,;]+)/i', '$1=[REDACTED]', $v) ?? $v;
                $v = preg_replace('/Authorization:\s*Bearer\s+([^\s,;]+)/i', 'Authorization: Bearer [REDACTED]', $v) ?? $v;

                return $v;
            }

            return $v;
        };

        return $redact($value);
    }
}

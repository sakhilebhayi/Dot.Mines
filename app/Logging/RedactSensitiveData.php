<?php

namespace App\Logging;

use Monolog\Logger;
use Monolog\LogRecord;

/**
 * Monolog tap that adds a processor to redact sensitive keys in log records.
 */
class RedactSensitiveData
{
    /**
     * Invoke the tap.
     *
     * @return void
     */
    public function __invoke(Logger $logger)
    {
        $logger->pushProcessor(function (LogRecord $record) use (&$defaults): LogRecord {
            $configured = config('logging_redaction.keys', []);
            $defaults = [
                'password', 'pass', 'pwd', 'secret', 'token', 'access_token', 'refresh_token',
                'api_key', 'apikey', 'auth', 'authorization', 'ssn', 'credit_card',
                'card_number', 'private_key', 'aws_secret', 'aws_secret_access_key', 'db_password',
                // Additional common service keys
                'sentry_auth_token', 'sentry_dsn', 'sentry_dsn_url', 'aws_access_key_id', 'aws_access_key',
                'sentry_dsn_public', 'aws_session_token', 'aws_session',
                'stripe_secret', 'stripe_token', 'stripe_key', 'stripe_api_key', 'stripe_publishable_key',
                'pusher_key', 'pusher_secret', 'pusher_app_id', 'mailgun_api_key', 'sendgrid_api_key',
                'twilio_auth_token', 'database_url',
            ];

            $sensitiveKeys = is_array($configured) && count($configured) > 0
                ? array_merge($defaults, $configured)
                : $defaults;

            // normalize to lowercase for comparisons
            $sensitiveKeys = array_map('strtolower', $sensitiveKeys);

            $redact = function (mixed $value) use (&$redact, $sensitiveKeys): mixed {
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
                    $value = (string) preg_replace('/(password|pwd|pass|api_key|apikey|token|access_token)=([^&\s,;]+)/i', '$1=[REDACTED]', $value);
                    $value = (string) preg_replace('/Authorization:\s*Bearer\s+([^\s,;]+)/i', 'Authorization: Bearer [REDACTED]', $value);

                    return $value;
                }

                return $value;
            };

            /** @var array<array-key, mixed> $context */
            $context = $redact($record->context);
            /** @var array<array-key, mixed> $extra */
            $extra = $redact($record->extra);
            $message = (string) $redact($record->message);

            return $record->with(context: $context, extra: $extra, message: $message);
        });
    }

    /**
     * Public helper to redact arbitrary values (useful for tests and reuse).
     *
     * @param  array<string>  $additionalKeys
     */
    public static function redactValue(mixed $value, array $additionalKeys = []): mixed
    {
        $defaults = [
            'password', 'pass', 'pwd', 'secret', 'token', 'access_token', 'refresh_token',
            'api_key', 'apikey', 'auth', 'authorization', 'ssn', 'credit_card',
            'card_number', 'private_key', 'aws_secret', 'aws_secret_access_key', 'db_password',
            'sentry_auth_token', 'sentry_dsn', 'sentry_dsn_url', 'aws_access_key_id', 'aws_access_key',
            'stripe_secret', 'stripe_token', 'stripe_key', 'stripe_api_key', 'stripe_publishable_key',
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
                $v = (string) preg_replace('/(password|pwd|pass|api_key|apikey|token|access_token)=([^&\s,;]+)/i', '$1=[REDACTED]', $v);
                $v = (string) preg_replace('/Authorization:\s*Bearer\s+([^\s,;]+)/i', 'Authorization: Bearer [REDACTED]', $v);

                return $v;
            }

            return $v;
        };

        return $redact($value);
    }
}

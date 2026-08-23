<?php

namespace App\Services\Webhooks;

/**
 * Signs an outbound webhook so the receiver can prove it came from us.
 *
 * The signature covers the timestamp AND the body, together. Signing the body
 * alone lets anyone who ever captured one request replay it forever; with the
 * timestamp inside the signed string, a receiver that rejects old timestamps
 * gets replay protection it can actually rely on, because changing the
 * timestamp invalidates the signature.
 *
 * Header format (the same shape Stripe and GitHub settled on, so it is
 * familiar and the verification snippet is short):
 *
 *   X-Mines-Signature: t=1755950400,v1=9f86d081...
 *
 * Receivers should compare with a constant-time comparison -- the docs show
 * exactly that. There is deliberately no verify() helper here: the tests
 * re-implement verification the way a receiver has to, from the header and
 * the secret alone, so what we publish is what is exercised rather than our
 * own helper agreeing with itself.
 */
class WebhookSignature
{
    public const HEADER = 'X-Mines-Signature';

    public static function header(string $payload, string $secret, ?int $timestamp = null): string
    {
        $timestamp ??= time();

        return 't='.$timestamp.',v1='.self::hash($payload, $secret, $timestamp);
    }

    public static function hash(string $payload, string $secret, int $timestamp): string
    {
        return hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
    }

    /**
     * A new signing secret. Shown to the user once, then only ever compared
     * against.
     */
    public static function newSecret(): string
    {
        return 'whsec_'.bin2hex(random_bytes(24));
    }
}

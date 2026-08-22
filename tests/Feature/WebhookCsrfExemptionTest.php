<?php

namespace Tests\Feature;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Guards the CSRF exemption for webhook endpoints. The ordinary feature-test
 * client cannot cover this (ValidateCsrfTokens skips itself in unit tests),
 * which is exactly how the missing exemption stayed invisible until a live
 * unsigned request returned 419 in production.
 */
class WebhookCsrfExemptionTest extends TestCase
{
    public function test_webhook_paths_are_exempt_from_csrf(): void
    {
        $middleware = app(VerifyCsrfToken::class);

        $method = new \ReflectionMethod($middleware, 'inExceptArray');

        $this->assertTrue(
            $method->invoke($middleware, Request::create('/webhooks/paystack', 'POST')),
            'Paystack signs webhooks with HMAC, not a session: /webhooks/* must bypass CSRF or every real delivery 419s.',
        );

        $this->assertFalse(
            $method->invoke($middleware, Request::create('/billing', 'POST')),
            'The exemption must stay scoped to webhooks.',
        );
    }
}

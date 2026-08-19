<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Production-readiness slice 1: session cookies and CORS.
 *
 * The framework's unpublished CORS default was allowed_origins: ['*'] --
 * any website's JavaScript could call the API. config/cors.php now locks
 * origins to APP_URL (+ explicit CORS_ALLOWED_ORIGINS). Session cookie
 * flags are asserted on real responses so a config regression (or an
 * .env.example drift like the SameSite=lax it used to ship) fails CI.
 */
class SessionCookieCorsHardeningTest extends TestCase
{
    public function test_cors_configuration_never_allows_the_wildcard_origin(): void
    {
        $origins = config('cors.allowed_origins');

        $this->assertNotEmpty($origins);
        $this->assertNotContains('*', $origins, 'Wildcard CORS lets any site script against the API.');
    }

    public function test_cross_origin_preflight_from_an_unknown_origin_is_not_granted(): void
    {
        $response = $this->call('OPTIONS', '/api/machines', [], [], [], [
            'HTTP_ORIGIN' => 'https://evil.example',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
        ]);

        // With a single fixed origin configured, the CORS layer may emit
        // that origin unconditionally -- the browser then rejects the
        // mismatch. The vulnerable shapes are '*' or REFLECTING the
        // requesting origin; assert neither ever happens.
        $grant = $response->headers->get('Access-Control-Allow-Origin');
        $this->assertNotSame('*', $grant, 'Wildcard grant must never be emitted.');
        $this->assertNotSame('https://evil.example', $grant, 'The requesting origin must never be reflected back.');
    }

    public function test_cross_origin_preflight_from_the_configured_origin_is_granted(): void
    {
        $allowed = config('cors.allowed_origins')[0];

        $response = $this->call('OPTIONS', '/api/machines', [], [], [], [
            'HTTP_ORIGIN' => $allowed,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
        ]);

        $this->assertSame($allowed, $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_session_cookie_carries_the_hardened_flags(): void
    {
        $response = $this->get('/');

        $sessionCookie = collect($response->headers->getCookies())
            ->first(fn ($cookie) => $cookie->getName() === config('session.cookie'));

        $this->assertNotNull($sessionCookie, 'Expected a session cookie on the response.');
        $this->assertTrue($sessionCookie->isHttpOnly(), 'Session cookie must be HttpOnly.');
        $this->assertTrue($sessionCookie->isSecure(), 'Session cookie must be Secure.');
        $this->assertSame('strict', strtolower((string) $sessionCookie->getSameSite()), 'Session cookie must be SameSite=strict.');
    }

    public function test_env_template_matches_the_hardened_session_defaults(): void
    {
        $template = (string) file_get_contents(base_path('.env.example'));

        $this->assertStringContainsString('SESSION_SAME_SITE=strict', $template, '.env.example used to ship lax, silently downgrading fresh deploys.');
        $this->assertStringContainsString('SESSION_SECURE_COOKIE=true', $template);
        $this->assertStringContainsString('SESSION_HTTP_ONLY=true', $template);
        $this->assertStringContainsString('CORS_ALLOWED_ORIGINS', $template);
    }
}

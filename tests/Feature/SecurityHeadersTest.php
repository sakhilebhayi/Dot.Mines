<?php

namespace Tests\Feature;

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(SecurityHeaders::class)->get('/__test-security-headers', static fn () => response('ok'));
    }

    public function test_x_frame_options_is_deny(): void
    {
        $this->get('/__test-security-headers')->assertHeader('X-Frame-Options', 'DENY');
    }

    public function test_x_content_type_options_is_nosniff(): void
    {
        $this->get('/__test-security-headers')->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_x_xss_protection_is_disabled(): void
    {
        $this->get('/__test-security-headers')->assertHeader('X-XSS-Protection', '0');
    }

    public function test_referrer_policy(): void
    {
        $this->get('/__test-security-headers')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_permissions_policy_blocks_geolocation(): void
    {
        $response = $this->get('/__test-security-headers');
        $response->assertHeader('Permissions-Policy');
        $policy = $response->headers->get('Permissions-Policy');
        $this->assertStringContainsString('geolocation=()', $policy);
        $this->assertStringNotContainsString('geolocation=(self)', $policy);
    }

    public function test_cross_origin_opener_policy(): void
    {
        $this->get('/__test-security-headers')
            ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin');
    }

    public function test_cross_origin_resource_policy(): void
    {
        $this->get('/__test-security-headers')
            ->assertHeader('Cross-Origin-Resource-Policy', 'same-origin');
    }

    public function test_x_dns_prefetch_control_is_off(): void
    {
        $this->get('/__test-security-headers')
            ->assertHeader('X-DNS-Prefetch-Control', 'off');
    }

    public function test_csp_is_report_only_outside_production(): void
    {
        $response = $this->get('/__test-security-headers');

        $response->assertHeaderMissing('Content-Security-Policy');
        $response->assertHeader('Content-Security-Policy-Report-Only');
    }

    public function test_csp_contains_required_directives(): void
    {
        $csp = $this->get('/__test-security-headers')
            ->headers->get('Content-Security-Policy-Report-Only');

        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
    }

    public function test_csp_nonce_is_unique_per_request(): void
    {
        $first = $this->get('/__test-security-headers')
            ->headers->get('Content-Security-Policy-Report-Only');
        $second = $this->get('/__test-security-headers')
            ->headers->get('Content-Security-Policy-Report-Only');

        preg_match("/'nonce-([a-f0-9]+)'/", (string) $first, $m1);
        preg_match("/'nonce-([a-f0-9]+)'/", (string) $second, $m2);

        $this->assertNotEmpty($m1[1] ?? '', 'CSP must contain a nonce');
        $this->assertNotSame($m1[1], $m2[1] ?? '', 'Each request must get a unique nonce');
    }

    public function test_hsts_absent_outside_production(): void
    {
        $this->get('/__test-security-headers')
            ->assertHeaderMissing('Strict-Transport-Security');
    }
}

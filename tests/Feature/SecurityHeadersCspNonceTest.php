<?php

namespace Tests\Feature;

use Tests\TestCase;

class SecurityHeadersCspNonceTest extends TestCase
{
    public function test_csp_header_nonce_matches_the_nonce_rendered_in_the_page(): void
    {
        $response = $this->get('/');

        $response->assertOk();

        $header = $response->headers->get('Content-Security-Policy')
            ?? $response->headers->get('Content-Security-Policy-Report-Only');

        $this->assertNotNull($header, 'Expected a CSP header on the response.');

        // Pull the nonce out of the script-src directive.
        $this->assertMatchesRegularExpression("/script-src[^;]*'nonce-([a-f0-9]+)'/", $header);
        preg_match("/script-src[^;]*'nonce-([a-f0-9]+)'/", $header, $matches);
        $nonce = $matches[1];

        // The same nonce must appear on the inline <style>/<script> tags rendered in the
        // page — this is what previously broke, since the nonce used to be generated
        // AFTER the view had already rendered.
        $response->assertSee('nonce="'.$nonce.'"', false);
    }

    public function test_style_src_attr_directive_permits_inline_style_attribute_mutations(): void
    {
        $response = $this->get('/');

        $header = $response->headers->get('Content-Security-Policy')
            ?? $response->headers->get('Content-Security-Policy-Report-Only');

        // Alpine/Livewire mutate style="" attributes at runtime; nonces don't cover that
        // vector, so it needs its own narrowly-scoped directive.
        $this->assertStringContainsString("style-src-attr 'self' 'unsafe-inline'", $header);
    }
}

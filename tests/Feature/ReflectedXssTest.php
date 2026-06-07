<?php

namespace Tests\Feature;

use Tests\TestCase;

class ReflectedXssTest extends TestCase
{
    public function test_common_endpoints_do_not_reflect_script_payloads_unescaped()
    {
        $payload = '<script>alert("xss")</script>';

        $endpoints = [
            '/',
            '/reports',
            '/integrations',
            '/pages/capabilities',
        ];

        foreach ($endpoints as $ep) {
            $resp = $this->get($ep.'?q='.urlencode($payload));
            $content = $resp->getContent();
            $this->assertStringNotContainsString($payload, $content, "Endpoint {$ep} reflected raw script payload");
        }
    }

    public function test_endpoints_do_not_reflect_html_injection_payloads()
    {
        $payload = '"><img src=x onerror=alert(1)>';

        $endpoints = ['/', '/reports'];

        foreach ($endpoints as $ep) {
            $resp = $this->get($ep.'?q='.urlencode($payload));
            $this->assertStringNotContainsString($payload, $resp->getContent(), "Endpoint {$ep} reflected HTML injection payload");
        }
    }

    public function test_health_check_endpoint_returns_json_without_reflecting_input()
    {
        $payload = '<script>pwned</script>';

        $resp = $this->getJson('/health?q='.urlencode($payload));
        $resp->assertStatus(200);
        $this->assertStringNotContainsString($payload, $resp->getContent());
    }
}

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
}

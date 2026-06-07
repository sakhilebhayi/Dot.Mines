<?php

namespace Tests\Unit;

use App\Logging\RedactSensitiveData;
use PHPUnit\Framework\TestCase;

class LogRedactionTest extends TestCase
{
    public function test_redacts_array_keys_and_nested_values()
    {
        $input = [
            'user' => [
                'email' => 'dev@example.com',
                'password' => 'supersecret',
                'tokens' => [
                    'access_token' => 'abcd1234',
                ],
            ],
            'headers' => [
                'Authorization' => 'Bearer somelongtokenvalue',
            ],
            'message' => 'api_key=sk_test_12345&other=ok',
        ];

        $out = RedactSensitiveData::redactValue($input);

        $this->assertEquals('[REDACTED]', $out['user']['password']);
        $this->assertEquals('[REDACTED]', $out['user']['tokens']['access_token']);
        // Authorization header is redacted entirely
        $this->assertEquals('[REDACTED]', $out['headers']['Authorization']);
        $this->assertStringContainsString('api_key=[REDACTED]', $out['message']);
    }

    public function test_additional_key_configuration_applies()
    {
        $input = ['custom_secret' => 's3cr3t'];
        $out = RedactSensitiveData::redactValue($input, ['custom_secret']);
        $this->assertEquals('[REDACTED]', $out['custom_secret']);
    }
}

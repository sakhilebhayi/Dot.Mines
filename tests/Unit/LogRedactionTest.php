<?php

namespace Tests\Unit;

use App\Logging\RedactSensitiveData;
use Illuminate\Log\Logger;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger as MonologLogger;
use Tests\TestCase;

class LogRedactionTest extends TestCase
{
    public function test_tap_invoke_registers_a_processor_that_redacts_log_records()
    {
        // The tap receives Laravel's Illuminate\Log\Logger wrapper, not a raw
        // Monolog\Logger — this is what previously threw a TypeError and
        // silently fell back to Laravel's emergency logger on every request.
        $handler = new TestHandler;
        $monolog = new MonologLogger('test', [$handler]);
        $logger = new Logger($monolog);

        (new RedactSensitiveData)($logger);

        $logger->info('user login', ['password' => 'supersecret', 'email' => 'dev@example.com']);

        $record = $handler->getRecords()[0];
        $this->assertEquals('[REDACTED]', $record->context['password']);
        $this->assertEquals('dev@example.com', $record->context['email']);
        $this->assertEquals(Level::Info, $record->level);
    }

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

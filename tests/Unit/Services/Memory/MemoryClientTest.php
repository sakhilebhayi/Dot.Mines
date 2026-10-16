<?php

namespace Tests\Unit\Services\Memory;

use App\Services\Memory\Exceptions\MemoryCredentialRejected;
use App\Services\Memory\Exceptions\MemoryRefused;
use App\Services\Memory\Exceptions\MemoryUnavailable;
use App\Services\Memory\MemoryClient;
use App\Services\Memory\MemoryClientFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

class MemoryClientTest extends TestCase
{
    private function client(): MemoryClient
    {
        return new MemoryClient('http://memory.test/', 'memory-token-value', 3);
    }

    public function test_record_posts_to_the_stage_endpoint_with_bearer_and_platform(): void
    {
        Http::fake(['memory.test/api/intelligence/actions' => Http::response(['data' => ['id' => 9]], 201)]);

        $this->assertSame(['id' => 9], $this->client()->record('action', ['loop_id' => 'l', 'action_kind' => 'alert.resolve']));

        Http::assertSent(fn (Request $r) => $r->url() === 'http://memory.test/api/intelligence/actions'
            && $r->hasHeader('Authorization', 'Bearer memory-token-value')
            && $r['platform'] === 'dot-mines'
            && $r['action_kind'] === 'alert.resolve');
    }

    public function test_errors_are_typed_and_never_carry_the_token(): void
    {
        Http::fake([
            'memory.test/api/intelligence/events' => Http::response(['message' => 'Unauthenticated.'], 401),
            'memory.test/api/intelligence/decisions' => Http::response(['errors' => ['risk' => ['The risk field is required.']]], 422),
            'memory.test/api/intelligence/outcomes' => Http::response('', 503),
        ]);

        try {
            $this->client()->record('event', []);
            $this->fail('expected credential rejection');
        } catch (MemoryCredentialRejected $e) {
            $this->assertStringNotContainsString('memory-token-value', $e->getMessage());
        }

        try {
            $this->client()->record('decision', []);
            $this->fail('expected refusal');
        } catch (MemoryRefused $e) {
            $this->assertSame('Dot.Memory refused the record: The risk field is required.', $e->getMessage());
        }

        $this->expectException(MemoryUnavailable::class);
        $this->client()->record('outcome', []);
    }

    public function test_unknown_stage_is_rejected_locally_and_factory_is_inert_without_config(): void
    {
        config()->set('services.memory.url', null);
        config()->set('services.memory.token', null);
        $this->assertFalse(MemoryClientFactory::configured());
        $this->assertNull((new MemoryClientFactory)->fromConfig());

        $this->expectException(InvalidArgumentException::class);
        $this->client()->record('wish', []);
    }
}

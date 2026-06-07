<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HealthControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function health_endpoint_returns_200_when_all_services_healthy(): void
    {
        $response = $this->getJson(route('health'));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'timestamp',
                'checks' => ['database', 'cache', 'queue', 'storage'],
            ])
            ->assertJsonPath('status', 'ok');
    }

    #[Test]
    public function health_response_includes_database_check(): void
    {
        $response = $this->getJson(route('health'));

        $response->assertJsonPath('checks.database.status', 'ok');
    }

    #[Test]
    public function health_response_includes_cache_check(): void
    {
        $response = $this->getJson(route('health'));

        $response->assertJsonPath('checks.cache.status', 'ok');
    }

    #[Test]
    public function health_response_includes_queue_check(): void
    {
        $response = $this->getJson(route('health'));

        $response->assertJsonPath('checks.queue.status', 'ok');
    }

    #[Test]
    public function health_response_includes_storage_check(): void
    {
        $response = $this->getJson(route('health'));

        $response->assertJsonPath('checks.storage.status', 'ok');
    }

    #[Test]
    public function health_response_includes_iso_timestamp(): void
    {
        $response = $this->getJson(route('health'));

        $response->assertJsonStructure(['timestamp']);
        $this->assertNotEmpty($response->json('timestamp'));
    }

    #[Test]
    public function health_returns_degraded_when_cache_fails(): void
    {
        Cache::shouldReceive('put')->once()->andThrow(new \RuntimeException('Cache unavailable'));
        Cache::makePartial();

        $response = $this->getJson(route('health'));

        $response->assertStatus(503)
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.cache.status', 'error');
    }

    #[Test]
    public function health_returns_degraded_when_database_fails(): void
    {
        DB::shouldReceive('connection')->andThrow(new \RuntimeException('DB down'));

        $response = $this->getJson(route('health'));

        $response->assertStatus(503)
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.database.status', 'error');
    }
}

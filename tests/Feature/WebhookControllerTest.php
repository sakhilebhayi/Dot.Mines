<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = 'test-paystack-secret';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.paystack.secret' => $this->secret]);
    }

    private function makePayload(array $overrides = []): string
    {
        return json_encode(array_merge([
            'event' => 'charge.success',
            'data' => [
                'reference' => 'ref_'.uniqid(),
                'createdAt' => now()->toIso8601String(),
            ],
        ], $overrides));
    }

    private function sign(string $payload): string
    {
        return hash_hmac('sha512', $payload, $this->secret);
    }

    // ── Signature Verification ───────────────────────────────────────────

    #[Test]
    public function valid_signature_returns_success(): void
    {
        $payload = $this->makePayload();

        $this->call('POST', route('webhooks.paystack'), [], [], [], [
            'HTTP_X-Paystack-Signature' => $this->sign($payload),
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertStatus(200)
            ->assertJson(['status' => 'success']);
    }

    #[Test]
    public function invalid_signature_returns_400(): void
    {
        $payload = $this->makePayload();

        $this->call('POST', route('webhooks.paystack'), [], [], [], [
            'HTTP_X-Paystack-Signature' => 'bad-signature',
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertStatus(400)
            ->assertJson(['error' => 'Invalid signature']);
    }

    #[Test]
    public function missing_signature_returns_400(): void
    {
        $payload = $this->makePayload();

        $this->call('POST', route('webhooks.paystack'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertStatus(400)
            ->assertJson(['error' => 'Invalid signature']);
    }

    #[Test]
    public function misconfigured_secret_returns_500(): void
    {
        config(['services.paystack.secret' => null]);

        $payload = $this->makePayload();

        $this->call('POST', route('webhooks.paystack'), [], [], [], [
            'HTTP_X-Paystack-Signature' => $this->sign($payload),
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertStatus(500)
            ->assertJson(['error' => 'Webhook endpoint misconfigured']);
    }

    // ── Replay Protection ────────────────────────────────────────────────

    #[Test]
    public function stale_event_older_than_5_minutes_is_rejected(): void
    {
        $staleTime = now()->subMinutes(10)->toIso8601String();
        $payload = json_encode([
            'event' => 'charge.success',
            'data' => ['createdAt' => $staleTime, 'reference' => 'old_ref'],
        ]);

        $this->call('POST', route('webhooks.paystack'), [], [], [], [
            'HTTP_X-Paystack-Signature' => $this->sign($payload),
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertStatus(400)
            ->assertJson(['error' => 'Stale webhook event']);
    }

    #[Test]
    public function recent_event_within_5_minutes_is_accepted(): void
    {
        $recentTime = now()->subMinutes(2)->toIso8601String();
        $payload = json_encode([
            'event' => 'charge.success',
            'data' => ['createdAt' => $recentTime, 'reference' => 'recent_ref'],
        ]);

        $this->call('POST', route('webhooks.paystack'), [], [], [], [
            'HTTP_X-Paystack-Signature' => $this->sign($payload),
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertStatus(200);
    }

    #[Test]
    public function event_without_timestamp_is_accepted(): void
    {
        $payload = json_encode([
            'event' => 'charge.success',
            'data' => ['reference' => 'no_ts_ref'],
        ]);

        $this->call('POST', route('webhooks.paystack'), [], [], [], [
            'HTTP_X-Paystack-Signature' => $this->sign($payload),
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertStatus(200);
    }

    // ── Payload Validation ───────────────────────────────────────────────

    #[Test]
    public function missing_event_key_returns_400(): void
    {
        $payload = json_encode(['data' => ['reference' => 'no_event']]);

        $this->call('POST', route('webhooks.paystack'), [], [], [], [
            'HTTP_X-Paystack-Signature' => $this->sign($payload),
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertStatus(400)
            ->assertJson(['error' => 'Invalid payload']);
    }

    // ── Known Event Types ────────────────────────────────────────────────

    #[Test]
    public function unhandled_event_type_returns_success(): void
    {
        $payload = $this->makePayload(['event' => 'unknown.event_type']);

        $this->call('POST', route('webhooks.paystack'), [], [], [], [
            'HTTP_X-Paystack-Signature' => $this->sign($payload),
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertStatus(200)
            ->assertJson(['status' => 'success']);
    }
}

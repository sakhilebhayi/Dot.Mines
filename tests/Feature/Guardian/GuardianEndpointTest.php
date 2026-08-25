<?php

namespace Tests\Feature\Guardian;

use App\Services\Guardian\CheckResult;
use App\Services\Guardian\Contracts\GuardianCheck;
use App\Services\Guardian\GuardianHealthReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuardianEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['guardian.token' => 'test-guardian-token']);
    }

    public function test_rejects_requests_without_a_bearer_token(): void
    {
        $this->getJson('/guardian/health')->assertUnauthorized();
    }

    public function test_rejects_requests_with_a_wrong_bearer_token(): void
    {
        $this->getJson('/guardian/health', ['Authorization' => 'Bearer wrong-token'])
            ->assertUnauthorized();
    }

    public function test_refuses_to_serve_when_no_token_is_configured(): void
    {
        config(['guardian.token' => null]);

        $this->getJson('/guardian/health', ['Authorization' => 'Bearer anything'])
            ->assertServiceUnavailable();
    }

    public function test_returns_the_guardian_contract_shape(): void
    {
        $response = $this->getJson('/guardian/health', [
            'Authorization' => 'Bearer test-guardian-token',
        ]);

        $response->assertOk()
            ->assertJsonPath('platform', 'dot-mines')
            ->assertJsonPath('contract', 'dot-guardian/v1')
            ->assertJsonStructure([
                'platform',
                'contract',
                'generated_at',
                'status',
                'checks',
            ]);

        $this->assertContains(
            $response->json('status'),
            ['healthy', 'warning', 'critical', 'unknown'],
        );

        foreach ($response->json('checks') as $check) {
            $this->assertArrayHasKey('status', $check);
            $this->assertArrayHasKey('message', $check);
            $this->assertArrayHasKey('metrics', $check);
        }
    }

    public function test_overall_status_is_the_worst_check_status(): void
    {
        $report = new GuardianHealthReport([
            $this->fakeCheck('alpha', CheckResult::healthy('fine')),
            $this->fakeCheck('bravo', CheckResult::critical('broken')),
            $this->fakeCheck('charlie', CheckResult::warning('degrading')),
        ]);

        $payload = $report->toArray();

        $this->assertSame('critical', $payload['status']);
        $this->assertSame('critical', $payload['checks']['bravo']['status']);
    }

    public function test_unknown_outranks_healthy_but_not_warning(): void
    {
        $report = new GuardianHealthReport([
            $this->fakeCheck('alpha', CheckResult::healthy('fine')),
            $this->fakeCheck('bravo', CheckResult::unknown('no signal')),
        ]);

        $this->assertSame('unknown', $report->toArray()['status']);

        $report = new GuardianHealthReport([
            $this->fakeCheck('alpha', CheckResult::unknown('no signal')),
            $this->fakeCheck('bravo', CheckResult::warning('degrading')),
        ]);

        $this->assertSame('warning', $report->toArray()['status']);
    }

    public function test_a_throwing_check_reports_unknown_instead_of_breaking_the_report(): void
    {
        $throwing = new class implements GuardianCheck
        {
            public function key(): string
            {
                return 'exploding';
            }

            public function run(): CheckResult
            {
                throw new \RuntimeException('boom');
            }
        };

        $payload = (new GuardianHealthReport([$throwing]))->toArray();

        $this->assertSame('unknown', $payload['checks']['exploding']['status']);
    }

    private function fakeCheck(string $key, CheckResult $result): GuardianCheck
    {
        return new class($key, $result) implements GuardianCheck
        {
            public function __construct(
                private readonly string $checkKey,
                private readonly CheckResult $result,
            ) {}

            public function key(): string
            {
                return $this->checkKey;
            }

            public function run(): CheckResult
            {
                return $this->result;
            }
        };
    }
}

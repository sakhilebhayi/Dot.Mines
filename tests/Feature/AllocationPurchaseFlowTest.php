<?php

namespace Tests\Feature;

use App\Livewire\BillingPortal;
use App\Models\MachineAllocation;
use App\Models\Payment;
use App\Models\Team;
use App\Models\User;
use App\Services\Billing\MachineEntitlementService;
use App\Services\PaystackService;
use App\Services\TeamRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Allocation Slice 2: purchasing capacity. Prices come only from the
 * allocation plan rows; checkout carries quantities in metadata; the
 * signature-verified charge.success webhook is the ONLY grant path and
 * is idempotent under duplicate delivery (brief §6-§10, §25).
 */
class AllocationPurchaseFlowTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'sk_test_paystack_secret';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.paystack.secret' => self::SECRET]);
    }

    private function fakePaystack(): void
    {
        Http::fake([
            'https://api.paystack.co/customer' => Http::response(['status' => true, 'data' => ['customer_code' => 'CUS_x']], 200),
            'https://api.paystack.co/customer/*' => Http::response(['status' => true, 'data' => ['customer_code' => 'CUS_x']], 200),
            'https://api.paystack.co/plan' => Http::response(['status' => true, 'data' => ['plan_code' => 'PLN_dynamic']], 200),
            'https://api.paystack.co/transaction/initialize' => Http::response(['status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/x', 'reference' => 'ref_123']], 200),
        ]);
    }

    private function owner(): array
    {
        $user = User::factory()->withPersonalTeam()->create();
        TeamRoleProvisioner::assignRole($user, $user->currentTeam, 'admin');

        return [$user, $user->currentTeam];
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function postWebhook(array $event): TestResponse
    {
        $payload = json_encode($event);
        assert(is_string($payload));

        return $this->call('POST', '/webhooks/paystack', [], [], [], [
            'HTTP_X-Paystack-Signature' => hash_hmac('sha512', $payload, self::SECRET),
            'CONTENT_TYPE' => 'application/json',
        ], $payload);
    }

    /**
     * @return array<string, mixed>
     */
    private function chargeSuccessEvent(Team $team, string $reference = 'ref_123', int $adt = 2, int $heavy = 1): array
    {
        return [
            'event' => 'charge.success',
            'data' => [
                'reference' => $reference,
                'amount' => 550000,
                'currency' => 'ZAR',
                'channel' => 'card',
                'createdAt' => now()->toIso8601String(),
                'metadata' => [
                    'team_id' => (string) $team->id,
                    'billing_cycle' => 'monthly',
                    'allocation_adt' => (string) $adt,
                    'allocation_heavy' => (string) $heavy,
                ],
            ],
        ];
    }

    public function test_checkout_total_comes_from_the_plan_rows_not_hardcoded_prices(): void
    {
        $this->fakePaystack();
        [, $team] = $this->owner();

        $result = (new PaystackService)->initializeAllocationPurchase($team, ['adt' => 2, 'heavy' => 1], 'monthly');

        $this->assertNotNull($result);
        $this->assertSame('https://checkout.paystack.com/x', $result['authorization_url']);

        // 2 x R1500 + 1 x R2500 = R5500 -> 550000 in cents, straight from
        // the migrated plan rows.
        Http::assertSent(function ($request): bool {
            if (! str_contains($request->url(), 'transaction/initialize')) {
                return false;
            }
            $body = json_decode($request->body(), true);

            return $body['amount'] === 550000
                && $body['metadata']['allocation_adt'] === '2'
                && $body['metadata']['allocation_heavy'] === '1';
        });
    }

    public function test_dynamic_paystack_plans_are_reused_per_amount(): void
    {
        $this->fakePaystack();
        [, $team] = $this->owner();

        $service = new PaystackService;
        $service->initializeAllocationPurchase($team, ['adt' => 1, 'heavy' => 0], 'monthly');
        $service->initializeAllocationPurchase($team, ['adt' => 1, 'heavy' => 0], 'monthly');

        // Same amount + interval -> exactly ONE /plan creation call.
        $planCalls = Http::recorded()
            ->filter(fn (array $pair): bool => str_ends_with($pair[0]->url(), '/plan'))
            ->count();

        $this->assertSame(1, $planCalls);
    }

    public function test_verified_charge_webhook_grants_the_ledger_rows(): void
    {
        [, $team] = $this->owner();

        $this->postWebhook($this->chargeSuccessEvent($team))->assertOk();

        $this->assertSame(2, (int) MachineAllocation::withoutGlobalScopes()->where('team_id', $team->id)->where('class', 'adt')->sum('delta'));
        $this->assertSame(1, (int) MachineAllocation::withoutGlobalScopes()->where('team_id', $team->id)->where('class', 'heavy')->sum('delta'));

        $payment = Payment::where('paystack_reference', 'ref_123')->firstOrFail();
        $this->assertSame(
            2,
            MachineAllocation::withoutGlobalScopes()->where('payment_id', $payment->id)->count(),
            'Grants must link to the verified payment for the audit trail.'
        );
    }

    public function test_duplicate_webhook_delivery_grants_exactly_once(): void
    {
        [, $team] = $this->owner();

        $event = $this->chargeSuccessEvent($team);
        $this->postWebhook($event)->assertOk();
        $this->postWebhook($event)->assertOk();

        $this->assertSame(1, Payment::where('paystack_reference', 'ref_123')->count());
        $this->assertSame(2, (int) MachineAllocation::withoutGlobalScopes()->where('team_id', $team->id)->where('class', 'adt')->sum('delta'));
    }

    public function test_failed_payment_webhook_grants_nothing(): void
    {
        [, $team] = $this->owner();

        $this->postWebhook([
            'event' => 'invoice.payment_failed',
            'data' => [
                'createdAt' => now()->toIso8601String(),
                'metadata' => ['team_id' => (string) $team->id, 'allocation_adt' => '5'],
            ],
        ])->assertOk();

        $this->assertSame(0, MachineAllocation::withoutGlobalScopes()->where('team_id', $team->id)->count());
    }

    public function test_charge_without_allocation_metadata_records_payment_but_grants_nothing(): void
    {
        [, $team] = $this->owner();

        $this->postWebhook([
            'event' => 'charge.success',
            'data' => [
                'reference' => 'ref_legacy',
                'amount' => 178200,
                'currency' => 'ZAR',
                'createdAt' => now()->toIso8601String(),
                'metadata' => ['team_id' => (string) $team->id],
            ],
        ])->assertOk();

        $this->assertSame(1, Payment::where('paystack_reference', 'ref_legacy')->count());
        $this->assertSame(0, MachineAllocation::withoutGlobalScopes()->where('team_id', $team->id)->count());
    }

    public function test_granted_allocations_immediately_raise_machine_capacity(): void
    {
        config(['billing.trial_machine_allowance' => 0]);
        [, $team] = $this->owner();

        $summaryBefore = app(MachineEntitlementService::class)->summary($team);
        $this->assertSame(0, $summaryBefore['available']['adt']);

        $this->postWebhook($this->chargeSuccessEvent($team))->assertOk();

        $summaryAfter = app(MachineEntitlementService::class)->summary($team);
        $this->assertSame(2, $summaryAfter['available']['adt']);
        $this->assertSame(1, $summaryAfter['available']['heavy']);
        $this->assertFalse($summaryAfter['trial']);
    }

    public function test_billing_portal_purchase_redirects_to_paystack(): void
    {
        $this->fakePaystack();
        [$user] = $this->owner();

        Livewire::actingAs($user)
            ->test(BillingPortal::class)
            ->set('qtyAdt', 1)
            ->set('qtyHeavy', 0)
            ->call('purchase')
            ->assertRedirect('https://checkout.paystack.com/x');
    }

    public function test_billing_portal_rejects_an_empty_purchase(): void
    {
        [$user] = $this->owner();

        Livewire::actingAs($user)
            ->test(BillingPortal::class)
            ->set('qtyAdt', 0)
            ->set('qtyHeavy', 0)
            ->call('purchase')
            ->assertHasErrors('purchase');
    }

    public function test_billing_page_shows_the_allocation_dashboard(): void
    {
        // Plain (non-admin-role) user: the route group's admin.2fa
        // middleware redirects admin-role accounts without confirmed 2FA.
        $user = User::factory()->withPersonalTeam()->create();

        $response = $this->actingAs($user)->get('/billing');

        $response->assertOk();
        $response->assertSee('Your Machine Capacity');
        $response->assertSee('ADT allocations');
        $response->assertSee('Purchase Machine Allocations');
        // The hardcoded price constants are gone; prices render from plans.
        $response->assertSee('1,500');
        $response->assertSee('2,500');
    }
}

<?php

namespace App\Livewire;

use App\Models\Invoice;
use App\Models\MachineAllocation;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\Billing\MachineEntitlementService;
use App\Services\PaystackService;
use App\Support\ApiPayload;
use App\Support\CurrentUser;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

/**
 * The allocation dashboard and purchase flow (billing brief §5, §7, §19).
 *
 * Pricing source of truth is the adt-allocation / heavy-allocation
 * SubscriptionPlan rows -- nothing here carries a price. Purchases only
 * ever REQUEST capacity: the signature-verified charge.success webhook is
 * the sole grant path, so this page shows "payment pending" and polls the
 * entitlement summary until the webhook lands (§8, §10, §21).
 *
 * @property-read SubscriptionPlan|null $adtPlan
 * @property-read SubscriptionPlan|null $heavyPlan
 */
class BillingPortal extends Component
{
    public int $qtyAdt = 0;

    public int $qtyHeavy = 0;

    public string $billingCycle = 'monthly';

    public ?Subscription $currentSubscription = null;

    public mixed $currentPlan = null;

    public ?string $nextBillingDate = null;

    public ?int $trialDaysRemaining = null;

    public float $totalPaid = 0;

    public string $totalPaidCurrency = 'ZAR';

    /** @var array<int|string, mixed> */
    public array $recentPayments = [];

    /** @var array<int|string, mixed> */
    public array $recentInvoices = [];

    public function mount(): void
    {
        $this->loadSubscriptionData();
        $this->loadRecentActivity();
    }

    /**
     * @return array{purchased: array{adt: int, heavy: int}, occupied: array{adt: int, heavy: int}, available: array{adt: int, heavy: int}, trial: bool, trial_allowance: int, over_allocated: bool, suspended: bool}
     */
    public function getAllocationSummaryProperty(): array
    {
        $team = CurrentUser::team();

        return app(MachineEntitlementService::class)->summary($team);
    }

    public function getAdtPlanProperty(): ?SubscriptionPlan
    {
        return SubscriptionPlan::query()->where('slug', 'adt-allocation')->where('is_active', true)->first();
    }

    public function getHeavyPlanProperty(): ?SubscriptionPlan
    {
        return SubscriptionPlan::query()->where('slug', 'heavy-allocation')->where('is_active', true)->first();
    }

    /**
     * The §18 audit trail, straight from the ledger -- purchases, admin
     * adjustments, refunds; every row immutable and attributable.
     *
     * @return array<int, array{class: string, delta: int, source: string, reason: string|null, created_at: string}>
     */
    public function getAllocationHistoryProperty(): array
    {
        /**
         * @psalm-suppress UnnecessaryVarAnnotation -- phpstan (unlike psalm) loses the Eloquent generics through latest()/limit()
         *
         * @var Collection<int, MachineAllocation> $rows
         */
        $rows = MachineAllocation::query()
            ->where('team_id', CurrentUser::get()?->currentTeam?->id)
            ->latest('id')
            ->limit(10)
            ->get();

        return $rows
            ->map(fn (MachineAllocation $row): array => [
                'class' => $row->class,
                'delta' => $row->delta,
                'source' => $row->source,
                'reason' => $row->reason,
                'created_at' => $row->created_at->format('j M Y H:i'),
            ])
            ->all();
    }

    public function getPurchaseTotalProperty(): float
    {
        $adt = $this->adtPlan;
        $heavy = $this->heavyPlan;

        if (! $adt || ! $heavy) {
            return 0.0;
        }

        $adtPrice = $this->billingCycle === 'yearly' ? (float) $adt->yearly_price : $adt->price;
        $heavyPrice = $this->billingCycle === 'yearly' ? (float) $heavy->yearly_price : $heavy->price;

        return ((float) $this->qtyAdt * $adtPrice) + ((float) $this->qtyHeavy * $heavyPrice);
    }

    public function purchase(): mixed
    {
        $this->validate([
            'qtyAdt' => 'integer|min:0|max:100',
            'qtyHeavy' => 'integer|min:0|max:100',
            'billingCycle' => 'in:monthly,yearly',
        ]);

        if ($this->qtyAdt + $this->qtyHeavy < 1) {
            $this->addError('purchase', 'Select at least one machine allocation.');

            return null;
        }

        $team = CurrentUser::team();

        try {
            $this->authorize('update', $team);

            $checkout = (new PaystackService)->initializeAllocationPurchase(
                $team,
                ['adt' => $this->qtyAdt, 'heavy' => $this->qtyHeavy],
                $this->billingCycle,
            );

            if (! $checkout) {
                $this->addError('purchase', "We couldn't start checkout. Please try again, or contact support if this keeps happening.");

                return null;
            }

            return redirect()->to($checkout['authorization_url']);
        } catch (\Throwable $e) {
            Log::error('Failed to start allocation checkout', ['team_id' => $team?->id, 'error' => $e->getMessage()]);
            $this->addError('purchase', "We couldn't start checkout. Please try again, or contact support if this keeps happening.");

            return null;
        }
    }

    public function switchBillingCycle(): void
    {
        $this->billingCycle = $this->billingCycle === 'monthly' ? 'yearly' : 'monthly';
    }

    public function loadSubscriptionData(): void
    {
        $team = CurrentUser::team();

        $this->currentSubscription = Subscription::where('team_id', $team->id)
            ->with('plan')
            ->first();

        if ($this->currentSubscription) {
            $this->currentPlan = $this->currentSubscription->plan;
            $this->nextBillingDate = $this->currentSubscription->current_period_end?->toDateString();
            $this->trialDaysRemaining = $this->currentSubscription->trialDaysRemaining();
        }
    }

    public function loadRecentActivity(): void
    {
        $team = CurrentUser::team();

        $succeededPayments = Payment::where('team_id', $team->id)
            ->where('status', 'succeeded');

        $this->totalPaid = (clone $succeededPayments)->sum('amount');
        $this->totalPaidCurrency = ApiPayload::str((clone $succeededPayments)->latest()->value('currency'), $team->currency);

        $this->recentPayments = Payment::where('team_id', $team->id)
            ->latest()
            ->limit(5)
            ->get()
            ->toArray();

        $this->recentInvoices = Invoice::where('team_id', $team->id)
            ->latest()
            ->limit(5)
            ->get()
            ->toArray();
    }

    public function manageBilling(): mixed
    {
        $team = CurrentUser::team();

        try {
            $this->authorize('update', $team);

            if (! $this->currentSubscription) {
                session()->flash('error', 'No active subscription to manage. Purchase an allocation first.');

                return null;
            }

            $portalUrl = (new PaystackService)->generateManageLink($this->currentSubscription);

            if (($portalUrl === null || $portalUrl === '' || $portalUrl === '0')) {
                session()->flash('error', 'Unable to access billing portal.');

                return null;
            }

            return redirect($portalUrl);
        } catch (\Throwable $e) {
            Log::error('Failed to open billing portal', ['team_id' => $team?->id, 'error' => $e->getMessage()]);
            session()->flash('error', "We couldn't open the billing portal. Please try again, or contact support if this keeps happening.");

            return null;
        }
    }

    public function cancelSubscription(): void
    {
        if (! $this->currentSubscription) {
            session()->flash('error', 'No active subscription found.');

            return;
        }

        try {
            $this->authorize('update', CurrentUser::get()?->currentTeam);

            $success = (new PaystackService)->cancelSubscription($this->currentSubscription, false);

            if ($success) {
                session()->flash('success', 'Your subscription will be canceled at the end of the billing period.');
                $this->loadSubscriptionData();
            } else {
                session()->flash('error', 'Unable to cancel subscription. Please contact support.');
            }
        } catch (\Throwable $e) {
            Log::error('Failed to cancel subscription', ['subscription_id' => $this->currentSubscription?->id, 'error' => $e->getMessage()]);
            session()->flash('error', "We couldn't cancel your subscription. Please try again, or contact support if this keeps happening.");
        }
    }

    public function resumeSubscription(): void
    {
        if (! $this->currentSubscription) {
            session()->flash('error', 'No subscription found.');

            return;
        }

        try {
            $this->authorize('update', CurrentUser::get()?->currentTeam);

            $success = (new PaystackService)->resumeSubscription($this->currentSubscription);

            if ($success) {
                session()->flash('success', 'Your subscription has been resumed.');
                $this->loadSubscriptionData();
            } else {
                session()->flash('error', 'Unable to resume subscription. Please contact support.');
            }
        } catch (\Throwable $e) {
            Log::error('Failed to resume subscription', ['subscription_id' => $this->currentSubscription?->id, 'error' => $e->getMessage()]);
            session()->flash('error', "We couldn't resume your subscription. Please try again, or contact support if this keeps happening.");
        }
    }

    public function downloadInvoice(int $invoiceId): mixed
    {
        $team = CurrentUser::team();
        $invoice = Invoice::where('team_id', $team->id)
            ->where('id', $invoiceId)
            ->first();

        if ($invoice && ($invoice->pdf_url !== null && $invoice->pdf_url !== '' && $invoice->pdf_url !== '0')) {
            return redirect($invoice->pdf_url);
        }

        session()->flash('error', 'Invoice not found or PDF not available.');

        return null;
    }

    public function render(): View
    {
        return view('livewire.billing-portal');
    }
}

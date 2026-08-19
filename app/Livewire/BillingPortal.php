<?php

namespace App\Livewire;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\PaystackService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class BillingPortal extends Component
{
    public bool $showConfirmModal = false;

    // User-selected machine counts for new subscription
    public int $selectedAdtCount = 0;

    public int $selectedBigMachineCount = 0;

    public function updatedSelectedAdtCount()
    {
        // Livewire will auto-update
    }

    public function updatedSelectedBigMachineCount()
    {
        // Livewire will auto-update
    }

    public function getUserSelectedMonthlyTotalProperty()
    {
        return ($this->selectedAdtCount * $this->ADT_PRICE) + ($this->selectedBigMachineCount * $this->BIG_MACHINE_PRICE);
    }

    public function getUserSelectedYearlyTotalProperty()
    {
        return $this->userSelectedMonthlyTotal * 12 * 0.9;
    }

    public mixed $currentSubscription = null;

    public mixed $currentPlan = null;

    public array $availablePlans = [];

    public ?int $selectedPlanId = null;

    public string $selectedBillingCycle = 'monthly';

    public bool $showPlanSelector = false;

    // Stats
    public float $totalPaid = 0;

    public string $totalPaidCurrency = 'ZAR';

    public ?string $nextBillingDate = null;

    public ?int $trialDaysRemaining = null;

    // Usage-based pricing
    public int $adtCount = 0;

    public int $bigMachineCount = 0;

    public float $monthlyPrice = 0;

    public float $yearlyPrice = 0;

    public int $ADT_PRICE = 1500; // R1,500 per ADT

    public int $BIG_MACHINE_PRICE = 2500; // R2,500 per bigger machine

    // Recent activity
    public array $recentPayments = [];

    public array $recentInvoices = [];

    public function mount()
    {
        $this->calculateUsageBasedPricing();
        $this->loadSubscriptionData();
        $this->loadAvailablePlans();
        $this->loadRecentActivity();
    }

    public function render()
    {
        return view('livewire.billing-portal', [
            'userSelectedMonthlyTotal' => $this->userSelectedMonthlyTotal,
            'userSelectedYearlyTotal' => $this->userSelectedYearlyTotal,
        ]);
    }

    public function calculateUsageBasedPricing()
    {
        $team = Auth::user()->currentTeam;

        // Count ADT machines (machine_type = 'adt')
        $this->adtCount = $team->machines()
            ->where('machine_type', 'adt')
            ->count();

        // Count bigger machines (excavator, dozer, loader, grader, etc.)
        $this->bigMachineCount = $team->machines()
            ->whereIn('machine_type', ['excavator', 'dozer', 'loader', 'grader', 'bulldozer'])
            ->count();

        // Calculate monthly and yearly pricing
        $this->monthlyPrice = ($this->adtCount * $this->ADT_PRICE) + ($this->bigMachineCount * $this->BIG_MACHINE_PRICE);
        $this->yearlyPrice = $this->monthlyPrice * 12 * 0.9; // 10% discount for yearly
    }

    public function loadSubscriptionData()
    {
        $team = Auth::user()->currentTeam;

        $this->currentSubscription = Subscription::where('team_id', $team->id)
            ->with('plan')
            ->first();

        if ($this->currentSubscription) {
            $this->currentPlan = $this->currentSubscription->plan;
            $this->selectedBillingCycle = $this->currentSubscription->billing_cycle;
            $this->nextBillingDate = $this->currentSubscription->current_period_end;
            $this->trialDaysRemaining = $this->currentSubscription->trialDaysRemaining();
        }
    }

    public function loadAvailablePlans()
    {
        $this->availablePlans = SubscriptionPlan::active()->get()->toArray();
    }

    public function loadRecentActivity()
    {
        $team = Auth::user()->currentTeam;

        // Calculate total paid. Payments are (in practice) all recorded in
        // whichever single currency Stripe actually charged this team's
        // subscription in, so summing raw amounts and labelling with the
        // most recent payment's currency is accurate for the real, current
        // usage pattern -- there's no per-currency grouping since a team
        // isn't expected to be billed in more than one currency at once.
        $succeededPayments = Payment::where('team_id', $team->id)
            ->where('status', 'succeeded');

        $this->totalPaid = (clone $succeededPayments)->sum('amount');
        $this->totalPaidCurrency = (clone $succeededPayments)->latest()->value('currency')
            ?? $team->currency
            ?? 'ZAR';

        // Get recent payments
        $this->recentPayments = Payment::where('team_id', $team->id)
            ->with('subscription.plan')
            ->latest()
            ->limit(5)
            ->get()
            ->toArray();

        // Get recent invoices
        $this->recentInvoices = Invoice::where('team_id', $team->id)
            ->latest()
            ->limit(5)
            ->get()
            ->toArray();
    }

    public function selectPlan($planId)
    {
        $this->selectedPlanId = $planId;
        $this->showPlanSelector = false;
        $this->dispatch('plan-selected', $planId);
    }

    public function subscribe()
    {
        if (($this->selectedAdtCount + $this->selectedBigMachineCount) < 1) {
            session()->flash('error', 'Please select at least one machine.');
            $this->showConfirmModal = false;

            return;
        }

        $team = Auth::user()->currentTeam;

        // You can extend this to pass counts to Stripe or your backend
        try {
            $this->authorize('update', $team);

            // Store selected machine counts in session or database as needed
            session()->put('subscription.selectedAdtCount', $this->selectedAdtCount);
            session()->put('subscription.selectedBigMachineCount', $this->selectedBigMachineCount);
            session()->put('subscription.selectedBillingCycle', $this->selectedBillingCycle);

            $plan = $this->selectedPlanId
                ? SubscriptionPlan::find($this->selectedPlanId)
                : SubscriptionPlan::active()->first();

            if (! $plan) {
                $this->showConfirmModal = false;
                session()->flash('error', 'No subscription plan is configured. Please contact support.');

                return;
            }

            $checkout = (new PaystackService)->initializeTransaction($team, $plan, $this->selectedBillingCycle);

            if (! $checkout) {
                $this->showConfirmModal = false;
                session()->flash('error', "We couldn't start checkout. Please try again, or contact support if this keeps happening.");

                return;
            }

            $this->showConfirmModal = false;

            return redirect()->to($checkout['authorization_url']);
        } catch (\Throwable $e) {
            $this->showConfirmModal = false;
            Log::error('Failed to start subscription checkout', ['team_id' => $team->id, 'error' => $e->getMessage()]);
            session()->flash('error', "We couldn't start checkout. Please try again, or contact support if this keeps happening.");
        }
    }

    public function manageBilling()
    {
        $team = Auth::user()->currentTeam;

        try {
            $this->authorize('update', $team);

            if (! $this->currentSubscription) {
                session()->flash('error', 'No active subscription to manage. Subscribe first.');

                return;
            }

            // Paystack's hosted subscription-management page (card updates,
            // cancellation) -- its equivalent of a billing portal.
            $portalUrl = (new PaystackService)->generateManageLink($this->currentSubscription);

            if (! $portalUrl) {
                session()->flash('error', 'Unable to access billing portal.');

                return;
            }

            return redirect($portalUrl);

        } catch (\Throwable $e) {
            Log::error('Failed to open billing portal', ['team_id' => $team->id, 'error' => $e->getMessage()]);
            session()->flash('error', "We couldn't open the billing portal. Please try again, or contact support if this keeps happening.");
        }
    }

    public function cancelSubscription()
    {
        if (! $this->currentSubscription) {
            session()->flash('error', 'No active subscription found.');

            return;
        }

        try {
            $this->authorize('update', Auth::user()->currentTeam);

            $success = (new PaystackService)->cancelSubscription($this->currentSubscription, false);

            if ($success) {
                session()->flash('success', 'Your subscription will be canceled at the end of the billing period.');
                $this->loadSubscriptionData();
            } else {
                session()->flash('error', 'Unable to cancel subscription. Please contact support.');
            }

        } catch (\Throwable $e) {
            Log::error('Failed to cancel subscription', ['subscription_id' => $this->currentSubscription->id ?? null, 'error' => $e->getMessage()]);
            session()->flash('error', "We couldn't cancel your subscription. Please try again, or contact support if this keeps happening.");
        }
    }

    public function resumeSubscription()
    {
        if (! $this->currentSubscription) {
            session()->flash('error', 'No subscription found.');

            return;
        }

        try {
            $this->authorize('update', Auth::user()->currentTeam);

            $success = (new PaystackService)->resumeSubscription($this->currentSubscription);

            if ($success) {
                session()->flash('success', 'Your subscription has been resumed.');
                $this->loadSubscriptionData();
            } else {
                session()->flash('error', 'Unable to resume subscription. Please contact support.');
            }

        } catch (\Throwable $e) {
            Log::error('Failed to resume subscription', ['subscription_id' => $this->currentSubscription->id ?? null, 'error' => $e->getMessage()]);
            session()->flash('error', "We couldn't resume your subscription. Please try again, or contact support if this keeps happening.");
        }
    }

    public function switchBillingCycle()
    {
        $this->selectedBillingCycle = $this->selectedBillingCycle === 'monthly' ? 'yearly' : 'monthly';
    }

    public function togglePlanSelector()
    {
        $this->showPlanSelector = ! $this->showPlanSelector;
    }

    public function downloadInvoice($invoiceId)
    {
        $team = Auth::user()->currentTeam;
        $invoice = Invoice::where('team_id', $team->id)
            ->where('id', $invoiceId)
            ->first();

        if ($invoice && $invoice->pdf_url) {
            return redirect($invoice->pdf_url);
        }

        session()->flash('error', 'Invoice not found or PDF not available.');
    }
}

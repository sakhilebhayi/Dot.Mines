<?php

namespace App\Livewire;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\PaystackService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

class BillingPortal extends Component
{
    public bool $showConfirmModal = false;

    // User-selected machine counts for new subscription
    public int $selectedAdtCount = 0;

    public int $selectedBigMachineCount = 0;

    public function updatedSelectedAdtCount(): void
    {
        // Livewire will auto-update
    }

    public function updatedSelectedBigMachineCount(): void
    {
        // Livewire will auto-update
    }

    public function getUserSelectedMonthlyTotalProperty(): mixed
    {
        return ($this->selectedAdtCount * $this->ADT_PRICE) + ($this->selectedBigMachineCount * $this->BIG_MACHINE_PRICE);
    }

    public function getUserSelectedYearlyTotalProperty(): mixed
    {
        return $this->userSelectedMonthlyTotal * 12 * 0.9;
    }

    public mixed $currentSubscription = null;

    public mixed $currentPlan = null;

    /** @var array<string, mixed> */
    public array $availablePlans = [];

    public ?int $selectedPlanId = null;

    public string $selectedBillingCycle = 'monthly';

    public bool $showPlanSelector = false;

    // Stats
    public float $totalPaid = 0;

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
    /** @var array<string, mixed> */
    public array $recentPayments = [];

    /** @var array<string, mixed> */
    public array $recentInvoices = [];

    public function mount(): void
    {
        $this->calculateUsageBasedPricing();
        $this->loadSubscriptionData();
        $this->loadAvailablePlans();
        $this->loadRecentActivity();
    }

    public function render(): View
    {
        return view('livewire.billing-portal', [
            'userSelectedMonthlyTotal' => $this->userSelectedMonthlyTotal,
            'userSelectedYearlyTotal' => $this->userSelectedYearlyTotal,
        ]);
    }

    public function calculateUsageBasedPricing(): void
    {
        $team = Auth::user()->currentTeam;

        $this->adtCount = $team->machines()
            ->where('machine_type', 'adt')
            ->count();

        $this->bigMachineCount = $team->machines()
            ->whereIn('machine_type', ['excavator', 'dozer', 'loader', 'grader', 'bulldozer'])
            ->count();

        $this->monthlyPrice = ($this->adtCount * $this->ADT_PRICE) + ($this->bigMachineCount * $this->BIG_MACHINE_PRICE);
        $this->yearlyPrice = $this->monthlyPrice * 12 * 0.9;
    }

    public function loadSubscriptionData(): void
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

    public function loadAvailablePlans(): void
    {
        $this->availablePlans = SubscriptionPlan::active()->get()->toArray();
    }

    public function loadRecentActivity(): void
    {
        $team = Auth::user()->currentTeam;

        $this->totalPaid = Payment::where('team_id', $team->id)
            ->where('status', 'succeeded')
            ->sum('amount');

        $this->recentPayments = Payment::where('team_id', $team->id)
            ->with('subscription.plan')
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

    public function selectPlan($planId): void
    {
        $this->selectedPlanId = $planId;
        $this->showPlanSelector = false;
        $this->dispatch('plan-selected', $planId);
    }

    public function subscribe(): mixed
    {
        if (! $this->selectedPlanId) {
            session()->flash('error', 'Please select a plan.');
            $this->showConfirmModal = false;

            return null;
        }

        $plan = SubscriptionPlan::find($this->selectedPlanId);

        if (! $plan) {
            session()->flash('error', 'Selected plan not found.');
            $this->showConfirmModal = false;

            return null;
        }

        $team = Auth::user()->currentTeam;

        try {
            $paystackService = new PaystackService;
            $result = $paystackService->initializeTransaction($team, $plan, $this->selectedBillingCycle);

            if (! $result) {
                session()->flash('error', 'Unable to initiate payment. Please try again.');
                $this->showConfirmModal = false;

                return null;
            }

            $this->showConfirmModal = false;

            return $this->redirect($result['authorization_url']);

        } catch (\Exception $e) {
            report($e);
            $this->showConfirmModal = false;
            session()->flash('error', 'An error occurred. Please try again.');
        }

        return null;
    }

    public function cancelSubscription(): void
    {
        if (! $this->currentSubscription) {
            session()->flash('error', 'No active subscription found.');

            return;
        }

        try {
            $paystackService = new PaystackService;
            $success = $paystackService->cancelSubscription($this->currentSubscription, false);

            if ($success) {
                session()->flash('success', 'Your subscription will be canceled at the end of the billing period.');
                $this->loadSubscriptionData();
            } else {
                session()->flash('error', 'Unable to cancel subscription. Please contact support.');
            }

        } catch (\Exception $e) {
            report($e);
            session()->flash('error', 'An error occurred. Please try again.');
        }
    }

    public function resumeSubscription(): void
    {
        if (! $this->currentSubscription) {
            session()->flash('error', 'No subscription found.');

            return;
        }

        try {
            $paystackService = new PaystackService;
            $success = $paystackService->resumeSubscription($this->currentSubscription);

            if ($success) {
                session()->flash('success', 'Your subscription has been resumed.');
                $this->loadSubscriptionData();
            } else {
                session()->flash('error', 'Unable to resume subscription. Please contact support.');
            }

        } catch (\Exception $e) {
            report($e);
            session()->flash('error', 'An error occurred. Please try again.');
        }
    }

    public function switchBillingCycle(): void
    {
        $this->selectedBillingCycle = $this->selectedBillingCycle === 'monthly' ? 'yearly' : 'monthly';
    }

    public function togglePlanSelector(): void
    {
        $this->showPlanSelector = ! $this->showPlanSelector;
    }

    public function downloadInvoice($invoiceId): mixed
    {
        $team = Auth::user()->currentTeam;
        $invoice = Invoice::where('team_id', $team->id)
            ->where('id', $invoiceId)
            ->first();

        if ($invoice && $invoice->pdf_url) {
            return redirect($invoice->pdf_url);
        }

        session()->flash('error', 'Invoice not found or PDF not available.');

        return null;
    }
}

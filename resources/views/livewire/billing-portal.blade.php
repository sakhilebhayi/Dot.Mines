{{-- 15s visible-only poll: after a checkout the allocation grant arrives via
     the payment webhook, so the summary refreshes itself without re-login
     (billing brief §21). Cheap: two aggregate queries per tick. --}}
<div class="container mx-auto py-8 px-4 max-w-5xl space-y-6" wire:poll.visible.15s>
    <div>
        <h1 class="text-3xl font-bold text-[var(--stone)]">Billing &amp; Machine Capacity</h1>
        <p class="text-[var(--sand)] text-sm mt-1">What you pay for, how many machines you can run, and how to add more.</p>
    </div>

    @if (session('success'))
        <div class="bg-green-500/10 border border-green-500/30 text-green-400 rounded-xl p-4" role="status">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="bg-red-500/10 border border-red-500/30 text-red-400 rounded-xl p-4" role="alert">{{ session('error') }}</div>
    @endif

    @php $alloc = $this->allocationSummary; @endphp

    {{-- Machine capacity (brief §5/§19): purchased vs used per class. --}}
    <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-xl shadow-lg p-6">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <h2 class="text-xl font-display font-semibold text-[var(--stone)]">Your Machine Capacity</h2>
            @if ($alloc['trial'])
                <span class="text-xs px-2.5 py-1 rounded-full bg-blue-500/15 text-blue-400 font-medium">Trial allowance: {{ $alloc['trial_allowance'] }} machines</span>
            @endif
        </div>

        @if ($alloc['suspended'] ?? false)
            <div class="bg-red-500/10 border border-red-500/30 text-red-300 rounded-lg p-3 mb-4 text-sm" role="alert">
                <span class="font-semibold text-red-400">Allocations unavailable.</span>
                Your subscription has lapsed. Existing machines keep running, but purchased allocations only count while the subscription is active — renew below to restore your capacity.
            </div>
        @elseif ($alloc['over_allocated'])
            <div class="bg-amber-500/10 border border-amber-500/30 text-amber-300 rounded-lg p-3 mb-4 text-sm" role="alert">
                <span class="font-semibold text-amber-400">Allocation review required.</span>
                This account runs more machines than its current entitlement. Existing machines keep working; adding machines requires purchasing allocations below.
            </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach ([
                'adt' => ['ADT allocations', $this->adtPlan],
                'heavy' => ['Heavy machine allocations', $this->heavyPlan],
            ] as $class => [$label, $plan])
                @php
                    $capacity = $alloc['trial'] ? $alloc['trial_allowance'] : $alloc['purchased'][$class];
                    $used = $alloc['occupied'][$class];
                    $pct = $capacity > 0 ? min(100, (int) round($used / $capacity * 100)) : ($used > 0 ? 100 : 0);
                @endphp
                <div class="rounded-lg border border-[var(--line)] bg-[var(--ink)]/40 p-4">
                    <div class="flex items-center justify-between mb-1">
                        <p class="text-sm font-medium text-[var(--stone)]">{{ $label }}</p>
                        <p class="text-sm text-[var(--sand)]">
                            <span class="text-[var(--stone)] font-semibold">{{ $used }}</span>
                            / {{ $alloc['trial'] ? $alloc['trial_allowance'].' (trial)' : $alloc['purchased'][$class] }} used
                        </p>
                    </div>
                    <div class="h-2 rounded-full bg-white/5 overflow-hidden" role="progressbar" aria-valuenow="{{ $used }}" aria-valuemin="0" aria-valuemax="{{ max($capacity, $used) }}">
                        <div class="h-full rounded-full {{ $alloc['available'][$class] > 0 ? 'bg-[var(--gold)]' : 'bg-amber-500' }}" style="width: {{ $pct }}%"></div>
                    </div>
                    <p class="text-xs mt-2 {{ $alloc['available'][$class] > 0 ? 'text-[var(--sand)]' : 'text-amber-400' }}">
                        {{ max($alloc['available'][$class], 0) }} available
                        @if ($plan)
                            · R{{ number_format((float) $plan->price, 0) }}/month each
                        @endif
                    </p>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Purchase additional allocations (brief §7). Prices come from the
         allocation plan rows -- the single pricing source of truth. --}}
    <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-xl shadow-lg p-6">
        <h2 class="text-xl font-display font-semibold text-[var(--stone)] mb-1">Purchase Machine Allocations</h2>
        <p class="text-sm text-[var(--sand)] mb-4">Each allocation lets you run one machine. Your allocation becomes available as soon as the payment is confirmed.</p>

        @error('purchase')
            <div class="bg-red-500/10 border border-red-500/30 text-red-400 rounded-lg p-3 mb-4 text-sm" role="alert">{{ $message }}</div>
        @enderror

        @if (! $this->adtPlan || ! $this->heavyPlan)
            <p class="text-sm text-amber-400">Allocation pricing is not configured yet. Please contact support.</p>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <label class="rounded-lg border border-[var(--line)] bg-[var(--ink)]/40 p-4 block">
                    <span class="text-sm font-medium text-[var(--stone)]">ADT machines</span>
                    <span class="block text-xs text-[var(--sand)] mt-0.5">
                        R{{ number_format($billingCycle === 'yearly' ? (float) $this->adtPlan->yearly_price : (float) $this->adtPlan->price, 0) }}/{{ $billingCycle === 'yearly' ? 'year' : 'month' }} each
                    </span>
                    <input type="number" min="0" max="100" wire:model.live="qtyAdt"
                           class="mt-2 w-24 bg-[var(--ink)] border-[var(--line)] text-[var(--stone)] rounded-lg focus:border-[var(--gold)] focus:ring-[var(--gold)]">
                </label>
                <label class="rounded-lg border border-[var(--line)] bg-[var(--ink)]/40 p-4 block">
                    <span class="text-sm font-medium text-[var(--stone)]">Heavy machines <span class="text-[var(--sand)] font-normal">(excavator, dozer, loader, grader)</span></span>
                    <span class="block text-xs text-[var(--sand)] mt-0.5">
                        R{{ number_format($billingCycle === 'yearly' ? (float) $this->heavyPlan->yearly_price : (float) $this->heavyPlan->price, 0) }}/{{ $billingCycle === 'yearly' ? 'year' : 'month' }} each
                    </span>
                    <input type="number" min="0" max="100" wire:model.live="qtyHeavy"
                           class="mt-2 w-24 bg-[var(--ink)] border-[var(--line)] text-[var(--stone)] rounded-lg focus:border-[var(--gold)] focus:ring-[var(--gold)]">
                </label>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-4">
                <button type="button" wire:click="switchBillingCycle" class="text-sm text-[var(--sand)] hover:text-[var(--stone)] underline decoration-dotted">
                    Billing: <span class="text-[var(--stone)] font-medium">{{ $billingCycle === 'yearly' ? 'Yearly (10% off)' : 'Monthly' }}</span> — switch
                </button>
                <div class="flex items-center gap-4">
                    <p class="text-sm text-[var(--sand)]">Total:
                        <span class="text-xl font-semibold text-[var(--stone)]">R{{ number_format($this->purchaseTotal, 2) }}</span>
                        <span class="text-xs">/{{ $billingCycle === 'yearly' ? 'year' : 'month' }}</span>
                    </p>
                    <x-busy-button target="purchase" wire:click="purchase">Continue to Payment</x-busy-button>
                </div>
            </div>
            <p class="text-xs text-[var(--sand)]/70 mt-3">
                You'll be redirected to Paystack to complete payment securely. Allocations are granted once the payment is confirmed — if your payment shows as pending, this page updates automatically when confirmation arrives.
            </p>
        @endif
    </div>

    {{-- Subscription status --}}
    <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-xl shadow-lg p-6">
        <h2 class="text-xl font-display font-semibold text-[var(--stone)] mb-4">Subscription</h2>
        @if ($currentSubscription)
            <div class="flex flex-wrap items-center gap-x-8 gap-y-2 text-sm">
                <p class="text-[var(--sand)]">Status:
                    <span class="font-medium {{ $currentSubscription->isActive() ? 'text-green-400' : 'text-amber-400' }}">{{ ucfirst($currentSubscription->status) }}</span>
                </p>
                @if ($nextBillingDate)
                    <p class="text-[var(--sand)]">Next billing: <span class="text-[var(--stone)]">{{ \Illuminate\Support\Carbon::parse($nextBillingDate)->format('j F Y') }}</span></p>
                @endif
                <p class="text-[var(--sand)]">Total paid: <span class="text-[var(--stone)]">{{ $totalPaidCurrency }} {{ number_format($totalPaid, 2) }}</span></p>
            </div>
            <div class="flex gap-2 mt-4">
                <x-secondary-button wire:click="manageBilling">Manage payment method</x-secondary-button>
                @if ($currentSubscription->isCanceled())
                    <x-secondary-button wire:click="resumeSubscription">Resume subscription</x-secondary-button>
                @else
                    <x-secondary-button wire:click="cancelSubscription" wire:confirm="Cancel your subscription at the end of the billing period? Your machines keep running until then.">Cancel subscription</x-secondary-button>
                @endif
            </div>
        @else
            <p class="text-sm text-[var(--sand)]">No subscription yet. Purchasing machine allocations above starts one.</p>
        @endif
    </div>

    {{-- Recent payments --}}
    @if (count($recentPayments) > 0)
        <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-xl shadow-lg p-6">
            <h2 class="text-xl font-display font-semibold text-[var(--stone)] mb-4">Recent Payments</h2>
            <div class="divide-y divide-[var(--line)]">
                @foreach ($recentPayments as $payment)
                    <div class="py-2 flex items-center justify-between text-sm" wire:key="payment-{{ $payment['id'] }}">
                        <span class="text-[var(--sand)]">{{ \Illuminate\Support\Carbon::parse($payment['created_at'])->format('j M Y') }}</span>
                        <span class="text-[var(--stone)]">{{ $payment['currency'] }} {{ number_format((float) $payment['amount'], 2) }}</span>
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $payment['status'] === 'succeeded' ? 'bg-green-500/15 text-green-400' : ($payment['status'] === 'pending' ? 'bg-amber-500/15 text-amber-400' : 'bg-red-500/15 text-red-400') }}">
                            {{ ucfirst($payment['status']) }}
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>

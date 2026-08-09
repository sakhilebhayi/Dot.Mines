<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-[var(--stone)]">Billing & Subscription</h1>
        <p class="text-[var(--sand)] mt-2">Manage your subscription, view invoices, and update payment methods</p>
    </div>

    <!-- Flash Messages -->
    @if (session()->has('success'))
        <div class="mb-6 bg-green-600 text-[var(--stone)] px-4 py-3 rounded-lg">
            {{ session('success') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div class="mb-6 bg-red-600 text-[var(--stone)] px-4 py-3 rounded-lg">
            {{ session('error') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Column - Subscription Info -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Current Subscription Card -->
            @if($currentSubscription)
                <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg p-6">
                    <div class="flex justify-between items-start mb-6">
                        <div>
                            <h2 class="text-2xl font-bold text-[var(--stone)]">{{ $currentPlan->name }} Plan</h2>
                            <p class="text-[var(--sand)] mt-1">{{ $currentPlan->description }}</p>
                        </div>
                        <span class="px-3 py-1 rounded-full text-sm font-medium
                            {{ $currentSubscription['status'] === 'active' ? 'bg-green-600' : '' }}
                            {{ $currentSubscription['status'] === 'trial' ? 'bg-blue-600' : '' }}
                            {{ $currentSubscription['status'] === 'past_due' ? 'bg-yellow-600' : '' }}
                            {{ $currentSubscription['status'] === 'canceled' ? 'bg-red-600' : '' }}
                            text-[var(--stone)]">
                            {{ ucfirst($currentSubscription['status']) }}
                        </span>
                    </div>

                    <!-- Trial Warning -->
                    @if($currentSubscription['status'] === 'trial' && $trialDaysRemaining !== null)
                        <div class="mb-6 bg-blue-600/20 border border-blue-600/50 rounded-lg p-4">
                            <div class="flex items-center gap-3">
                                <svg class="w-6 h-6 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <div>
                                    <p class="text-blue-300 font-medium">Trial Period Active</p>
                                    <p class="text-blue-400 text-sm">{{ $trialDaysRemaining }} days remaining in your trial</p>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Cancellation Notice -->
                    @if($currentSubscription['canceled_at'] && $currentSubscription['ends_at'])
                        <div class="mb-6 bg-red-600/20 border border-red-600/50 rounded-lg p-4">
                            <div class="flex items-center gap-3">
                                <svg class="w-6 h-6 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                </svg>
                                <div>
                                    <p class="text-red-300 font-medium">Subscription Canceled</p>
                                    <p class="text-red-400 text-sm">Access ends on {{ \Carbon\Carbon::parse($currentSubscription['ends_at'])->format('F j, Y') }}</p>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Pricing -->
                    <div class="mb-6">
                        <div class="flex items-baseline gap-2">
                            <span class="text-4xl font-bold text-[var(--stone)]">
                                R{{ number_format($currentSubscription['billing_cycle'] === 'yearly' ? $yearlyPrice : $monthlyPrice, 2) }}
                            </span>
                            <span class="text-[var(--sand)]">/ {{ $currentSubscription['billing_cycle'] === 'yearly' ? 'year' : 'month' }}</span>
                        </div>
                        <p class="text-[var(--sand)] text-sm mt-2">{{ $adtCount }} ADT @ R1,500 each, {{ $bigMachineCount }} big machines @ R2,500 each</p>
                        @if($currentSubscription['billing_cycle'] === 'yearly')
                            <p class="text-green-400 text-sm mt-1">10% yearly discount applied</p>
                        @endif
                    </div>

                    <!-- Plan Features -->
                    <div class="mb-6">
                        <h3 class="text-sm font-semibold text-[var(--sand)] uppercase mb-3">Plan Features</h3>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="flex items-center gap-2 text-[var(--sand)]">
                                <svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                <span>{{ $currentPlan['max_machines'] }} Machines</span>
                            </div>
                            <div class="flex items-center gap-2 text-[var(--sand)]">
                                <svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                <span>{{ $currentPlan['max_users'] }} Users</span>
                            </div>
                            <div class="flex items-center gap-2 text-[var(--sand)]">
                                <svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                <span>{{ $currentPlan['max_geofences'] }} Geofences</span>
                            </div>
                            <div class="flex items-center gap-2 text-[var(--sand)]">
                                <svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                <span>{{ $currentPlan['max_mine_areas'] }} Mine Areas</span>
                            </div>
                            @if($currentPlan['has_advanced_analytics'])
                                <div class="flex items-center gap-2 text-[var(--sand)]">
                                    <svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                    <span>Advanced Analytics</span>
                                </div>
                            @endif
                            @if($currentPlan['has_api_access'])
                                <div class="flex items-center gap-2 text-[var(--sand)]">
                                    <svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                    <span>API Access</span>
                                </div>
                            @endif
                            @if($currentPlan['has_priority_support'])
                                <div class="flex items-center gap-2 text-[var(--sand)]">
                                    <svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                    <span>Priority Support</span>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex flex-wrap gap-3">
                        <button wire:click="manageBilling" 
                            class="px-4 py-2 bg-[var(--gold)] hover:bg-[var(--gold-soft)] text-[var(--stone)] rounded-lg transition-colors">
                            Manage Billing
                        </button>
                        
                        <button wire:click="togglePlanSelector" 
                            class="px-4 py-2 bg-white/10 hover:bg-white/20 text-[var(--stone)] rounded-lg transition-colors">
                            Change Plan
                        </button>

                        @if($currentSubscription['canceled_at'] && $currentSubscription['ends_at'])
                            <button wire:click="resumeSubscription" 
                                wire:confirm="Resume your subscription?"
                                class="px-4 py-2 bg-green-600 hover:bg-green-700 text-[var(--stone)] rounded-lg transition-colors">
                                Resume Subscription
                            </button>
                        @elseif($currentSubscription['status'] === 'active')
                            <button wire:click="cancelSubscription" 
                                wire:confirm="Are you sure you want to cancel your subscription? You'll still have access until the end of your billing period."
                                class="px-4 py-2 bg-red-600 hover:bg-red-700 text-[var(--stone)] rounded-lg transition-colors">
                                Cancel Subscription
                            </button>
                        @endif
                    </div>

                    <!-- Next Billing Date -->
                    @if($nextBillingDate && $currentSubscription['status'] !== 'canceled')
                        <div class="mt-6 pt-6 border-t border-[var(--line)]">
                            <p class="text-sm text-[var(--sand)]">
                                Next billing date: <span class="text-[var(--stone)] font-medium">{{ \Carbon\Carbon::parse($nextBillingDate)->format('F j, Y') }}</span>
                            </p>
                        </div>
                    @endif
                </div>
            @else
                <!-- No Subscription - Show Plans -->
                <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg p-6">
                    <h2 class="text-2xl font-bold text-[var(--stone)] mb-4">Choose Your Plan</h2>
                    <p class="text-[var(--sand)] mb-6">Select a plan to get started with Mines</p>
                    
                    <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg p-6">
                        <h3 class="text-xl font-bold text-[var(--stone)] mb-4">Select Machine Types & Quantity</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <label class="block text-[var(--stone)] font-medium mb-2">ADT (Articulated Dump Truck)
                                    <span class="ml-1 text-xs text-[var(--sand)]" title="Used for hauling material, typically R1,500 per month per machine">[?]</span>
                                </label>
                                <div class="flex items-center gap-2">
                                    <input type="number" min="0" wire:model="selectedAdtCount" class="w-24 px-3 py-2 bg-white/5 border border-[var(--line)] rounded text-[var(--stone)] focus:outline-none focus:border-[var(--gold)]" />
                                    <span class="text-[var(--sand)]">x R1,500 / month</span>
                                </div>
                            </div>
                            <div>
                                <label class="block text-[var(--stone)] font-medium mb-2">Big Machine (Excavator, Loader, etc.)
                                    <span class="ml-1 text-xs text-[var(--sand)]" title="Includes excavators, loaders, dozers, graders. R2,500 per month per machine">[?]</span>
                                </label>
                                <div class="flex items-center gap-2">
                                    <input type="number" min="0" wire:model="selectedBigMachineCount" class="w-24 px-3 py-2 bg-white/5 border border-[var(--line)] rounded text-[var(--stone)] focus:outline-none focus:border-[var(--gold)]" />
                                    <span class="text-[var(--sand)]">x R2,500 / month</span>
                                </div>
                            </div>
                        </div>

                        <!-- Plan Summary -->
                        <div class="mb-6 bg-[var(--ink)]/60 rounded-lg p-4 text-[var(--sand)]">
                            <h4 class="font-semibold text-[var(--stone)] mb-2">Plan Summary</h4>
                            <ul class="text-sm mb-2">
                                <li>{{ $selectedAdtCount }} ADT @ R1,500 each</li>
                                <li>{{ $selectedBigMachineCount }} Big Machine @ R2,500 each</li>
                                <li>Billing Cycle: <span class="font-bold">{{ $selectedBillingCycle === 'yearly' ? 'Yearly (10% discount)' : 'Monthly' }}</span></li>
                            </ul>
                            <div class="text-lg font-bold text-green-400">Total: R{{ number_format($selectedBillingCycle === 'yearly' ? $userSelectedYearlyTotal : $userSelectedMonthlyTotal, 2) }} / {{ $selectedBillingCycle === 'yearly' ? 'year' : 'month' }}</div>
                        </div>

                        <!-- Billing Cycle Toggle -->
                        <div class="flex items-center justify-center gap-3 mb-6">
                            <span class="text-[var(--sand)] {{ $selectedBillingCycle === 'monthly' ? 'text-[var(--stone)] font-medium' : '' }}">Monthly</span>
                            <button wire:click="switchBillingCycle" 
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors {{ $selectedBillingCycle === 'yearly' ? 'bg-green-600' : 'bg-white/15' }}">
                                <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform {{ $selectedBillingCycle === 'yearly' ? 'translate-x-6' : 'translate-x-1' }}"></span>
                            </button>
                            <span class="text-[var(--sand)] {{ $selectedBillingCycle === 'yearly' ? 'text-[var(--stone)] font-medium' : '' }}">
                                Yearly <span class="text-green-400 text-sm">(10% discount)</span>
                            </span>
                        </div>

                        <div class="flex items-center justify-between mb-6">
                            <div class="text-lg text-[var(--stone)] font-bold">Total:</div>
                            <div class="text-3xl font-bold text-green-400">
                                R{{ number_format($selectedBillingCycle === 'yearly' ? $userSelectedYearlyTotal : $userSelectedMonthlyTotal, 2) }}
                            </div>
                            <div class="text-[var(--sand)]">/ {{ $selectedBillingCycle === 'yearly' ? 'year' : 'month' }}</div>
                        </div>

                        <div class="mt-6 text-center">
                            <button wire:click="$set('showConfirmModal', true)" 
                                class="px-6 py-3 bg-[var(--gold)] hover:bg-[var(--gold-soft)] text-[var(--stone)] rounded-lg transition-colors font-medium"
                                @if($selectedAdtCount + $selectedBigMachineCount < 1) disabled class="opacity-50 cursor-not-allowed" @endif>
                                Subscribe Now
                            </button>
                            @if($selectedAdtCount + $selectedBigMachineCount < 1)
                                <p class="text-red-400 text-sm mt-2">Please select at least one machine to subscribe.</p>
                            @endif
                        </div>

                        <!-- Confirmation Modal -->
                        @if($showConfirmModal)
                            <div class="fixed inset-0 bg-black/60 flex items-center justify-center z-50">
                                <div class="bg-[var(--ink-soft)] rounded-lg p-8 w-full max-w-md text-center border border-[var(--line)] text-[var(--stone)] shadow-lg">
                                    <h3 class="text-xl font-display font-semibold text-[var(--stone)] mb-4">Confirm Subscription</h3>
                                    <p class="text-[var(--sand)] mb-4">You are about to subscribe to <span class="font-bold">{{ $selectedAdtCount }} ADT</span> and <span class="font-bold">{{ $selectedBigMachineCount }} Big Machine</span> for a total of <span class="text-green-400 font-bold">R{{ number_format($selectedBillingCycle === 'yearly' ? $userSelectedYearlyTotal : $userSelectedMonthlyTotal, 2) }}</span> per {{ $selectedBillingCycle === 'yearly' ? 'year' : 'month' }}.</p>
                                    <div class="flex gap-4 justify-center">
                                        <button wire:click="subscribe" class="px-5 py-2 bg-green-600 hover:bg-green-700 text-[var(--stone)] rounded-lg font-medium">Confirm & Subscribe</button>
                                        <button wire:click="$set('showConfirmModal', false)" class="px-5 py-2 bg-white/10 hover:bg-white/20 text-[var(--stone)] rounded-lg font-medium">Cancel</button>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <!-- Recent Payments -->
            @if(count($recentPayments) > 0)
                <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg p-6">
                    <h2 class="text-xl font-bold text-[var(--stone)] mb-4">Recent Payments</h2>
                    <div class="space-y-3">
                        @foreach($recentPayments as $payment)
                            <div class="flex justify-between items-center p-3 bg-white/5 rounded-lg">
                                <div>
                                    <p class="text-[var(--stone)] font-medium">${{ number_format($payment['amount'], 2) }}</p>
                                    <p class="text-sm text-[var(--sand)]">{{ \Carbon\Carbon::parse($payment['created_at'])->format('M j, Y') }}</p>
                                </div>
                                <span class="px-3 py-1 rounded-full text-xs font-medium
                                    {{ $payment['status'] === 'succeeded' ? 'bg-green-600' : '' }}
                                    {{ $payment['status'] === 'pending' ? 'bg-yellow-600' : '' }}
                                    {{ $payment['status'] === 'failed' ? 'bg-red-600' : '' }}
                                    text-[var(--stone)]">
                                    {{ ucfirst($payment['status']) }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <!-- Right Column - Stats & Invoices -->
        <div class="space-y-6">
            <!-- Stats Card -->
            <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg p-6">
                <h3 class="text-lg font-semibold text-[var(--stone)] mb-4">Billing Stats</h3>
                <div class="space-y-4">
                    <div>
                        <p class="text-sm text-[var(--sand)]">Total Paid</p>
                        <p class="text-2xl font-bold text-[var(--stone)]">${{ number_format($totalPaid, 2) }}</p>
                    </div>
                    @if($currentSubscription)
                        <div>
                            <p class="text-sm text-[var(--sand)]">Current Plan</p>
                            <p class="text-lg font-semibold text-[var(--stone)]">{{ $currentPlan->name }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-[var(--sand)]">Billing Cycle</p>
                            <p class="text-lg font-semibold text-[var(--stone)]">{{ ucfirst($currentSubscription['billing_cycle']) }}</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Recent Invoices -->
            @if(count($recentInvoices) > 0)
                <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-[var(--stone)] mb-4">Recent Invoices</h3>
                    <div class="space-y-3">
                        @foreach($recentInvoices as $invoice)
                            <div class="p-3 bg-white/5 rounded-lg">
                                <div class="flex justify-between items-start mb-2">
                                    <p class="text-[var(--stone)] font-medium">{{ $invoice['invoice_number'] }}</p>
                                    <span class="px-2 py-1 rounded text-xs font-medium
                                        {{ $invoice['status'] === 'paid' ? 'bg-green-600' : '' }}
                                        {{ $invoice['status'] === 'open' ? 'bg-yellow-600' : '' }}
                                        {{ $invoice['status'] === 'void' ? 'bg-red-600' : '' }}
                                        text-[var(--stone)]">
                                        {{ ucfirst($invoice['status']) }}
                                    </span>
                                </div>
                                <p class="text-sm text-[var(--sand)] mb-2">${{ number_format($invoice['total'], 2) }}</p>
                                <p class="text-xs text-[var(--sand)]/70">{{ \Carbon\Carbon::parse($invoice['created_at'])->format('M j, Y') }}</p>
                                @if($invoice['pdf_url'])
                                    <button wire:click="downloadInvoice({{ $invoice['id'] }})" 
                                        class="mt-2 text-xs text-[var(--gold)] hover:text-[var(--gold-soft)]">
                                        Download PDF
                                    </button>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- Plan Selector Modal -->
    @if($showPlanSelector && $currentSubscription)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" wire:click="togglePlanSelector">
            <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg p-6 max-w-4xl w-full mx-4" x-on:click.stop>
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold text-[var(--stone)]">Change Your Plan</h2>
                    <button wire:click="togglePlanSelector" class="text-[var(--sand)] hover:text-[var(--stone)]">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <!-- Billing Cycle Toggle -->
                <div class="flex items-center justify-center gap-3 mb-6">
                    <span class="text-[var(--sand)] {{ $selectedBillingCycle === 'monthly' ? 'text-[var(--stone)] font-medium' : '' }}">Monthly</span>
                    <button wire:click="switchBillingCycle" 
                        class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors {{ $selectedBillingCycle === 'yearly' ? 'bg-green-600' : 'bg-white/15' }}">
                        <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform {{ $selectedBillingCycle === 'yearly' ? 'translate-x-6' : 'translate-x-1' }}"></span>
                    </button>
                    <span class="text-[var(--sand)] {{ $selectedBillingCycle === 'yearly' ? 'text-[var(--stone)] font-medium' : '' }}">
                        Yearly <span class="text-green-400 text-sm">(Save up to 20%)</span>
                    </span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    @foreach($availablePlans as $plan)
                        <div class="bg-white/5 border-2 {{ $selectedPlanId === $plan['id'] ? 'border-[var(--gold)]' : 'border-[var(--line)]' }} rounded-lg p-6 hover:border-[var(--gold)] transition-colors cursor-pointer"
                            wire:click="selectPlan({{ $plan['id'] }})">
                            <h3 class="text-xl font-bold text-[var(--stone)] mb-2">{{ $plan['name'] }}</h3>
                            <div class="mb-4">
                                <span class="text-3xl font-bold text-[var(--stone)]">
                                    R{{ number_format($selectedBillingCycle === 'yearly' ? $plan['yearly_price'] : $plan['price'], 2) }}
                                </span>
                                <span class="text-[var(--sand)]">/ {{ $selectedBillingCycle === 'yearly' ? 'year' : 'month' }}</span>
                            </div>
                            <p class="text-[var(--sand)] text-sm mb-4">{{ $plan['description'] }}</p>
                        </div>
                    @endforeach
                </div>

                <div class="text-center">
                    <button wire:click="subscribe" 
                        @if(!$selectedPlanId) disabled @endif
                        class="px-6 py-3 bg-[var(--gold)] hover:bg-[var(--gold-soft)] disabled:bg-white/10 disabled:cursor-not-allowed text-[var(--ink)] rounded-lg transition-colors font-display font-semibold">
                        Change to Selected Plan
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>

@extends('layouts.marketing')
@section('title', 'Pricing')
@section('content')
<div class="max-w-4xl mx-auto py-12 px-4">
    <h1 class="text-4xl font-bold text-[var(--gold)] mb-2">Pricing</h1>
    <p class="text-[var(--sand)] mb-10">
        Simple, usage-based pricing. Pay per machine you actually track — no seat limits, no fake tiers.
    </p>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-10">
        <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-xl p-8">
            <h2 class="text-xl font-display font-semibold text-[var(--stone)] mb-1">ADT</h2>
            <p class="text-[var(--sand)] text-sm mb-4">Articulated dump trucks and similar haul vehicles.</p>
            <p class="text-4xl font-bold text-[var(--stone)]">
                R1,500<span class="text-base font-normal text-[var(--sand)]">/machine/mo</span>
            </p>
        </div>
        <div class="bg-[var(--ink-soft)] border border-[var(--gold)] rounded-xl p-8">
            <h2 class="text-xl font-display font-semibold text-[var(--stone)] mb-1">Big Machine</h2>
            <p class="text-[var(--sand)] text-sm mb-4">Excavators, dozers, drills, and other heavy equipment.</p>
            <p class="text-4xl font-bold text-[var(--stone)]">
                R2,500<span class="text-base font-normal text-[var(--sand)]">/machine/mo</span>
            </p>
        </div>
    </div>

    <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-xl p-6 mb-10">
        <ul class="text-[var(--sand)] text-sm space-y-2">
            <li>Unlimited mine areas, users, and geofences on every plan</li>
            <li>Real-time fleet tracking, fuel management, and maintenance alerts included</li>
            <li>AI-driven route and cost optimization included</li>
            <li>Pay yearly and save 10% on your total</li>
        </ul>
    </div>

    <div class="text-center">
        <a href="{{ route('register') }}" class="inline-block bg-[var(--gold)] hover:bg-[var(--gold-soft)] text-[var(--ink)] font-display font-semibold px-8 py-3.5 rounded-lg transition-colors">
            Start free trial
        </a>
        <p class="text-[var(--sand)] text-xs mt-3">
            You'll only be billed once you add machines to your team. Manage or cancel anytime from the Billing page in your account menu.
        </p>
    </div>
</div>
@endsection

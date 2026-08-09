@extends('layouts.app')
@section('title', 'Platform Capabilities')
@section('content')
<div class="max-w-5xl mx-auto py-12 px-4">
    <h1 class="text-4xl font-bold text-[var(--gold)] mb-6">Platform Capabilities</h1>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-xl p-6 shadow-lg flex flex-col gap-4">
            <h2 class="text-2xl font-semibold text-[var(--stone)]">Multi-Site Management</h2>
            <p class="text-[var(--sand)]">Manage unlimited mining sites, teams, and users from a single dashboard with role-based access.</p>
        </div>
        <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-xl p-6 shadow-lg flex flex-col gap-4">
            <h2 class="text-2xl font-semibold text-[var(--stone)]">API & Integrations</h2>
            <p class="text-[var(--sand)]">RESTful API, webhooks, and integrations with popular mining and ERP software.</p>
        </div>
        <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-xl p-6 shadow-lg flex flex-col gap-4">
            <h2 class="text-2xl font-semibold text-[var(--stone)]">Custom Reporting</h2>
            <p class="text-[var(--sand)]">Build custom dashboards and export data for compliance, audits, and operational analysis.</p>
        </div>
        <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-xl p-6 shadow-lg flex flex-col gap-4">
            <h2 class="text-2xl font-semibold text-[var(--stone)]">24/7 Support & Training</h2>
            <p class="text-[var(--sand)]">Access to a dedicated support team, onboarding, and training resources for your staff.</p>
        </div>
    </div>
    <div class="mt-10 text-center">
        <a href="{{ route('register') }}" class="inline-block bg-[var(--gold)] hover:bg-[var(--gold-soft)] text-[var(--ink)] font-display font-semibold px-8 py-3.5 rounded-lg transition-colors">
            Start free trial
        </a>
    </div>
</div>
@endsection

@extends('layouts.app')
@section('title', 'Platform Features')
@section('content')
<div class="max-w-5xl mx-auto py-12 px-4">
    <h1 class="text-4xl font-bold text-[var(--gold)] mb-6">Platform Features</h1>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-xl p-6 shadow-lg flex flex-col gap-4">
            <h2 class="text-2xl font-semibold text-[var(--stone)]">Real-Time Fleet Tracking</h2>
            <p class="text-[var(--sand)]">Monitor all machines and vehicles live on the map, view status, location, and performance metrics instantly.</p>
            <a href="{{ route('core-features.fleet-tracking') }}" class="text-[var(--gold)] hover:text-[var(--gold-soft)] hover:underline">Learn more</a>
        </div>
        <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-xl p-6 shadow-lg flex flex-col gap-4">
            <h2 class="text-2xl font-semibold text-[var(--stone)]">Mine Area Management</h2>
            <p class="text-[var(--sand)]">Define, edit, and monitor mine areas with geofencing, production targets, and shift management.</p>
            <a href="{{ route('register') }}" class="text-[var(--gold)] hover:text-[var(--gold-soft)] hover:underline">Start free trial</a>
        </div>
        <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-xl p-6 shadow-lg flex flex-col gap-4">
            <h2 class="text-2xl font-semibold text-[var(--stone)]">Maintenance & Alerts</h2>
            <p class="text-[var(--sand)]">Automated maintenance scheduling, breakdown alerts, and service history for every asset.</p>
            <a href="{{ route('core-features.maintenance') }}" class="text-[var(--gold)] hover:text-[var(--gold-soft)] hover:underline">Learn more</a>
        </div>
        <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-xl p-6 shadow-lg flex flex-col gap-4">
            <h2 class="text-2xl font-semibold text-[var(--stone)]">Fuel & Cost Management</h2>
            <p class="text-[var(--sand)]">Track fuel usage, costs, and efficiency. Generate reports for cost optimization and compliance.</p>
            <a href="{{ route('core-features.fuel') }}" class="text-[var(--gold)] hover:text-[var(--gold-soft)] hover:underline">Learn more</a>
        </div>
    </div>
</div>
@endsection

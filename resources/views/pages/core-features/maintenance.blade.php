@extends('layouts.app')
@section('title', 'Maintenance & Alerts')
@section('content')
<div class="max-w-4xl mx-auto py-12 px-4">
    <h1 class="text-4xl font-bold text-[var(--gold)] mb-6">Maintenance & Alerts</h1>
    <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-xl p-8 shadow-lg flex flex-col gap-6">
        <p class="text-[var(--sand)] text-lg">Automate maintenance schedules, receive instant breakdown alerts, and track service history for every asset. Reduce downtime and extend equipment life.</p>
        <ul class="list-disc pl-6 text-[var(--sand)] space-y-2">
            <li>Automated service reminders and logs</li>
            <li>Breakdown and warning alerts</li>
            <li>Maintenance cost tracking</li>
            <li>Exportable service history</li>
        </ul>
        <div>
            <a href="{{ route('register') }}" class="inline-block bg-[var(--gold)] hover:bg-[var(--gold-soft)] text-[var(--ink)] font-display font-semibold px-6 py-2.5 rounded-lg transition-colors">
                Start free trial
            </a>
        </div>
    </div>
</div>
@endsection

@extends('layouts.marketing')
@section('title', 'Fuel & Cost Management')
@section('content')
<div class="max-w-4xl mx-auto py-12 px-4">
    <h1 class="text-4xl font-bold text-[var(--gold)] mb-6">Fuel & Cost Management</h1>
    <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-xl p-8 shadow-lg flex flex-col gap-6">
        <p class="text-[var(--sand)] text-lg">Track fuel usage, costs, and efficiency for every machine. Generate reports for cost optimization, compliance, and sustainability.</p>
        <ul class="list-disc pl-6 text-[var(--sand)] space-y-2">
            <li>Fuel dispensing and consumption logs</li>
            <li>Cost per machine and per area</li>
            <li>Efficiency and loss detection</li>
            <li>Exportable fuel and cost reports</li>
        </ul>
        <div>
            <a href="{{ route('register') }}" class="inline-block bg-[var(--gold)] hover:bg-[var(--gold-soft)] text-[var(--ink)] font-display font-semibold px-6 py-2.5 rounded-lg transition-colors">
                Start free trial
            </a>
        </div>
    </div>
</div>
@endsection

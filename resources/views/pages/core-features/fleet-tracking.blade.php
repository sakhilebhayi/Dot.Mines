@extends('layouts.marketing')
@section('title', 'Fleet Tracking')
@section('content')
<div class="max-w-4xl mx-auto py-12 px-4">
    <h1 class="text-4xl font-bold text-[var(--gold)] mb-6">Real-Time Fleet Tracking</h1>
    <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-xl p-8 shadow-lg flex flex-col gap-6">
        <p class="text-[var(--sand)] text-lg">Monitor every machine and vehicle in real time. See live locations, status, and performance metrics on an interactive map. Instantly identify issues, optimize routes, and improve productivity.</p>
        <ul class="list-disc pl-6 text-[var(--sand)] space-y-2">
            <li>Live map with machine icons and status colors</li>
            <li>Click any machine for detailed info and history</li>
            <li>Filter by status, type, or location</li>
            <li>Mobile-friendly and fast updates</li>
        </ul>
        <div>
            <a href="{{ route('register') }}" class="inline-block bg-[var(--gold)] hover:bg-[var(--gold-soft)] text-[var(--ink)] font-display font-semibold px-6 py-2.5 rounded-lg transition-colors">
                Start free trial
            </a>
        </div>
    </div>
</div>
@endsection

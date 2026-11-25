@extends('errors._ecosystem-layout', ['code' => 503])

@section('icon')
    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#8a5f18" stroke-width="1.75"><path d="M14.7 6.3a4 4 0 00-5.4 5.4L4 17v3h3l5.3-5.3a4 4 0 005.4-5.4l-2.65 2.65a1.5 1.5 0 01-2.1-2.1L14.7 6.3z" stroke-linecap="round" stroke-linejoin="round"/></svg>
@endsection

@section('title', 'Dot.Mines is not available right now')
@section('message', $message ?? 'Dot.Mines is not available right now.')

@section('actions')
    <a href="{{ url()->current() }}" class="press" style="padding: 12px 24px; background: var(--signal); border: 1px solid var(--signal); color: #211a14; font-family: var(--font-display); font-weight: 600; font-size: 15px; border-radius: 2px; text-decoration: none;">Try again</a>
@endsection

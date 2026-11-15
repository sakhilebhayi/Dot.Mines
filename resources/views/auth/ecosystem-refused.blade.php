{{--
    Shown when a sign-in handed over by InfoDot cannot be completed (R-52 section 6). It always says what happened and
    what to do next, and never echoes anything from the request. Own layout on purpose (no Vite bundle), like the
    error pages, so it renders even if the build is missing.
--}}
@extends('errors._ecosystem-layout')

@section('icon')
    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#d99e2b" stroke-width="1.75"><circle cx="12" cy="12" r="9" stroke-linecap="round"/><path d="M12 7v6" stroke-linecap="round"/><circle cx="12" cy="16.5" r="1.1" fill="#d99e2b" stroke="none"/></svg>
@endsection

@section('title', $title)
@section('message', $message)

@section('actions')
    <a href="{{ route('login') }}" class="press" style="padding: 12px 24px; background: var(--signal); border: 1px solid var(--signal); color: #211a14; font-family: var(--font-display); font-weight: 600; font-size: 15px; border-radius: 2px; text-decoration: none;">Sign in to Dot.Mines</a>
    @if ($retryUrl)
        <a href="{{ $retryUrl }}" class="press link-underline" style="padding: 12px 24px; color: var(--ink); font-size: 15px; text-decoration: none;">Try again from InfoDot</a>
    @elseif ($infodotUrl)
        <a href="{{ $infodotUrl }}" class="press link-underline" style="padding: 12px 24px; color: var(--ink); font-size: 15px; text-decoration: none;">Back to InfoDot</a>
    @endif
    <p class="font-mono" style="flex-basis: 100%; margin: 14px 0 0; font-size: 12px; color: var(--text-quiet);">Reference: {{ $reason }}</p>
@endsection

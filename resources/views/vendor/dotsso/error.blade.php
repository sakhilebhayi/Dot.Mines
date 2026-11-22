{{-- R-54: every central sign-in failure ends here, in words, with one next step and no automatic redirect. The two "no
     account" reasons are written for Dot.Mines: accounts are made by invitation, never by signing in. --}}
@php
    $noAccount = in_array($code, ['not_provisioned', 'provision_refused'], true);
    if ($noAccount) {
        $headline = 'There is no Dot.Mines account for this email yet';
        $body = 'Dot.Mines accounts are created by invitation. Ask your Dot.Mines administrator to invite you with the email address you use on InfoDot, then sign in again. Nothing was created and nothing on any mine team was changed.';
        $retry = false;
    }
@endphp
<x-guest-layout>
    <x-authentication-card>
        <x-slot name="logo">
            <x-authentication-card-logo />
        </x-slot>

        <div role="alert">
            <h1 class="font-display text-xl font-semibold text-[var(--stone)]">{{ $headline }}</h1>
            <p class="mt-2 text-sm leading-6 text-[var(--sand)]">{{ $body }}</p>
            <div class="mt-6 flex flex-wrap items-center gap-3">
                @if ($retry)<a href="{{ $retryUrl }}" class="inline-flex items-center justify-center min-h-[44px] px-6 py-2.5 bg-[var(--gold)] rounded-lg font-display font-semibold text-sm text-[var(--ink)] hover:bg-[var(--gold-soft)] focus:outline-none focus:ring-2 focus:ring-[var(--gold)] focus:ring-offset-2 focus:ring-offset-[var(--ink-soft)]">Start again</a>@endif
                <a href="{{ $homeUrl }}" class="inline-flex items-center min-h-[44px] underline text-sm text-[var(--gold)] hover:text-[var(--gold-soft)] rounded-md focus:outline-none focus:ring-2 focus:ring-[var(--gold)]">Back to Dot.Mines</a>
            </div>
            <p class="mt-4 font-mono text-xs text-[var(--sand)]">Reference: {{ $code }}</p>
        </div>
    </x-authentication-card>
</x-guest-layout>

{{-- R-54: the claim page, in the Dot.Mines control room (ink and gold, the sign-in card). Shown when a Dot.Mines account
     already uses the email address of the InfoDot sign-in. Nothing on the account is replaced or wiped; the emailed code
     proves the address. Variables come from the shared package. --}}
<x-guest-layout>
    <x-authentication-card>
        <x-slot name="logo">
            <x-authentication-card-logo />
        </x-slot>

        <h1 class="font-display text-xl font-semibold text-[var(--stone)]">Confirm it is you</h1>
        <p class="mt-2 text-sm leading-6 text-[var(--sand)]">A Dot.Mines account already uses <strong class="text-[var(--stone)]">{{ $maskedEmail }}</strong>. We sent a {{ $codeLength }}-digit code to that address. Enter it to connect the two. Your password, two-step sign-in, mine teams, roles and sessions stay exactly as they are.</p>

        @if (session('dotsso_status'))
            <p class="mt-4 text-sm font-medium text-emerald-400" role="status">{{ session('dotsso_status') }}</p>
        @endif

        <form method="POST" action="{{ $verifyUrl }}" class="mt-6" novalidate>
            @csrf
            <x-label for="code" value="Code from the email" />
            <x-input id="code" name="code" type="text" inputmode="numeric" pattern="[0-9]*" autocomplete="one-time-code" maxlength="{{ $codeLength }}" required autofocus
                     aria-describedby="code-hint code-error" class="block mt-1 w-full min-h-[44px] tracking-[0.3em] text-lg" />
            <p id="code-hint" class="mt-2 text-xs text-[var(--sand)]">It works for {{ $minutes }} minutes and once. You have {{ $maxAttempts }} tries.</p>
            <p id="code-error" class="mt-2 text-sm text-red-400" role="alert">{{ $errors->first('code') }}</p>

            <x-button class="mt-4 w-full justify-center min-h-[44px]">Confirm and continue</x-button>
        </form>

        <div class="mt-4 flex flex-wrap items-center gap-x-6">
            <form method="POST" action="{{ $resendUrl }}">@csrf
                <button type="submit" class="inline-flex items-center min-h-[44px] underline text-sm text-[var(--gold)] hover:text-[var(--gold-soft)] rounded-md focus:outline-none focus:ring-2 focus:ring-[var(--gold)]">Send a new code</button>
            </form>
            <form method="POST" action="{{ $cancelUrl }}">@csrf
                <button type="submit" class="inline-flex items-center min-h-[44px] underline text-sm text-[var(--sand)] hover:text-[var(--stone)] rounded-md focus:outline-none focus:ring-2 focus:ring-[var(--gold)]">Cancel</button>
            </form>
        </div>
    </x-authentication-card>
</x-guest-layout>

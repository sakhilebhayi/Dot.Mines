You're invited to the {{ $team->name }} Operations Feed!

{{ config('app.name') }} — Feed Onboarding Invitation

Hello {{ $invitee->name }},

You have been invited to join the {{ $team->name }} Operations Feed on {{ config('app.name') }} — the centralised platform for mine communications, shift updates, and real-time operations.

@if (!empty($personalMessage))
Message from your team admin:
"{{ $personalMessage }}"

@endif
This replaces WhatsApp for all team communications. Sign in to get started.

SIGN IN: {{ config('app.url') }}/login

---
@if (!empty($unsubscribeUrl))
To opt out of these onboarding invitations: {{ $unsubscribeUrl }}
@endif
© {{ date('Y') }} {{ config('app.name') }}. All rights reserved.

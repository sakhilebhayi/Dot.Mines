Team Invitation — {{ $invitation->team?->name ?? config('app.name') }}

You have been invited to join the {{ $invitation->team?->name ?? config('app.name') }} team on {{ config('app.name') }}.

@if (Laravel\Fortify\Features::enabled(Laravel\Fortify\Features::registration()))
Don't have an account? Create one first: {{ route('register') }}
Then return to accept this invitation.

Already have an account? Accept below:
@else
Accept the invitation by clicking the link below:
@endif

ACCEPT INVITATION: {{ $acceptUrl }}

---
If you did not expect to receive this invitation, you may safely discard this email.

© {{ date('Y') }} {{ config('app.name') }}. All rights reserved.

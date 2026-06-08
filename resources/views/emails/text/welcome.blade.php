Welcome aboard, {{ $user->name }}!

{{ config('app.name') }} — Your account is ready.

Thanks for joining {{ config('app.name') }}. You can now sign in and start monitoring your fleet, tracking operations, and generating reports from one dashboard.

Go to Dashboard: {{ config('app.url') }}

---
Fleet Dashboard: Monitor all machines and live telemetry in real time.
Reports: Generate production, maintenance, and cost reports on demand.
Operations Feed: Stay in sync with shift updates, alerts, and team activity.

---
If you have any questions, just reply to this email.

Manage notification preferences: {{ route('settings') }}
© {{ date('Y') }} {{ config('app.name') }}. All rights reserved.

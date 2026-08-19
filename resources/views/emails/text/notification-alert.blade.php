{{ strtoupper($notification->alert_level ?? 'INFO') }}: {{ $notification->title }}

{{ config('app.name') }} Alert Notification

Hello {{ $recipient->name }},

A new alert has been triggered that requires your attention.

Alert: {{ $notification->title }}
Level: {{ strtoupper($notification->alert_level ?? 'info') }}
@if (!empty($notification->body))
Details: {{ $notification->body }}
@endif
Time: {{ $notification->created_at->format('D, d M Y H:i T') }}

@if (!empty($notification->action_url))
VIEW DETAILS: {{ $notification->action_url }}
@endif

---
You are receiving this notification because you have a management role on your team.
To adjust your notification preferences, sign in to your account settings.

@if (!empty($unsubscribeUrl))
Unsubscribe from alert notifications: {{ $unsubscribeUrl }}
@endif
© {{ date('Y') }} {{ config('app.name') }}. All rights reserved.

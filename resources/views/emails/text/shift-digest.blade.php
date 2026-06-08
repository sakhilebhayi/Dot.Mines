{{ $teamName }} — Shift Digest: {{ $shiftLabel }}

{{ config('app.name') }} Shift Summary

Shift: {{ $shiftLabel }}
Team: {{ $teamName }}

------ OPERATIONS SUMMARY ------
@if (!empty($stats['active_machines']))
Active Machines: {{ $stats['active_machines'] }}
@endif
@if (!empty($stats['fuel_dispensed']))
Fuel Dispensed: {{ $stats['fuel_dispensed'] }}L
@endif
@if (!empty($stats['total_tonnage']))
Total Tonnage: {{ $stats['total_tonnage'] }}t
@endif
@if (!empty($stats['alerts_triggered']))
Alerts Triggered: {{ $stats['alerts_triggered'] }}
@endif
@if (!empty($stats['feed_posts']))
Feed Posts: {{ $stats['feed_posts'] }}
@endif

@if (!empty($topPosts))
------ TOP FEED POSTS ------
@foreach ($topPosts as $post)
• {{ $post['author'] ?? 'Team Member' }}: {{ Str::limit($post['content'] ?? '', 120) }}
@endforeach
@endif

@if (!empty($pendingApprovals))
------ PENDING APPROVALS ------
{{ count($pendingApprovals) }} post(s) are waiting for approval.
Review here: {{ route('feed') }}
@endif

---
View the full feed: {{ route('feed') }}

@if (!empty($unsubscribeUrl))
Unsubscribe from shift digests: {{ $unsubscribeUrl }}
@endif
© {{ date('Y') }} {{ config('app.name') }}. All rights reserved.

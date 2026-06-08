Your report is ready — {{ $report->title }}

{{ config('app.name') }} Report Delivery

Your report "{{ $report->title }}" has been generated and is ready to download.

@if (!empty($report->description))
Description: {{ $report->description }}
@endif

DOWNLOAD REPORT: {{ $downloadUrl }}

This download link will expire in 24 hours.

---
Questions? Contact us at {{ config('mail.addresses.support') }}
© {{ date('Y') }} {{ config('app.name') }}. All rights reserved.

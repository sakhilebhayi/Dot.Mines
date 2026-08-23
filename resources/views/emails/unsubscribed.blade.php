<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Unsubscribed — {{ config('app.name') }}</title>
    <style>
        body { margin: 0; font-family: system-ui, sans-serif; background: #211a14; color: #f4efe4; display: flex; min-height: 100vh; align-items: center; justify-content: center; }
        .card { background: #2c2319; border: 1px solid rgba(244, 239, 228, 0.12); border-radius: 12px; padding: 2.5rem; max-width: 26rem; text-align: center; }
        h1 { font-size: 1.25rem; margin: 0 0 0.75rem; }
        p { color: #c9b896; margin: 0 0 1.5rem; line-height: 1.5; }
        a { color: #d99e2b; text-decoration: none; }
    </style>
</head>
<body>
    <div class="card">
        <h1>You're unsubscribed</h1>
        <p>
            @if ($type === 'report_notifications')
                You will no longer receive report-ready emails from {{ config('app.name') }}.
            @else
                You will no longer receive alert notification emails from {{ config('app.name') }}.
            @endif
            You can turn them back on any time under Settings &rsaquo; Notifications.
        </p>
        <a href="{{ route('login') }}">Go to {{ config('app.name') }}</a>
    </div>
</body>
</html>

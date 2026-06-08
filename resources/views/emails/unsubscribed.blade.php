<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unsubscribed — {{ config('app.name') }}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #070d1a; color: #cbd5e1; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
        .card { background: #0f172a; border: 1px solid #1e293b; border-radius: 14px; max-width: 480px; width: 100%; padding: 40px 32px; text-align: center; }
        .logo { font-size: 22px; font-weight: 700; color: #f59e0b; margin-bottom: 24px; }
        .icon { font-size: 48px; margin-bottom: 16px; }
        h1 { color: #f1f5f9; font-size: 20px; margin: 0 0 12px; }
        p { color: #94a3b8; font-size: 14px; line-height: 1.6; margin: 0 0 24px; }
        .btn { display: inline-block; background: #1e40af; color: #fff; border-radius: 8px; padding: 12px 28px; font-size: 15px; font-weight: 600; text-decoration: none; }
        .btn:hover { background: #1d4ed8; }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo">{{ config('app.name') }}</div>
        <div class="icon">✓</div>
        <h1>You've been unsubscribed</h1>
        <p>You will no longer receive those emails. You can re-enable them at any time in your <a href="{{ route('login') }}" style="color:#f59e0b;">account notification settings</a>.</p>
        <a href="{{ route('login') }}" class="btn">Sign in to manage preferences</a>
    </div>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unsubscribe — {{ config('app.name') }}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #070d1a; color: #cbd5e1; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
        .card { background: #0f172a; border: 1px solid #1e293b; border-radius: 14px; max-width: 480px; width: 100%; padding: 40px 32px; text-align: center; }
        .logo { font-size: 22px; font-weight: 700; color: #f59e0b; margin-bottom: 24px; }
        h1 { color: #f1f5f9; font-size: 20px; margin: 0 0 12px; }
        p { color: #94a3b8; font-size: 14px; line-height: 1.6; margin: 0 0 24px; }
        .type-badge { display: inline-block; background: #1e293b; color: #f59e0b; border-radius: 6px; padding: 4px 12px; font-size: 13px; font-weight: 600; margin-bottom: 20px; text-transform: capitalize; }
        .btn { display: inline-block; background: #dc2626; color: #fff; border: none; border-radius: 8px; padding: 12px 28px; font-size: 15px; font-weight: 600; cursor: pointer; text-decoration: none; width: 100%; box-sizing: border-box; }
        .btn:hover { background: #b91c1c; }
        .cancel { display: block; margin-top: 14px; color: #64748b; font-size: 13px; text-decoration: none; }
        .cancel:hover { color: #94a3b8; }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo">{{ config('app.name') }}</div>
        <h1>Unsubscribe from emails</h1>
        <p>You are about to stop receiving the following email type for <strong style="color:#f1f5f9;">{{ $user->email }}</strong>:</p>
        <span class="type-badge">{{ str_replace('_', ' ', $type) }}</span>
        <form method="POST" action="{{ route('email.unsubscribe.handle') }}?{{ http_build_query(request()->query()) }}">
            @csrf
            <input type="hidden" name="user" value="{{ $user->id }}">
            <input type="hidden" name="type" value="{{ $type }}">
            <button type="submit" class="btn">Yes, unsubscribe me</button>
        </form>
        <a href="{{ route('login') }}" class="cancel">Cancel — take me to sign in</a>
    </div>
</body>
</html>

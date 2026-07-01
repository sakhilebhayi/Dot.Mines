<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $status ?? 500 }} – Error | {{ config('app.name', 'Mines') }}</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' fill='%23f59e0b' rx='15'/><path d='M20 45 L20 30 L35 37 L35 52 L20 45 M35 52 L50 45 L50 60 L35 67 L35 52 M50 60 L65 53 L65 68 L50 75 L50 60 M35 37 L50 30 L50 45 L35 52 L35 37 M50 45 L65 38 L65 53 L50 60 L50 45 M50 30 L65 23 L80 30 L65 38 L50 30' fill='%231e293b' stroke='%231e293b' stroke-width='2'/></svg>">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Figtree', sans-serif; background: #0f172a; color: #f1f5f9; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 2rem; }
        .container { max-width: 520px; text-align: center; }
        .code { font-size: 5rem; font-weight: 700; color: #f59e0b; line-height: 1; margin-bottom: 1.5rem; }
        h1 { font-size: 1.5rem; font-weight: 600; margin-bottom: 0.75rem; }
        p { color: #94a3b8; line-height: 1.6; margin-bottom: 1.25rem; }
        .ref { font-family: monospace; font-size: 0.75rem; color: #64748b; background: #1e293b; border: 1px solid #334155; padding: 0.4rem 0.75rem; border-radius: 0.375rem; display: inline-block; margin-bottom: 2rem; }
        .actions { display: flex; gap: 0.75rem; justify-content: center; flex-wrap: wrap; }
        .btn { display: inline-block; padding: 0.625rem 1.5rem; border-radius: 0.5rem; font-weight: 600; text-decoration: none; transition: background 0.15s; }
        .btn-primary { background: #f59e0b; color: #0f172a; }
        .btn-primary:hover { background: #d97706; }
        .btn-secondary { background: #1e293b; color: #e2e8f0; border: 1px solid #334155; }
        .btn-secondary:hover { background: #334155; }
    </style>
</head>
<body>
    <div class="container">
        <div class="code">{{ $status ?? 500 }}</div>
        <h1>
            @if(($status ?? 500) === 404)
                Page Not Found
            @elseif(($status ?? 500) === 403)
                Access Denied
            @else
                Something Went Wrong
            @endif
        </h1>
        <p>
            @if(($status ?? 500) === 404)
                The page you're looking for doesn't exist or has been moved.
            @elseif(($status ?? 500) === 403)
                You don't have permission to access this resource.
            @else
                We ran into an unexpected issue. Our team has been notified automatically.
            @endif
        </p>
        @if(!empty($error_ref) && ($status ?? 500) >= 500)
            <div class="ref">Reference: {{ $error_ref }}</div>
            <br>
        @endif
        <div class="actions">
            <a href="{{ url('/') }}" class="btn btn-primary">Go to Dashboard</a>
            <a href="javascript:history.back()" class="btn btn-secondary">Go Back</a>
        </div>
    </div>
</body>
</html>

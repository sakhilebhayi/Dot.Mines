@extends('emails.layout')

@section('title', 'Welcome to ' . config('app.name'))

@section('banner')
    <h1 style="color:#f1f5f9;font-size:22px;font-weight:700;margin:0 0 6px;letter-spacing:-0.02em;">
        Welcome aboard, {{ $user->name }}!
    </h1>
    <p style="color:#94a3b8;font-size:14px;margin:0;line-height:1.5;">
        Your account is ready. Let's get started.
    </p>
@endsection

@section('content')
    <p style="color:#e2e8f0;font-size:15px;line-height:1.7;margin:0 0 20px;">
        Thanks for joining <strong style="color:#f1f5f9;">{{ config('app.name') }}</strong>. You can now sign in and start
        monitoring your fleet, tracking operations across sites, and generating reports — all from one dashboard.
    </p>

    <div style="text-align:center;margin:32px 0;">
        <a href="{{ config('app.url') }}"
           style="display:inline-block;background-color:#f59e0b;color:#0f172a;text-decoration:none;padding:13px 36px;border-radius:8px;font-size:15px;font-weight:700;letter-spacing:0.01em;">
            Go to Dashboard
        </a>
    </div>

    <table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation" style="margin:24px 0 0;border-top:1px solid #1e293b;padding-top:20px;">
        <tr>
            <td style="padding:12px 0;vertical-align:top;width:32px;">
                <div style="width:28px;height:28px;background-color:rgba(245,158,11,0.12);border-radius:6px;text-align:center;line-height:28px;font-size:14px;">📊</div>
            </td>
            <td style="padding:12px 0 12px 12px;">
                <p style="color:#f1f5f9;font-size:14px;font-weight:600;margin:0 0 2px;">Fleet Dashboard</p>
                <p style="color:#94a3b8;font-size:13px;margin:0;line-height:1.5;">Monitor all machines, utilisation rates, and live telemetry in real time.</p>
            </td>
        </tr>
        <tr>
            <td style="padding:12px 0;vertical-align:top;width:32px;border-top:1px solid #1e293b;">
                <div style="width:28px;height:28px;background-color:rgba(16,185,129,0.12);border-radius:6px;text-align:center;line-height:28px;font-size:14px;">📋</div>
            </td>
            <td style="padding:12px 0 12px 12px;border-top:1px solid #1e293b;">
                <p style="color:#f1f5f9;font-size:14px;font-weight:600;margin:0 0 2px;">Reports</p>
                <p style="color:#94a3b8;font-size:13px;margin:0;line-height:1.5;">Generate production, maintenance, and cost reports on demand or on a schedule.</p>
            </td>
        </tr>
        <tr>
            <td style="padding:12px 0 0;vertical-align:top;width:32px;border-top:1px solid #1e293b;">
                <div style="width:28px;height:28px;background-color:rgba(59,130,246,0.12);border-radius:6px;text-align:center;line-height:28px;font-size:14px;">⚡</div>
            </td>
            <td style="padding:12px 0 0 12px;border-top:1px solid #1e293b;">
                <p style="color:#f1f5f9;font-size:14px;font-weight:600;margin:0 0 2px;">Operations Feed</p>
                <p style="color:#94a3b8;font-size:13px;margin:0;line-height:1.5;">Stay in sync with shift updates, alerts, and team activity — all in one place.</p>
            </td>
        </tr>
    </table>

    <p style="color:#64748b;font-size:13px;line-height:1.6;margin:24px 0 0;">
        If you have any questions, just reply to this email and our team will be happy to help.
    </p>
@endsection

@section('footer_note')
    <a href="{{ route('settings') }}" style="color:#64748b;text-decoration:none;">Manage notification preferences</a>
@endsection


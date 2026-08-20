@extends('emails.layout')

@section('title', 'Welcome to '.config('app.name'))

@section('banner')
    <p style="margin:0 0 4px;font-size:12px;letter-spacing:0.08em;text-transform:uppercase;color:#c9b896;">Welcome</p>
    <h1 style="margin:0;font-size:20px;line-height:1.3;color:#f4efe4;">Welcome aboard, {{ $user->name }}!</h1>
@endsection

@section('content')
    <p style="margin:0 0 16px;font-size:14px;line-height:1.6;color:#c9b896;">
        Thanks for creating an account. You can sign in and start using the dashboard to monitor your fleet, track production, and generate operational reports.
    </p>

    <table cellpadding="0" cellspacing="0" border="0" role="presentation" style="margin:0 auto 8px;">
        <tr>
            <td style="background-color:#d99e2b;border-radius:8px;">
                <a href="{{ route('dashboard') }}" style="display:inline-block;padding:12px 28px;font-size:14px;font-weight:700;color:#211a14;text-decoration:none;">Open Dashboard</a>
            </td>
        </tr>
    </table>

    <p style="margin:16px 0 0;font-size:13px;line-height:1.6;color:#a89a7c;">
        If you have any questions, just reply to this email and we'll help.
    </p>
@endsection

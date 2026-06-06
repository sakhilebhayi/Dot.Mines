@extends('emails.layout')

@section('title', 'You\'ve been invited to join ' . $invitation->team->name)

@section('banner')
    <h1 style="color:#f1f5f9;font-size:20px;font-weight:700;margin:0 0 6px;letter-spacing:-0.02em;">
        You're invited to join a team
    </h1>
    <p style="color:#94a3b8;font-size:14px;margin:0;line-height:1.5;">
        {{ $invitation->team->name }} has invited you to collaborate on {{ config('app.name') }}.
    </p>
@endsection

@section('content')
    <p style="color:#e2e8f0;font-size:15px;line-height:1.7;margin:0 0 24px;">
        You have been invited to join the
        <strong style="color:#f1f5f9;">{{ $invitation->team->name }}</strong>
        team on {{ config('app.name') }}.
    </p>

    @if (Laravel\Fortify\Features::enabled(Laravel\Fortify\Features::registration()))
        <div style="background-color:#1e293b;border:1px solid #334155;border-radius:8px;padding:16px 20px;margin:0 0 20px;">
            <p style="color:#94a3b8;font-size:13px;line-height:1.6;margin:0 0 12px;">
                Don't have an account yet? Create one first, then return to accept the invitation.
            </p>
            <a href="{{ route('register') }}"
               style="display:inline-block;background-color:#1e293b;color:#f1f5f9;text-decoration:none;padding:10px 24px;border-radius:7px;font-size:14px;font-weight:600;border:1px solid #475569;">
                Create Account
            </a>
        </div>

        <p style="color:#94a3b8;font-size:14px;line-height:1.6;margin:0 0 20px;">
            Already have an account? Accept the invitation below:
        </p>
    @else
        <p style="color:#94a3b8;font-size:14px;line-height:1.6;margin:0 0 20px;">
            Accept the invitation by clicking the button below:
        </p>
    @endif

    <div style="text-align:center;margin:0 0 28px;">
        <a href="{{ $acceptUrl }}"
           style="display:inline-block;background-color:#f59e0b;color:#0f172a;text-decoration:none;padding:13px 36px;border-radius:8px;font-size:15px;font-weight:700;letter-spacing:0.01em;">
            Accept Invitation
        </a>
    </div>

    <p style="color:#64748b;font-size:13px;line-height:1.6;margin:0;border-top:1px solid #1e293b;padding-top:20px;">
        If you did not expect to receive an invitation to this team, you may safely discard this email.
    </p>
@endsection

@section('footer_note')
    This invitation was sent on behalf of <strong>{{ $invitation->team->name }}</strong>.
@endsection


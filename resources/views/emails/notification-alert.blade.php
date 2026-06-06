@extends('emails.layout')

@php
    $levelColors = [
        'critical' => '#dc2626',
        'high'     => '#ea580c',
        'warning'  => '#d97706',
        'info'     => '#3b82f6',
    ];
    $levelColor = $levelColors[$notification->alert_level] ?? '#3b82f6';
    $levelLabel = strtoupper($notification->alert_level);
    $data = $notification->data ?? [];
@endphp

@section('title', $notification->title)

@section('header_badge')
    <span style="display:inline-block;background-color:{{ $levelColor }};color:#fff;font-size:11px;font-weight:700;letter-spacing:0.08em;padding:3px 10px;border-radius:4px;text-transform:uppercase;">
        {{ $levelLabel }}
    </span>
@endsection

@section('banner')
    <h1 style="color:#f1f5f9;font-size:20px;font-weight:700;margin:0 0 6px;letter-spacing:-0.02em;">
        {{ $notification->title }}
    </h1>
    <p style="color:#94a3b8;font-size:14px;margin:0;line-height:1.5;">
        {{ $notification->message }}
    </p>
@endsection

@section('content')
    <p style="color:#e2e8f0;font-size:15px;line-height:1.7;margin:0 0 20px;">
        Hi {{ $recipient->name }}, here is a notification from your
        <strong style="color:#f1f5f9;">{{ config('app.name') }}</strong> operations platform.
    </p>

    {{-- Detail table --}}
    @if(!empty($data))
        <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:0 0 24px;">
            @foreach($data as $key => $value)
                @if(!is_array($value) && $value !== null && $value !== '')
                    <tr>
                        <td style="padding:8px 12px 8px 0;color:#94a3b8;font-size:13px;font-weight:600;white-space:nowrap;text-transform:capitalize;vertical-align:top;border-bottom:1px solid #1e293b;">
                            {{ str_replace('_', ' ', $key) }}
                        </td>
                        <td style="padding:8px 0 8px 12px;color:#e2e8f0;font-size:13px;vertical-align:top;border-bottom:1px solid #1e293b;">
                            {{ $value }}
                        </td>
                    </tr>
                @endif
            @endforeach
        </table>
    @endif

    {{-- CTA button --}}
    @if($notification->action_url)
        <div style="text-align:center;margin:0 0 28px;">
            <a href="{{ rtrim(config('app.url'), '/') . $notification->action_url }}"
               style="display:inline-block;background-color:{{ $levelColor }};color:#fff;text-decoration:none;padding:13px 36px;border-radius:8px;font-size:15px;font-weight:700;letter-spacing:0.01em;">
                View Details
            </a>
        </div>
    @endif

    <p style="color:#64748b;font-size:13px;line-height:1.6;margin:0;border-top:1px solid #1e293b;padding-top:20px;">
        You are receiving this notification because you have a management role on your team.
        Notification preferences can be adjusted in your account settings.
    </p>
@endsection

@section('footer_note')
    Sent at {{ $notification->created_at->format('D, d M Y H:i T') }}
@endsection

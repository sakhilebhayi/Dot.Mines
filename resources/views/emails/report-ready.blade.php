@extends('emails.layout')

@section('title', 'Your report is ready')

@section('banner')
    <table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation">
        <tr>
            <td style="vertical-align:middle;padding-right:16px;">
                <h1 style="color:#f1f5f9;font-size:20px;font-weight:700;margin:0 0 4px;letter-spacing:-0.02em;">
                    Your Report is Ready
                </h1>
                <p style="color:#94a3b8;font-size:14px;margin:0;line-height:1.5;">
                    {{ $report->title }}
                </p>
            </td>
            <td style="vertical-align:middle;text-align:right;white-space:nowrap;">
                <span style="display:inline-block;background-color:rgba(16,185,129,0.15);color:#34d399;font-size:11px;font-weight:600;padding:4px 10px;border-radius:20px;letter-spacing:0.04em;text-transform:uppercase;">
                    Completed
                </span>
            </td>
        </tr>
    </table>
@endsection

@section('content')
    <p style="color:#e2e8f0;font-size:15px;line-height:1.7;margin:0 0 24px;">
        The report you requested has been generated and is available for download.
        The download link is valid for <strong style="color:#f1f5f9;">24 hours</strong>.
    </p>

    @if($downloadUrl && $downloadUrl !== '#')
        <div style="text-align:center;margin:0 0 28px;">
            <a href="{{ $downloadUrl }}"
               style="display:inline-block;background-color:#f59e0b;color:#0f172a;text-decoration:none;padding:13px 36px;border-radius:8px;font-size:15px;font-weight:700;letter-spacing:0.01em;">
                Download Report
            </a>
        </div>
    @else
        <div style="background-color:#1e293b;border:1px solid #334155;border-radius:8px;padding:16px 20px;margin:0 0 28px;">
            <p style="color:#94a3b8;font-size:14px;margin:0;line-height:1.5;">
                The report file will be available in your
                <a href="{{ route('reports') }}" style="color:#f59e0b;text-decoration:none;font-weight:600;">Reports list</a>
                shortly.
            </p>
        </div>
    @endif

    <!-- Report metadata -->
    <table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation"
           style="background-color:#1e293b;border:1px solid #334155;border-radius:8px;overflow:hidden;">
        <tr>
            <td style="padding:14px 20px;border-bottom:1px solid #334155;">
                <span style="color:#64748b;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.06em;">Report</span>
                <p style="color:#e2e8f0;font-size:14px;font-weight:600;margin:4px 0 0;">{{ $report->title }}</p>
            </td>
        </tr>
        <tr>
            <td style="padding:14px 20px;border-bottom:1px solid #334155;">
                <span style="color:#64748b;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.06em;">Generated</span>
                <p style="color:#e2e8f0;font-size:14px;margin:4px 0 0;">{{ $report->created_at->format('D, d M Y \a\t H:i') }}</p>
            </td>
        </tr>
        <tr>
            <td style="padding:14px 20px;">
                <span style="color:#64748b;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.06em;">Link expires</span>
                <p style="color:#fbbf24;font-size:14px;font-weight:600;margin:4px 0 0;">{{ now()->addHours(24)->format('D, d M Y \a\t H:i') }}</p>
            </td>
        </tr>
    </table>

    <p style="color:#64748b;font-size:13px;line-height:1.6;margin:24px 0 0;">
        You can also access all your reports from the
        <a href="{{ route('reports') }}" style="color:#94a3b8;text-decoration:none;">Reports dashboard</a>.
    </p>
@endsection

@section('footer_note')
    <a href="{{ route('settings') }}" style="color:#64748b;text-decoration:none;">Manage notification preferences</a>
@endsection


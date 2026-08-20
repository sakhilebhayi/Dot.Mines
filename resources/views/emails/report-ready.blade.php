@extends('emails.layout')

@section('title', 'Your report is ready')

@section('banner')
    <p style="margin:0 0 4px;font-size:12px;letter-spacing:0.08em;text-transform:uppercase;color:#c9b896;">Report ready</p>
    <h1 style="margin:0;font-size:20px;line-height:1.3;color:#f4efe4;">{{ $report->title }}</h1>
@endsection

@section('content')
    <p style="margin:0 0 16px;font-size:14px;line-height:1.6;color:#c9b896;">
        The report you requested has been generated and is ready for download.
    </p>

    <table cellpadding="0" cellspacing="0" border="0" role="presentation" style="margin:0 0 20px;width:100%;background-color:#3a2f22;border-radius:10px;">
        <tr>
            <td style="padding:14px 18px;font-size:13px;color:#c9b896;">Report</td>
            <td style="padding:14px 18px;font-size:13px;color:#f4efe4;text-align:right;font-weight:600;">{{ $report->title }}</td>
        </tr>
        @if($report->team)
        <tr>
            <td style="padding:0 18px 14px;font-size:13px;color:#c9b896;">Site</td>
            <td style="padding:0 18px 14px;font-size:13px;color:#f4efe4;text-align:right;">{{ $report->team->name }}</td>
        </tr>
        @endif
        <tr>
            <td style="padding:0 18px 14px;font-size:13px;color:#c9b896;">Generated</td>
            <td style="padding:0 18px 14px;font-size:13px;color:#f4efe4;text-align:right;">{{ now()->format('d M Y, H:i') }}</td>
        </tr>
    </table>

    @if($downloadUrl && $downloadUrl !== '#')
        <table cellpadding="0" cellspacing="0" border="0" role="presentation" style="margin:0 auto;">
            <tr>
                <td style="background-color:#d99e2b;border-radius:8px;">
                    <a href="{{ $downloadUrl }}" style="display:inline-block;padding:12px 28px;font-size:14px;font-weight:700;color:#211a14;text-decoration:none;">Download Report</a>
                </td>
            </tr>
        </table>
        <p style="margin:16px 0 0;font-size:12px;line-height:1.5;color:#a89a7c;text-align:center;">
            Download links expire after a limited time for security.
        </p>
    @else
        <p style="margin:0;font-size:14px;line-height:1.6;color:#c9b896;">
            The report file will be available in your reports list shortly.
        </p>
    @endif
@endsection

@section('footer_note')
    You received this email because a report was generated for your team on {{ config('app.name') }}.
@endsection

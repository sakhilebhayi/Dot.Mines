<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $report->title }}</title>
    <style>
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            color: #211a14;
            font-size: 11px;
        }
        .header {
            border-bottom: 3px solid #d99e2b;
            padding-bottom: 12px;
            margin-bottom: 18px;
        }
        .header h1 {
            font-size: 20px;
            margin: 0 0 4px 0;
            color: #211a14;
        }
        .header .meta {
            color: #6b6152;
            font-size: 10px;
        }
        .summary {
            margin-bottom: 18px;
            width: 100%;
        }
        .summary td {
            padding: 4px 12px 4px 0;
            font-size: 11px;
        }
        .summary .label {
            color: #6b6152;
        }
        .summary .value {
            font-weight: bold;
            color: #211a14;
        }
        table.data {
            width: 100%;
            border-collapse: collapse;
        }
        table.data th {
            background: #d99e2b;
            color: #211a14;
            text-align: left;
            padding: 6px 8px;
            font-size: 10px;
            text-transform: uppercase;
        }
        table.data td {
            padding: 5px 8px;
            border-bottom: 1px solid #e5ddc8;
            font-size: 10px;
        }
        table.data tr:nth-child(even) td {
            background: #faf7ef;
        }
        .brandbar {
            width: 100%;
            margin-bottom: 6px;
        }
        .brandbar .wordmark {
            font-size: 16px;
            font-weight: bold;
            color: #211a14;
            letter-spacing: 0.02em;
        }
        .brandbar .tag {
            font-size: 9px;
            color: #6b6152;
            text-transform: uppercase;
            letter-spacing: 0.14em;
        }
        .brandbar .logo {
            width: 28px;
            height: 28px;
        }
        /* dompdf repeats position:fixed elements on every page. */
        .pagefooter {
            position: fixed;
            bottom: -22px;
            left: 0;
            right: 0;
            font-size: 8px;
            color: #999;
            border-top: 1px solid #e5ddc8;
            padding-top: 4px;
        }
        body {
            margin-bottom: 30px;
        }
    </style>
</head>
<body>
    @php
        // Embedded so the PDF never depends on a web host or hardcoded URL;
        // asset lives in the repo, public_path() resolves per environment.
        $logoPath = public_path('images/mark.png');
        $logoData = is_file($logoPath) ? base64_encode(file_get_contents($logoPath)) : null;
    @endphp

    <div class="pagefooter">
        Official {{ config('app.name') }} operational report
        &middot; {{ $report->team?->name }}
        &middot; Generated {{ now()->format('Y-m-d H:i') }} by {{ config('app.name') }}
    </div>

    <div class="header">
        <table class="brandbar" cellpadding="0" cellspacing="0">
            <tr>
                @if($logoData)
                    <td style="width:34px;"><img class="logo" src="data:image/png;base64,{{ $logoData }}" alt=""></td>
                @endif
                <td>
                    <div class="wordmark">{{ config('app.name') }}</div>
                    <div class="tag">Operational Report</div>
                </td>
            </tr>
        </table>
        <h1>{{ $report->title }}</h1>
        <div class="meta">
            Generated {{ now()->format('Y-m-d H:i') }}
            @if($report->team)
                &middot; Site: {{ $report->team->name }}
            @endif
            @if($report->type)
                &middot; Type: {{ ucwords(str_replace('_', ' ', $report->type)) }}
            @endif
            @if(isset($report->filters['start_date'], $report->filters['end_date']))
                &middot; Period: {{ \Carbon\Carbon::parse($report->filters['start_date'])->format('Y-m-d') }}
                to {{ \Carbon\Carbon::parse($report->filters['end_date'])->format('Y-m-d') }}
            @endif
        </div>
    </div>

    @if(!empty($data['summary']))
        <table class="summary">
            <tr>
                @foreach($data['summary'] as $label => $value)
                    <td class="label">{{ $label }}:</td>
                    <td class="value">{{ $value }}</td>
                @endforeach
            </tr>
        </table>
    @endif

    <table class="data">
        <thead>
            <tr>
                @foreach($data['headers'] as $header)
                    <th>{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($data['rows'] as $row)
                <tr>
                    @foreach($row as $cell)
                        <td>{{ $cell ?? '—' }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($data['headers']) }}">No data found for the selected filters.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

</body>
</html>

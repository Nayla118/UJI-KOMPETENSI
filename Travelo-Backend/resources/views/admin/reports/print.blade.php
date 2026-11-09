<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Travelo report {{ $report['range']['start'] }} – {{ $report['range']['end'] }}</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            color: #1f2937;
            margin: 0;
            padding: 32px;
            background: #fff;
            font-size: 12px;
        }
        h1 { font-size: 22px; margin: 0 0 4px; color: #493747; }
        .meta { color: #6b7280; margin-bottom: 24px; }
        h2 {
            font-size: 14px;
            margin: 26px 0 8px;
            padding-bottom: 4px;
            border-bottom: 2px solid #a95372;
            color: #493747;
            page-break-after: avoid;
        }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        th, td { text-align: left; padding: 5px 8px; border-bottom: 1px solid #e5e7eb; }
        th { background: #f9e7ed; color: #493747; font-size: 10px; text-transform: uppercase; letter-spacing: .04em; }
        td:last-child, th:last-child { text-align: right; }
        tr:nth-child(even) td { background: #fafafa; }
        .empty { color: #9ca3af; font-style: italic; }
        .footer { margin-top: 28px; color: #9ca3af; font-size: 10px; }
        @media print {
            body { padding: 0; }
            h2 { break-after: avoid; }
            table { break-inside: auto; }
            tr { break-inside: avoid; }
        }
    </style>
</head>
<body>
    <h1>Travelo Analytics Report</h1>
    <p class="meta">
        {{ $report['range']['start'] }} – {{ $report['range']['end'] }}
        ({{ $report['range']['days'] }} days, grouped by {{ $report['range']['period'] }})
        · generated {{ \Carbon\Carbon::parse($report['generated_at'])->format('d M Y H:i') }}
    </p>

    @foreach($sections as $section)
        <h2>{{ $section['title'] }}</h2>

        @if(! empty($section['headers']) || ! empty($section['rows']))
            <table>
                @if(! empty($section['headers']))
                    <thead>
                        <tr>
                            @foreach($section['headers'] as $header)
                                <th>{{ $header }}</th>
                            @endforeach
                        </tr>
                    </thead>
                @endif
                <tbody>
                    @forelse($section['rows'] as $row)
                        <tr>
                            @foreach($row as $cell)
                                <td>{{ $cell }}</td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td class="empty" colspan="{{ max(1, count($section['headers'])) }}">No data for this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        @endif
    @endforeach

    <p class="footer">
        Revenue is recognised when a booking is paid; refunds are counted separately on the
        refund date. Use your browser's Print → Save as PDF to archive this report.
    </p>
</body>
</html>

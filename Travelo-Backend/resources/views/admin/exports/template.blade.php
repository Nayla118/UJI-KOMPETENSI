<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        body {
            font-family: 'Nunito Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            font-size: 12px;
            color: #493747;
            background: #FFFFFF;
        }
        h1 {
            color: #A95372;
            font-family: 'Nunito', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            font-size: 20px;
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #E7D9DF;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #A95372;
            color: white;
            font-weight: bold;
        }
        tr:nth-child(even) {
            background-color: #FFF9F7;
        }
        .meta {
            color: #6E5A68;
            font-size: 10px;
            margin-bottom: 20px;
        }
        @media print {
            body { font-size: 10px; }
            th { background-color: #A95372 !important; color: white !important; -webkit-print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <div class="meta">
        Generated: {{ now()->format('Y-m-d H:i:s') }}
    </div>

    <table>
        <thead>
            <tr>
                @foreach($headers as $header)
                    <th>{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($data as $row)
                <tr>
                    @foreach($row as $cell)
                        <td>{{ $cell }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>

    <div style="margin-top: 20px; font-size: 10px; color: #6E5A68;">
        Travelo - Travel Planner & Tour Booking System
    </div>
</body>
</html>

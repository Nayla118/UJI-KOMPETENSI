<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
        }
        h1 {
            color: #2563EB;
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #2563EB;
            color: white;
            font-weight: bold;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .meta {
            color: #666;
            font-size: 10px;
            margin-bottom: 20px;
        }
        @media print {
            body { font-size: 10px; }
            th { background-color: #2563EB !important; color: white !important; -webkit-print-color-adjust: exact; }
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

    <div style="margin-top: 20px; font-size: 10px; color: #666;">
        Travelo - Travel Planner & Tour Booking System
    </div>
</body>
</html>

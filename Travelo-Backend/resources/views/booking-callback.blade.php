<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ $title }} &mdash; Travelo</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #FFF9F7;
            color: #493747;
            font-family: 'Nunito Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            padding: 24px;
        }
        .card {
            background: #FFFFFF;
            border: 1px solid #E7D9DF;
            border-radius: 24px;
            padding: 32px 28px;
            max-width: 460px;
            width: 100%;
            text-align: center;
            box-shadow: 0 12px 32px rgba(73, 55, 71, .08);
        }
        .brand {
            display: inline-block;
            margin-bottom: 24px;
            color: #A95372;
            font-family: 'Nunito', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            font-size: 22px;
            font-weight: 800;
            letter-spacing: .5px;
            text-decoration: none;
        }
        .badge {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 64px;
            height: 64px;
            margin: 0 auto 16px;
            border-radius: 50%;
            background: {{ $tone }}1A;
            color: {{ $tone }};
        }
        h1 {
            margin: 0 0 8px;
            color: #493747;
            font-family: 'Nunito', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            font-size: 21px;
            line-height: 1.35;
            font-weight: 800;
        }
        p {
            margin: 0 0 18px;
            color: #6E5A68;
            font-size: 15px;
            line-height: 1.65;
        }
        .meta {
            margin: 0;
            padding-top: 16px;
            border-top: 1px solid #E7D9DF;
            color: #6E5A68;
            font-size: 13px;
            line-height: 1.7;
        }
        .meta strong { color: #493747; }
        @media (max-width: 420px) {
            body { padding: 16px; }
            .card { padding: 28px 20px; }
        }
    </style>
</head>
<body>
<main class="card">
    <span class="brand">Travelo</span>

    <div class="badge" aria-hidden="true">
        @if ($result === 'success')
            <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20 6 9 17l-5-5"/>
            </svg>
        @elseif ($result === 'pending')
            <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="9"/>
                <path d="M12 7v5l3 2"/>
            </svg>
        @else
            <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="9"/>
                <path d="M12 8v5"/>
                <path d="M12 16h.01"/>
            </svg>
        @endif
    </div>

    <h1>{{ $title }}</h1>
    <p>{{ $body }}</p>

    <p class="meta">
        {{ __('travelo.callback.booking_id') }}: <strong>{{ $bookingId ?: '-' }}</strong><br>
        {{ __('travelo.callback.open_app') }}
    </p>
</main>
</body>
</html>

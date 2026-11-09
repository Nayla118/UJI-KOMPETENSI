@php
    $paidAt = \Carbon\Carbon::parse($invoice['paid_at'])->timezone(config('app.timezone'));
    $visitDate = $invoice['visit_date']
        ? \Carbon\Carbon::parse($invoice['visit_date'])->format('d M Y')
        : '—';
    $money = fn ($amount) => 'Rp '.number_format((float) $amount, 0, ',', '.');
    $destination = $invoice['tour_package']['destination'] ?? null;
    $destinationLabel = $destination
        ? trim(($destination['name'] ?? '').' — '.($destination['city'] ?? '').', '.($destination['country'] ?? ''), ' —,')
        : null;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice {{ $invoice['invoice_number'] }}</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 32px 16px;
            background: #f4f1ee;
            font-family: "Helvetica Neue", Arial, sans-serif;
            color: #2f2430;
            font-size: 14px;
            line-height: 1.5;
        }
        .sheet {
            max-width: 720px;
            margin: 0 auto;
            background: #ffffff;
            border: 1px solid #e7d9df;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 8px 24px rgba(73, 55, 71, 0.08);
        }
        .header {
            display: flex;
            justify-content: space-between;
            gap: 24px;
            padding-bottom: 20px;
            border-bottom: 2px solid #a95372;
        }
        .brand { font-size: 26px; font-weight: 700; letter-spacing: 1px; color: #a95372; }
        .brand small { display: block; font-size: 11px; letter-spacing: 2px; color: #6e5a68; font-weight: 500; }
        .doc-title { text-align: right; }
        .doc-title h1 { margin: 0; font-size: 20px; letter-spacing: 2px; text-transform: uppercase; color: #493747; }
        .doc-title p { margin: 4px 0 0; font-size: 12px; color: #6e5a68; }
        .meta {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px 24px;
            margin: 24px 0;
        }
        .meta div span { display: block; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #6e5a68; }
        .meta div strong { font-size: 14px; color: #2f2430; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        thead th {
            text-align: left;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #6e5a68;
            border-bottom: 1px solid #e7d9df;
            padding: 10px 8px;
        }
        thead th:last-child, tbody td:last-child { text-align: right; }
        tbody td { padding: 12px 8px; border-bottom: 1px solid #f1e8ec; vertical-align: top; }
        .item-title { font-weight: 600; }
        .item-sub { font-size: 12px; color: #6e5a68; }
        .totals { margin-left: auto; margin-top: 16px; width: 100%; max-width: 320px; }
        .totals .row { display: flex; justify-content: space-between; padding: 6px 0; }
        .totals .row.discount { color: #4f7d66; }
        .totals .row.grand {
            border-top: 2px solid #493747;
            margin-top: 6px;
            padding-top: 10px;
            font-size: 17px;
            font-weight: 700;
            color: #a95372;
        }
        .payment {
            margin-top: 28px;
            padding: 16px;
            background: #f9e7ed;
            border-radius: 10px;
        }
        .payment .label { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #6e5a68; }
        .payment .value { font-weight: 600; }
        .status {
            display: inline-block;
            margin-left: 8px;
            padding: 2px 10px;
            border-radius: 999px;
            background: #4f7d66;
            color: #ffffff;
            font-size: 11px;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .footer {
            margin-top: 28px;
            padding-top: 16px;
            border-top: 1px solid #e7d9df;
            font-size: 12px;
            color: #6e5a68;
        }
        .actions { max-width: 720px; margin: 16px auto 0; text-align: right; }
        .actions button {
            border: 0;
            border-radius: 8px;
            padding: 10px 18px;
            background: #a95372;
            color: #fff;
            font-size: 14px;
            cursor: pointer;
        }
        @media print {
            @page { size: A4; margin: 14mm; }
            body { background: #ffffff; padding: 0; }
            .sheet { box-shadow: none; border: 0; border-radius: 0; padding: 0; max-width: none; }
            .actions { display: none; }
            .payment { background: #f4f1ee; }
        }
    </style>
</head>
<body>
    <div class="sheet">
        <div class="header">
            <div>
                <div class="brand">TRAVELO<small>EXPLORE WITH CONFIDENCE</small></div>
            </div>
            <div class="doc-title">
                <h1>Invoice</h1>
                <p>{{ $invoice['invoice_number'] }}</p>
            </div>
        </div>

        <div class="meta">
            <div>
                <span>Tanggal bayar</span>
                <strong>{{ $paidAt->format('d M Y H:i') }} ({{ config('app.timezone') }})</strong>
            </div>
            <div>
                <span>Booking</span>
                <strong>#{{ $invoice['booking_id'] }}</strong>
            </div>
            <div>
                <span>Nama pemesan</span>
                <strong>{{ $invoice['customer_name'] ?: '—' }}</strong>
            </div>
            <div>
                <span>Tanggal kunjungan</span>
                <strong>{{ $visitDate }}</strong>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Deskripsi</th>
                    <th>Jumlah</th>
                    <th>Harga</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <div class="item-title">{{ $invoice['tour_package']['title'] ?: 'Paket wisata' }}</div>
                        @if($destinationLabel)
                            <div class="item-sub">{{ $destinationLabel }}</div>
                        @endif
                    </td>
                    <td>{{ $invoice['people_count'] }} orang</td>
                    <td>{{ $money($invoice['subtotal_amount']) }}</td>
                </tr>
            </tbody>
        </table>

        <div class="totals">
            <div class="row">
                <span>Subtotal</span>
                <span>{{ $money($invoice['subtotal_amount']) }}</span>
            </div>
            @if($invoice['discount_amount'] > 0)
                <div class="row discount">
                    <span>
                        Diskon
                        @if($invoice['promo_code'])
                            ({{ $invoice['promo_code'] }})
                        @endif
                    </span>
                    <span>-{{ $money($invoice['discount_amount']) }}</span>
                </div>
            @endif
            <div class="row grand">
                <span>Total dibayar</span>
                <span>{{ $money($invoice['total_amount']) }}</span>
            </div>
        </div>

        <div class="payment">
            <span class="label">Metode pembayaran</span>
            <div class="value">
                {{ $invoice['payment_method'] ?: '—' }}
                <span class="status">{{ ucfirst($invoice['payment_status']) }}</span>
            </div>
        </div>

        <div class="footer">
            Invoice ini dibuat otomatis oleh sistem Travelo setelah pembayaran
            terkonfirmasi. Nomor identitas pengunjung tidak ditampilkan pada
            dokumen ini. Simpan invoice sebagai bukti pembayaran yang sah.
        </div>
    </div>

    <div class="actions">
        <button type="button" onclick="window.print()">Cetak / Simpan PDF</button>
    </div>
</body>
</html>

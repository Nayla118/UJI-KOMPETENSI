@extends('layouts.owner')

@section('title', 'Detail Pembayaran | Travelo Owner')

@section('content')
<header class="bg-white sticky top-0 z-20 border-b border-slate-200 px-8 py-4">
    <div class="max-w-7xl mx-auto flex items-center justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold tracking-tight">Detail Pembayaran</h2>
            <a href="{{ route('owner.payments.index') }}" class="text-amber-700 hover:underline text-sm">Kembali ke daftar</a>
        </div>
    </div>
</header>

<div class="p-8 max-w-7xl mx-auto space-y-6">
    <div class="bg-white border border-slate-200 rounded-xl p-6">
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
            <div class="col-span-2">
                <dt class="text-slate-500 uppercase text-xs font-semibold">ID Pembayaran</dt>
                <dd class="text-ink font-medium">{{ $payment->id }}</dd>
            </div>
            <div class="col-span-2">
                <dt class="text-slate-500 uppercase text-xs font-semibold">Booking</dt>
                <dd class="text-ink font-medium">{{ $payment->booking?->reference ?? '-' }}</dd>
            </div>
            <div>
                <dt class="text-slate-500 uppercase text-xs font-semibold">Metode</dt>
                <dd class="text-ink">{{ $payment->payment_method ?? '-' }}</dd>
            </div>
            <div>
                <dt class="text-slate-500 uppercase text-xs font-semibold">Status</dt>
                <dd class="text-ink">{{ ucfirst($payment->payment_status ?? '-') }}</dd>
            </div>
            <div>
                <dt class="text-slate-500 uppercase text-xs font-semibold">Nominal</dt>
                <dd class="text-ink">{{ number_format((float) ($payment->amount ?? 0), 0, ',', '.') }}</dd>
            </div>
            <div>
                <dt class="text-slate-500 uppercase text-xs font-semibold">Tanggal</dt>
                <dd class="text-ink">{{ $payment->created_at?->format('d M Y H:i') ?? '-' }}</dd>
            </div>
        </dl>
    </div>
</div>
@endsection

@extends('layouts.owner')

@section('title', 'Detail Booking | Travelo Owner')

@section('content')
<header class="bg-white sticky top-0 z-20 border-b border-slate-200 px-8 py-4">
    <div class="max-w-7xl mx-auto">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold tracking-tight">Booking #{{ $booking->id }}</h2>
                <p class="text-sm text-slate-500">Detail booking untuk {{ $booking->tourPackage->destination->name }}</p>
            </div>
            <a href="{{ route('owner.bookings.index') }}" class="text-primary hover:underline text-sm">
                <span class="material-symbols-outlined text-[18px] mr-1">arrow_back</span>
                Kembali
            </a>
        </div>
    </div>
</header>

<div class="p-8 max-w-4xl mx-auto space-y-6">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white border border-slate-200 rounded-xl p-6">
            <h3 class="font-semibold text-ink mb-4">Informasi Booking</h3>
            <dl class="divide-y divide-slate-100">
                <div class="py-4">
                    <dt class="text-sm text-slate-500">Traveller</dt>
                    <dd class="text-lg font-bold text-ink">{{ optional($booking->user)->name ?? '-' }}</dd>
                </div>
                <div class="py-4">
                    <dt class="text-sm text-slate-500">Paket Wisata</dt>
                    <dd class="text-lg font-bold text-ink">{{ optional($booking->tourPackage)->title ?? '-' }}</dd>
                </div>
                <div class="py-4">
                    <dt class="text-sm text-slate-500">Destinasi</dt>
                    <dd class="text-lg font-bold text-ink">{{ optional($booking->tourPackage->destination)->name ?? '-' }}</dd>
                </div>
                <div class="py-4">
                    <dt class="text-sm text-slate-500">Tanggal Kunjungan</dt>
                    <dd class="text-lg font-bold text-ink">{{ optional($booking->booking_date)->format('d M Y') ?? '-' }}</dd>
                </div>
                <div class="py-4">
                    <dt class="text-sm text-slate-500">Jumlah Orang</dt>
                    <dd class="text-lg font-bold text-ink">{{ $booking->people_count }} orang</dd>
                </div>
            </dl>
        </div>

        <div class="bg-white border border-slate-200 rounded-xl p-6">
            <h3 class="font-semibold text-ink mb-4">Pembayaran & Refund</h3>
            <dl class="divide-y divide-slate-100">
                <div class="py-4">
                    <dt class="text-sm text-slate-500">Status Pembayaran</dt>
                    <dd class="text-lg font-bold text-ink">{{ ucfirst($booking->payment_status ?? '-') }}</dd>
                </div>
                <div class="py-4">
                    <dt class="text-sm text-slate-500">Status Booking</dt>
                    <dd class="text-lg font-bold text-ink">{{ ucfirst($booking->booking_status ?? '-') }}</dd>
                </div>
                <div class="py-4">
                    <dt class="text-sm text-slate-500">Total Dibayar</dt>
                    <dd class="text-lg font-bold text-ink">IDR {{ number_format((int) $booking->total_price, 0, ',', '.') }}</dd>
                </div>
                @if($booking->discount_amount > 0)
                    <div class="py-4">
                        <dt class="text-sm text-slate-500">Diskon Promo</dt>
                        <dd class="text-lg font-bold text-amber-700">-IDR {{ number_format((int) $booking->discount_amount, 0, ',', '.') }}</dd>
                    </div>
                @endif
                @php($refund = $booking->latestChangeRequest)
                @if($refund)
                    <div class="py-4">
                        <dt class="text-sm text-slate-500">Status Refund</dt>
                        <dd class="text-lg font-bold
                            @if($refund->refund_status === 'refunded') text-green-700
                            @elseif($refund->refund_status === 'failed') text-red-700
                            @elseif($refund->refund_status === 'processing') text-amber-700
                            @elseif($refund->refund_status === 'pending') text-amber-700
                            @else text-slate-700 @endif">
                            {{ $refund->refundStatusLabel() }}
                        </dd>
                    </div>
                    @if($refund->refund_percentage !== null)
                        <div class="py-4">
                            <dt class="text-sm text-slate-500">Persentase Refund</dt>
                            <dd class="text-lg font-bold text-ink">{{ $refund->refund_percentage }}%</dd>
                        </div>
                    @endif
                    @if($refund->refund_amount !== null)
                        <div class="py-4">
                            <dt class="text-sm text-slate-500">Nominal Refund</dt>
                            <dd class="text-lg font-bold text-green-700">IDR {{ number_format((float) $refund->refund_amount, 0, ',', '.') }}</dd>
                        </div>
                    @endif
                    @if($refund->refund_status === 'failed' && $refund->refund_failure_reason)
                        <div class="py-4">
                            <dt class="text-sm text-slate-500">Alasan Gagal</dt>
                            <dd class="text-sm text-red-700">{{ $refund->refund_failure_reason }}</dd>
                        </div>
                    @endif
                @endif
            </dl>
        </div>
    </div>

    @if($booking->visitors && $booking->visitors->count() > 0)
        <div class="bg-white border border-slate-200 rounded-xl p-6">
            <h3 class="font-semibold text-ink mb-4">Pengunjung ({{ $booking->visitors->count() }})</h3>
            <div class="space-y-3">
                @foreach($booking->visitors as $v)
                    <div class="flex items-center gap-4 p-3 bg-slate-50 rounded-lg">
                        <span class="text-sm font-medium text-ink">{{ $v->name }}</span>
                        <span class="ml-auto text-sm text-slate-500">{{ ucfirst($v->identity_type ?? '-') }}</span>
                        <span class="text-sm text-slate-500">{{ $v->identity_number_masked ?? '***' }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
@endsection

@extends('layouts.owner')

@section('title', 'Owner Dashboard | Travelo')

@section('content')
<header class="bg-white sticky top-0 z-20 border-b border-slate-200 px-8 py-4">
    <div class="max-w-7xl mx-auto">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold tracking-tight">Dashboard Pemilik</h2>
                <p class="text-sm text-slate-500">Kelola destinasi, paket, dan booking yang Anda miliki.</p>
            </div>
        </div>
    </div>
</header>

<div class="p-8 max-w-7xl mx-auto space-y-6">
    <!-- Stats cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white border border-slate-200 rounded-xl p-6">
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Destinasi</p>
            <p class="text-2xl font-bold text-ink mt-1">{{ $destinationCount }}</p>
        </div>
        <div class="bg-white border border-slate-200 rounded-xl p-6">
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Paket Wisata</p>
            <p class="text-2xl font-bold text-ink mt-1">{{ $packageCount }}</p>
        </div>
        <div class="bg-white border border-slate-200 rounded-xl p-6">
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Booking</p>
            <p class="text-2xl font-bold text-ink mt-1">{{ $bookingCount }}</p>
        </div>
        <div class="bg-white border border-slate-200 rounded-xl p-6">
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Pengajuan Pembatalan</p>
            <p class="text-2xl font-bold text-amber-700 mt-1">{{ $pendingCount }}</p>
        </div>
    </div>

    <!-- Pending change requests -->
    <div class="bg-white border border-slate-200 rounded-xl p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-semibold text-ink">Pengajuan Pembatalan Tertunda</h3>
            <a href="{{ route('owner.change-requests.index') }}" class="text-sm text-primary hover:underline">Lihat semua</a>
        </div>

        @if($pendingRequests->isEmpty())
            <p class="text-sm text-slate-500 text-center py-8">Tidak ada pengajuan pembatalan yang menunggu persetujuan.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-slate-500 font-semibold">Traveller</th>
                            <th class="px-4 py-3 text-left text-slate-500 font-semibold">Destinasi</th>
                            <th class="px-4 py-3 text-left text-slate-500 font-semibold">Tanggal Kunjungan</th>
                            <th class="px-4 py-3 text-right text-slate-500 font-semibold">Dibuat</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($pendingRequests as $req)
                            <tr>
                                <td class="px-4 py-3 text-ink font-medium">{{ optional($req->booking->user)->name ?? 'Traveller' }}</td>
                                <td class="px-4 py-3 text-ink">{{ $req->booking->tourPackage->destination->name ?? '-' }}</td>
                                <td class="px-4 py-3 text-ink">{{ optional($req->booking->booking_date)->format('d M Y') ?? '-' }}</td>
                                <td class="px-4 py-3 text-right text-slate-500">{{ $req->created_at?->format('d M Y H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <!-- Recent bookings -->
    <div class="bg-white border border-slate-200 rounded-xl p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-semibold text-ink">Booking Terbaru</h3>
            <a href="{{ route('owner.bookings.index') }}" class="text-sm text-primary hover:underline">Lihat semua</a>
        </div>

        @if($bookings->isEmpty())
            <p class="text-sm text-slate-500 text-center py-8">Belum ada booking untuk destinasi Anda.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-slate-500 font-semibold">Traveller</th>
                            <th class="px-4 py-3 text-left text-slate-500 font-semibold">Paket</th>
                            <th class="px-4 py-3 text-left text-slate-500 font-semibold">Status</th>
                            <th class="px-4 py-3 text-right text-slate-500 font-semibold">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($bookings as $b)
                            <tr>
                                <td class="px-4 py-3 text-ink font-medium">{{ optional($b->user)->name ?? 'Traveller' }}</td>
                                <td class="px-4 py-3 text-ink">{{ $b->tourPackage->title ?? '-' }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold
                                        @if($b->booking_status === 'confirmed') bg-green-50 text-green-700
                                        @elseif($b->booking_status === 'pending') bg-amber-50 text-amber-700
                                        @elseif($b->booking_status === 'cancelled') bg-red-50 text-red-700
                                        @else bg-slate-50 text-slate-700 @endif">
                                        {{ ucfirst($b->booking_status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right text-ink font-medium">
                                    IDR {{ number_format((int) $b->total_price, 0, ',', '.') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection

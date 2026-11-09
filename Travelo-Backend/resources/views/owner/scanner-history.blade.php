@extends('layouts.owner')

@section('title', 'Riwayat Scan Saya | Travelo')

@section('content')
<header class="bg-white sticky top-0 z-20 border-b border-slate-200 px-8 py-4">
    <div class="max-w-7xl mx-auto flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold tracking-tight">Riwayat Scan Saya</h2>
            <p class="text-sm text-slate-500">Hanya tiket yang Anda pindai sendiri. Data pribadi dan nominal tidak ditampilkan.</p>
        </div>
        <a href="{{ route('owner.scanner') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-primary hover:underline">
            <span class="material-symbols-outlined text-[18px]">qr_code_scanner</span>
            Kembali ke scanner
        </a>
    </div>
</header>

<div class="p-8 max-w-7xl mx-auto space-y-6">
    <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-slate-500">Waktu Scan</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-500">Paket</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-500">Destinasi</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-500">Tanggal Kunjungan</th>
                        <th class="px-4 py-3 text-right font-semibold text-slate-500">Jumlah Orang</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($bookings as $booking)
                        <tr>
                            <td class="px-4 py-3 text-slate-600">{{ $booking->ticket_used_at?->format('d M Y H:i') ?? '-' }}</td>
                            <td class="px-4 py-3 font-medium text-ink">{{ $booking->tourPackage?->title ?? '-' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $booking->tourPackage?->destination?->name ?? '-' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $booking->booking_date?->format('d M Y') ?? '-' }}</td>
                            <td class="px-4 py-3 text-right text-slate-600">{{ (int) $booking->people_count }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-10 text-center text-slate-500">Belum ada tiket yang Anda pindai.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{ $bookings->links() }}
</div>
@endsection

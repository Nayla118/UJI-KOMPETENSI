@extends('layouts.owner')

@section('title', 'Laporan Check-in | Travelo Owner')

@section('content')
<header class="bg-white sticky top-0 z-20 border-b border-slate-200 px-8 py-4">
    <div class="max-w-7xl mx-auto">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold tracking-tight">Laporan Check-in</h2>
                <p class="text-sm text-slate-500">Rekap kunjungan dan tiket terpakai untuk destinasi Anda.</p>
            </div>
            <a href="{{ route('owner.laporan.export', ['date_from' => $date->copy()->startOfMonth()->format('Y-m-d'), 'date_to' => $date->copy()->endOfMonth()->format('Y-m-d')]) }}" class="bg-primary hover:bg-primary/90 text-white px-5 py-2.5 rounded-lg font-semibold flex items-center gap-2 transition-colors shadow-sm">
                <span class="material-symbols-outlined text-[20px]">file_download</span>
                Export CSV
            </a>
        </div>
    </div>
</header>

<div class="p-8 max-w-7xl mx-auto space-y-6">
    <!-- Date filter -->
    <div class="bg-white border border-slate-200 rounded-xl p-6">
        <form method="GET" action="{{ route('owner.laporan.index') }}" class="flex flex-col sm:flex-row gap-4 items-end">
            <div class="flex-1">
                <label class="block text-sm font-medium text-slate-700 mb-1">Tanggal</label>
                <input type="date" name="date" value="{{ $date->format('Y-m-d') }}"
                    class="w-full pl-4 py-2.5 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none">
            </div>
            <button type="submit" class="bg-primary hover:bg-primary/90 text-white px-5 py-2.5 rounded-lg font-semibold shadow-sm">
                <span class="material-symbols-outlined text-[18px] mr-2">search</span>
                Tampilkan
            </button>
        </form>
    </div>

    <!-- Today's stats -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white border border-slate-200 rounded-xl p-6">
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Booking Hari Ini</p>
            <p class="text-2xl font-bold text-ink mt-1">{{ $todayBookings->count() }}</p>
        </div>
        <div class="bg-white border border-slate-200 rounded-xl p-6">
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Sudah Check-in</p>
            <p class="text-2xl font-bold text-green-700 mt-1">{{ $todayCheckedIn }}</p>
        </div>
        <div class="bg-white border border-slate-200 rounded-xl p-6">
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Tiket Terpakai</p>
            <p class="text-2xl font-bold text-primary mt-1">{{ $todayTickets }} orang</p>
        </div>
    </div>

    <!-- 14-day chart placeholder -->
    <div class="bg-white border border-slate-200 rounded-xl p-6">
        <h3 class="font-semibold text-ink mb-4">Tren Kunjungan 14 Hari Terakhir</h3>
        <div class="h-48 w-full flex items-end justify-between gap-1 overflow-x-auto pb-4">
            @foreach($fourteenDays as $day)
                @php
                    $max = max(array_column($fourteenDays, 'count'));
                    $barHeight = $max > 0 ? round(($day['count'] / $max) * 200) : 0;
                @endphp
                <div class="flex flex-col items-center justify-end gap-2 w-12">
                    <div class="w-full bg-primary/20 rounded-t-lg" style="height: {{ $barHeight }}px;" title="{{ $day['date'] }}: {{ $day['count'] }} orang"></div>
                    <span class="text-xs text-slate-500">{{ $day['count'] }}</span>
                    <span class="text-[10px] text-slate-400">{{ $day['date'] }}</span>
                </div>
            @endforeach
        </div>
        <p class="mt-3 text-xs text-slate-500">Setiap batang menunjukkan jumlah tiket terpakai per hari.</p>
    </div>

    <!-- Per-destination summary -->
    <div class="bg-white border border-slate-200 rounded-xl p-6">
        <h3 class="font-semibold text-ink mb-4">Rekap per Destinasi</h3>
        @if(empty($destSummary))
            <p class="text-sm text-slate-500 text-center py-8">Belum ada data kunjungan.</p>
        @else
            <table class="min-w-full text-sm divide-y divide-slate-100">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-slate-500 font-semibold">Destinasi</th>
                        <th class="px-4 py-3 text-right text-slate-500 font-semibold">Total Booking</th>
                        <th class="px-4 py-3 text-right text-slate-500 font-semibold">Tiket Terpakai</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($destSummary as $ds)
                        <tr>
                            <td class="px-4 py-3 text-ink font-medium">{{ $ds['name'] }}</td>
                            <td class="px-4 py-3 text-right text-slate-600">{{ $ds['bookings'] }}</td>
                            <td class="px-4 py-3 text-right text-slate-600">{{ $ds['checked_in'] }} orang</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <!-- Recent bookings -->
    <div class="bg-white border border-slate-200 rounded-xl p-6">
        <h3 class="font-semibold text-ink mb-4">Booking Terbaru ({{ $date->format('d M Y') }})</h3>
        @if($todayBookings->isEmpty())
            <p class="text-sm text-slate-500 text-center py-8">Tidak ada booking untuk tanggal ini.</p>
        @else
            <table class="min-w-full text-sm divide-y divide-slate-100">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-slate-500 font-semibold">Traveller</th>
                        <th class="px-4 py-3 text-left text-slate-500 font-semibold">Paket</th>
                        <th class="px-4 py-3 text-left text-slate-500 font-semibold">Destinasi</th>
                        <th class="px-4 py-3 text-right text-slate-500 font-semibold">Orang</th>
                        <th class="px-4 py-3 text-right text-slate-500 font-semibold">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($todayBookings as $b)
                        <tr>
                            <td class="px-4 py-3 text-ink font-medium">{{ optional($b->user)->name ?? '-' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $b->tourPackage->title ?? '-' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $b->tourPackage->destination->name ?? '-' }}</td>
                            <td class="px-4 py-3 text-right text-slate-600">{{ $b->people_count }}</td>
                            <td class="px-4 py-3 text-right text-ink font-medium">IDR {{ number_format((int) $b->total_price, 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
@endsection

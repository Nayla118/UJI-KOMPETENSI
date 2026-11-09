@extends('layouts.owner')

@section('title', 'Booking | Travelo Owner')

@section('content')
<header class="bg-white sticky top-0 z-20 border-b border-slate-200 px-8 py-4">
    <div class="max-w-7xl mx-auto">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold tracking-tight">Booking</h2>
                <p class="text-sm text-slate-500">Lihat semua booking untuk destinasi Anda.</p>
            </div>
        </div>
    </div>
</header>

<div class="p-8 max-w-7xl mx-auto space-y-6">
    <div class="flex flex-col md:flex-row gap-4 items-center justify-between mb-4">
        <form method="GET" action="{{ route('owner.bookings.index') }}" class="relative w-full md:w-96">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">search</span>
            <input
                name="search"
                value="{{ request('search') }}"
                class="w-full pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none"
                placeholder="Cari nama traveller atau paket..."
                type="text"
            />
        </form>

        <div class="flex items-center gap-3 w-full md:w-auto">
            <form method="GET" action="{{ route('owner.bookings.index') }}" class="flex-1 md:w-48">
                @if(request('search'))
                    <input type="hidden" name="search" value="{{ request('search') }}">
                @endif
                <select name="status" class="w-full pl-4 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none">
                    <option value="">Semua Status</option>
                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="confirmed" {{ request('status') === 'confirmed' ? 'selected' : '' }}>Confirmed</option>
                    <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                </select>
            </form>
        </div>
    </div>

    @if($bookings->isEmpty())
        <div class="text-center py-12">
            <span class="material-symbols-outlined text-4xl text-slate-300 mb-3">event_busy</span>
            <p class="text-lg text-slate-500">Tidak ada booking untuk destinasi Anda.</p>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm divide-y divide-slate-100">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-slate-500 font-semibold">Traveller</th>
                        <th class="px-4 py-3 text-left text-slate-500 font-semibold">Paket</th>
                        <th class="px-4 py-3 text-left text-slate-500 font-semibold">Tanggal</th>
                        <th class="px-4 py-3 text-left text-slate-500 font-semibold">Status</th>
                        <th class="px-4 py-3 text-right text-slate-500 font-semibold">Total</th>
                        <th class="px-4 py-3 text-left text-slate-500 font-semibold">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($bookings as $b)
                        <tr>
                            <td class="px-4 py-3 text-ink font-medium">{{ optional($b->user)->name ?? 'Traveller' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $pkg = optional($b->tourPackage)->title ?? '-' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ optional($b->booking_date)->format('d M Y') ?? '-' }}</td>
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
                            <td class="px-4 py-3">
                                <a href="{{ route('owner.bookings.show', $b) }}" class="text-primary hover:underline text-sm">Lihat detail</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $bookings->links() }}
    @endif
</div>
@endsection

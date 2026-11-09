@extends('layouts.owner')

@section('title', 'Promo & Voucher | Travelo')

@section('content')
<header class="bg-white sticky top-0 z-20 border-b border-slate-200 px-8 py-4">
    <div class="max-w-7xl mx-auto flex items-center justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold tracking-tight">Promo &amp; Voucher</h2>
            <p class="text-sm text-slate-500">Hanya untuk destinasi dan paket milik Anda.</p>
        </div>
        <a href="{{ route('owner.promos.create') }}" class="bg-primary hover:bg-primary/90 text-white px-5 py-2.5 rounded-lg font-semibold flex items-center gap-2 shadow-sm">
            <span class="material-symbols-outlined text-[20px]">add</span>
            Tambah Promo
        </a>
    </div>
</header>

<div class="p-8 max-w-7xl mx-auto space-y-6">
    @if (session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 px-6 py-4 rounded-xl">{{ session('success') }}</div>
    @endif

    <form method="GET" action="{{ route('owner.promos.index') }}" class="flex gap-3">
        <input name="search" value="{{ request('search') }}" placeholder="Cari kode atau deskripsi..." class="w-full sm:w-96 rounded-xl border border-slate-200 px-4 py-2.5 text-sm" />
        <button type="submit" class="rounded-xl bg-slate-800 px-5 py-2.5 text-sm font-semibold text-white">Cari</button>
    </form>

    <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-slate-500">Kode</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-500">Cakupan</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-500">Diskon</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-500">Pemakaian</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-500">Status</th>
                        <th class="px-4 py-3 text-right font-semibold text-slate-500">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($promos as $promo)
                        <tr>
                            <td class="px-4 py-3 font-mono font-semibold text-ink">{{ $promo->code }}</td>
                            <td class="px-4 py-3 text-slate-600">
                                {{ $promo->destination?->name ?? $promo->tourPackage?->title ?? '-' }}
                            </td>
                            <td class="px-4 py-3 text-slate-600">
                                {{ $promo->isPercent() ? rtrim(rtrim((string) $promo->discount_value, '0'), '.').'%' : 'IDR '.number_format((int) $promo->discount_value, 0, ',', '.') }}
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ (int) $promo->used_count }}@if($promo->usage_limit) / {{ (int) $promo->usage_limit }}@endif</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $promo->is_active ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700' }}">
                                    {{ $promo->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <a href="{{ route('owner.promos.edit', $promo) }}" class="text-amber-700 hover:underline">Edit</a>
                                <form method="POST" action="{{ route('owner.promos.toggle', $promo) }}" class="inline-block ml-3">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="text-primary hover:underline">{{ $promo->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                                </form>
                                <form method="POST" action="{{ route('owner.promos.destroy', $promo) }}" class="inline-block ml-3" onsubmit="return confirm('Hapus promo ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:underline">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-slate-500">Belum ada promo untuk destinasi Anda.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{ $promos->links() }}
</div>
@endsection

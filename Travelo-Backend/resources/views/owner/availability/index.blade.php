@extends('layouts.owner')

@section('title', 'Kelola Kuota | Travelo Owner')

@section('content')
<header class="bg-white sticky top-0 z-20 border-b border-slate-200 px-8 py-4">
    <div class="max-w-7xl mx-auto">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold tracking-tight">Kelola Kuota</h2>
                <p class="text-sm text-slate-500">{{ $tourPackage->title }}</p>
            </div>
            <a href="{{ route('owner.tour-packages.index') }}" class="text-primary hover:underline text-sm">
                <span class="material-symbols-outlined text-[18px] mr-1">arrow_back</span>
                Kembali
            </a>
        </div>
    </div>
</header>

<div class="p-8 max-w-4xl mx-auto space-y-6">
    <div class="bg-white border border-slate-200 rounded-xl p-6">
        <form method="POST" action="{{ route('owner.tour-packages.availability.store', $tourPackage) }}" class="space-y-4">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Tanggal <span class="text-red-500">*</span></label>
                    <input type="date" name="date" required
                        class="w-full pl-4 py-2.5 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Kapasitas (kosong = default)</label>
                    <input type="number" name="capacity" min="1"
                        class="w-full pl-4 py-2.5 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none">
                </div>
                <div class="flex items-end">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="is_closed" value="1">
                        <span class="text-sm text-slate-700">Tutup tanggal ini</span>
                    </label>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Catatan</label>
                    <input type="text" name="note"
                        class="w-full pl-4 py-2.5 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none">
                </div>
            </div>

            <div class="pt-4 flex justify-end">
                <button type="submit" class="bg-primary hover:bg-primary/90 text-white px-5 py-2.5 rounded-lg font-semibold shadow-sm">Simpan Kuota</button>
            </div>
        </form>
    </div>

    <div class="bg-white border border-slate-200 rounded-xl p-6">
        <h3 class="font-semibold text-ink mb-4">Override Tanggal</h3>
        @if($availabilities->isEmpty())
            <p class="text-sm text-slate-500">Belum ada override kuota untuk paket ini.</p>
        @else
            <table class="min-w-full text-sm divide-y divide-slate-100">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-slate-500 font-semibold">Tanggal</th>
                        <th class="px-4 py-3 text-left text-slate-500 font-semibold">Kapasitas</th>
                        <th class="px-4 py-3 text-left text-slate-500 font-semibold">Status</th>
                        <th class="px-4 py-3 text-left text-slate-500 font-semibold">Catatan</th>
                        <th class="px-4 py-3 text-right text-slate-500 font-semibold">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($availabilities as $avail)
                        <tr>
                            <td class="px-4 py-3 text-slate-600">{{ \Carbon\Carbon::parse($avail->date)->format('d M Y') }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $avail->capacity ?? 'Default' }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold
                                    @if($avail->is_closed) bg-red-50 text-red-700
                                    @else bg-green-50 text-green-700 @endif">
                                    {{ $avail->is_closed ? 'Tutup' : 'Buka' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ $avail->note ?? '-' }}</td>
                            <td class="px-4 py-3 text-right">
                                <form method="POST" action="{{ route('owner.tour-packages.availability.destroy', [$tourPackage, $avail]) }}" class="inline-block" onsubmit="return confirm('Hapus override ini?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-xs text-red-600 hover:underline">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
@endsection

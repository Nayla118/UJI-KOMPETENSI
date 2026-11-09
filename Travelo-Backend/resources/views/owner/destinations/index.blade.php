@extends('layouts.owner')

@section('title', 'Destinasi Saya | Travelo Owner')

@section('content')
<header class="bg-white sticky top-0 z-20 border-b border-slate-200 px-8 py-4">
    <div class="max-w-7xl mx-auto">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold tracking-tight">Destinasi Saya</h2>
                <p class="text-sm text-slate-500">Kelola destinasi yang Anda miliki.</p>
            </div>
            @if (auth()->user()->hasPermission('destinations.create'))
                <a href="{{ route('owner.destinations.create') }}" class="bg-primary hover:bg-primary/90 text-white px-5 py-2.5 rounded-lg font-semibold flex items-center gap-2 transition-all shadow-sm">
                    <span class="material-symbols-outlined text-[20px]">add</span>
                    Tambah Destinasi
                </a>
            @endif
        </div>
    </div>
</header>

<div class="p-8 max-w-7xl mx-auto space-y-6">
    <div class="flex flex-col md:flex-row gap-4 items-center justify-between">
        <form method="GET" action="{{ route('owner.destinations.index') }}" class="relative w-full md:w-96">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">search</span>
            <input
                name="search"
                value="{{ request('search') }}"
                class="w-full pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none"
                placeholder="Cari destinasi, kota, atau negara..."
                type="text"
            />
        </form>
    </div>

    @if($destinations->isEmpty())
        <div class="text-center py-12">
            <span class="material-symbols-outlined text-4xl text-slate-300 mb-3">location_off</span>
            <p class="text-lg text-slate-500">Belum ada destinasi. <a href="{{ route('owner.destinations.create') }}" class="text-primary hover:underline font-medium">Tambahkan destinasi pertama Anda</a>.</p>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm divide-y divide-slate-100">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-slate-500 font-semibold">Nama</th>
                        <th class="px-4 py-3 text-left text-slate-500 font-semibold">Lokasi</th>
                        <th class="px-4 py-3 text-left text-slate-500 font-semibold">Kategori</th>
                        <th class="px-4 py-3 text-left text-slate-500 font-semibold">Paket</th>
                        <th class="px-4 py-3 text-left text-slate-500 font-semibold">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($destinations as $dest)
                        <tr>
                            <td class="px-4 py-3 text-ink font-medium">{{ $dest->name }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $dest->city }}, {{ $dest->country }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blush text-plum">
                                    {{ ucfirst($dest->category) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ $dest->packages_count }} paket</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('owner.destinations.show', $dest) }}" class="text-primary hover:underline text-sm">Lihat detail</a>
                                <a href="{{ route('owner.destinations.edit', $dest) }}" class="ml-3 text-amber-700 hover:underline text-sm">Edit</a>
                                @if (auth()->user()->hasPermission('destinations.delete'))
                                <form method="POST" action="{{ route('owner.destinations.destroy', $dest) }}" class="inline-block ml-3" onsubmit="return confirm('Hapus destinasi ini? Paket dan booking terkait akan ikut terpengaruh.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:underline text-sm">Hapus</button>
                                </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{ $destinations->links() }}
    @endif
</div>
@endsection

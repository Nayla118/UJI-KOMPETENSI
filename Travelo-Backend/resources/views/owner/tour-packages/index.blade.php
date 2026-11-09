@extends('layouts.owner')

@section('title', 'Paket Wisata | Travelo Owner')

@section('content')
<header class="bg-white sticky top-0 z-20 border-b border-slate-200 px-8 py-4">
    <div class="max-w-7xl mx-auto">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold tracking-tight">Paket Wisata</h2>
                <p class="text-sm text-slate-500">Kelola paket wisata untuk destinasi Anda.</p>
            </div>
            @if (auth()->user()->hasPermission('packages.create'))
                <a href="{{ route('owner.tour-packages.create') }}" class="bg-primary hover:bg-primary/90 text-white px-5 py-2.5 rounded-lg font-semibold flex items-center gap-2 transition-all shadow-sm">
                    <span class="material-symbols-outlined text-[20px]">add</span>
                    Tambah Paket
                </a>
            @endif
        </div>
    </div>
</header>

<div class="p-8 max-w-7xl mx-auto space-y-6">
    <form method="GET" action="{{ route('owner.tour-packages.index') }}" class="relative w-full md:w-96 mb-6">
        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">search</span>
        <input
            name="search"
            value="{{ request('search') }}"
            class="w-full pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none"
            placeholder="Cari paket wisata..."
            type="text"
        />
    </form>

    @if($packages->isEmpty())
        <div class="text-center py-12">
            <span class="material-symbols-outlined text-4xl text-slate-300 mb-3">tour_off</span>
            <p class="text-lg text-slate-500">Belum ada paket wisata. <a href="{{ route('owner.tour-packages.create') }}" class="text-primary hover:underline font-medium">Tambahkan paket pertama</a>.</p>
        </div>
    @else
        <div class="space-y-4">
            @foreach($packages as $pkg)
                <div class="bg-white border border-slate-200 rounded-xl p-6 flex flex-col md:flex-row gap-4 items-start md:items-center justify-between">
                    <div class="flex-1">
                        <h3 class="font-bold text-ink">{{ $pkg->title }}</h3>
                        <p class="text-sm text-slate-500 mt-1">{{ $pkg->destination->name }}</p>
                        <p class="text-sm text-slate-600 mt-2">{{ Str::limit($pkg->description ?? '', 120) }}</p>
                    </div>
                    <div class="flex items-center gap-4 text-sm">
                        <span class="text-ink font-medium">IDR {{ number_format((int) $pkg->price, 0, ',', '.') }}</span>
                        <span class="text-slate-500">{{ $pkg->duration_days }} hari</span>
                        <span class="text-slate-500">Maks {{ $pkg->max_people }} org</span>
                        <a href="{{ route('owner.tour-packages.edit', $pkg) }}" class="text-sm text-primary hover:underline">Edit paket</a>
                        <a href="{{ route('owner.tour-packages.availability', $pkg) }}" class="ml-3 text-sm text-amber-700 hover:underline">Kelola kuota</a>
                        @if (auth()->user()->hasPermission('packages.delete'))
                        <form method="POST" action="{{ route('owner.tour-packages.destroy', $pkg) }}" class="ml-3 inline-block" onsubmit="return confirm('Hapus paket ini?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 hover:underline text-sm">Hapus</button>
                        </form>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
        {{ $packages->links() }}
    @endif
</div>
@endsection

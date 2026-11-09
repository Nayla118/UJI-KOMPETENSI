@extends('layouts.owner')

@section('title', 'Detail Destinasi | Travelo Owner')

@section('content')
<header class="bg-white sticky top-0 z-20 border-b border-slate-200 px-8 py-4">
    <div class="max-w-7xl mx-auto">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold tracking-tight">{{ $destination->name }}</h2>
                <p class="text-sm text-slate-500">{{ $destination->city }}, {{ $destination->country }}</p>
            </div>
            <a href="{{ route('owner.destinations.edit', $destination) }}" class="bg-amber-50 hover:bg-amber-100 text-amber-700 px-5 py-2.5 rounded-lg font-semibold flex items-center gap-2 transition-colors">
                <span class="material-symbols-outlined text-[18px]">edit</span>
                Edit
            </a>
        </div>
    </div>
</header>

<div class="p-8 max-w-7xl mx-auto space-y-6">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white border border-slate-200 rounded-xl p-6">
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Kategori</p>
            <p class="text-lg font-bold text-ink mt-1">{{ ucfirst($destination->category) }}</p>
        </div>
        <div class="bg-white border border-slate-200 rounded-xl p-6">
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Paket Wisata</p>
            <p class="text-2xl font-bold text-ink mt-1">{{ $destination->tourPackages->count() }}</p>
        </div>
        <div class="bg-white border border-slate-200 rounded-xl p-6">
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Rating</p>
            <p class="text-2xl font-bold text-ink mt-1">{{ $destination->reviews_avg_rating ?? 0 }} / 5</p>
        </div>
    </div>

    <div class="bg-white border border-slate-200 rounded-xl p-6">
        <h3 class="font-semibold text-ink mb-4">Paket Wisata</h3>
        @if($destination->tourPackages->isEmpty())
            <p class="text-sm text-slate-500">Belum ada paket wisata untuk destinasi ini.</p>
        @else
            <div class="space-y-4">
                @foreach($destination->tourPackages as $pkg)
                    <div class="flex items-center gap-4 p-4 border border-slate-100 rounded-xl">
                        <img src="{{ $pkg->image ?? '/images/logos/travelo-logo-192.png' }}" class="w-20 h-20 object-cover rounded-lg">
                        <div class="flex-1">
                            <p class="font-semibold text-ink">{{ $pkg->title }}</p>
                            <p class="text-sm text-slate-500">{{ $pkg->duration_days }} hari · Maks {{ $pkg->max_people }} orang</p>
                            <p class="text-sm font-medium text-primary">IDR {{ number_format((int) $pkg->price, 0, ',', '.') }}</p>
                        </div>
                        <div class="text-right">
                            <a href="{{ route('owner.tour-packages.edit', $pkg) }}" class="text-sm text-primary hover:underline">Edit</a>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
        <a href="{{ route('owner.tour-packages.create', ['destination_id' => $destination->id]) }}" class="mt-4 inline-flex items-center gap-2 text-primary hover:underline text-sm">
            <span class="material-symbols-outlined text-[18px]">add</span>
            Tambah Paket Wisata
        </a>
    </div>
</div>
@endsection

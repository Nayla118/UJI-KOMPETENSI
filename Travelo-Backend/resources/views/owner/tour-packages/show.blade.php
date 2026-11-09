@extends('layouts.owner')

@section('title', 'Detail Paket Wisata | Travelo Owner')

@section('content')
<header class="bg-white sticky top-0 z-20 border-b border-slate-200 px-8 py-4">
    <div class="max-w-5xl mx-auto">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="text-sm text-slate-500 mb-1">
                    <a href="{{ route('owner.tour-packages.index') }}" class="hover:text-primary">Paket Wisata</a>
                    <span class="mx-1">/</span>
                    Detail
                </p>
                <h2 class="text-2xl font-bold tracking-tight">{{ $tourPackage->title }}</h2>
                <p class="text-sm text-slate-500">{{ $tourPackage->destination?->name }}</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('owner.tour-packages.index') }}" class="px-4 py-2.5 rounded-lg text-slate-600 border border-slate-200 hover:bg-slate-50">
                    Kembali
                </a>
                <a href="{{ route('owner.tour-packages.edit', $tourPackage) }}" class="bg-primary hover:bg-primary/90 text-white px-5 py-2.5 rounded-lg font-semibold flex items-center gap-2 transition-colors">
                    <span class="material-symbols-outlined text-[18px]">edit</span>
                    Edit
                </a>
            </div>
        </div>
    </div>
</header>

<div class="p-8 max-w-5xl mx-auto space-y-6">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white border border-slate-200 rounded-xl overflow-hidden md:col-span-1">
            @if($tourPackage->image)
                <img src="{{ $tourPackage->imageUrl }}" alt="{{ $tourPackage->title }}" class="w-full h-40 object-cover">
            @else
                <div class="w-full h-40 flex items-center justify-center bg-slate-100">
                    <span class="material-symbols-outlined text-slate-300 text-5xl">image</span>
                </div>
            @endif
        </div>

        <div class="md:col-span-2 grid grid-cols-2 gap-6">
            <div class="bg-white border border-slate-200 rounded-xl p-6">
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Harga</p>
                <p class="text-xl font-bold text-ink mt-1">IDR {{ number_format((int) $tourPackage->price, 0, ',', '.') }}</p>
            </div>
            <div class="bg-white border border-slate-200 rounded-xl p-6">
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Durasi</p>
                <p class="text-xl font-bold text-ink mt-1">{{ $tourPackage->duration_days }} hari</p>
            </div>
            <div class="bg-white border border-slate-200 rounded-xl p-6">
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Kapasitas</p>
                <p class="text-xl font-bold text-ink mt-1">Maks {{ $tourPackage->max_people }} orang</p>
            </div>
            <div class="bg-white border border-slate-200 rounded-xl p-6">
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Galeri</p>
                <p class="text-xl font-bold text-ink mt-1">{{ $tourPackage->images->count() }} foto</p>
            </div>
        </div>
    </div>

    <div class="bg-white border border-slate-200 rounded-xl p-6">
        <h3 class="font-semibold text-ink mb-3">Deskripsi</h3>
        <p class="text-sm text-slate-600 whitespace-pre-line">{{ $tourPackage->description ?: 'Belum ada deskripsi.' }}</p>
    </div>

    <div class="bg-white border border-slate-200 rounded-xl p-6">
        <h3 class="font-semibold text-ink mb-3">Itinerary</h3>
        @if($tourPackage->itineraryDays->isEmpty())
            <p class="text-sm text-slate-500">Belum ada itinerary.</p>
        @else
            <ol class="space-y-4">
                @foreach($tourPackage->itineraryDays as $day)
                    <li class="flex gap-4">
                        <span class="shrink-0 inline-flex items-center justify-center px-3 py-1 rounded-full bg-primary/10 text-primary text-xs font-bold">
                            Hari {{ $day->day_number }}
                        </span>
                        <div>
                            <p class="font-medium text-ink">{{ $day->title }}</p>
                            @if($day->description)
                                <p class="text-sm text-slate-600 mt-1 whitespace-pre-line">{{ $day->description }}</p>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ol>
        @endif
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white border border-slate-200 rounded-xl p-6">
            <h3 class="font-semibold text-ink mb-3">Termasuk</h3>
            @if(empty($tourPackage->inclusions))
                <p class="text-sm text-slate-500">Belum ada data.</p>
            @else
                <ul class="space-y-2 text-sm text-slate-600">
                    @foreach($tourPackage->inclusions as $item)
                        <li class="flex items-start gap-2">
                            <span class="material-symbols-outlined text-green-600 text-base">check_circle</span>
                            <span>{{ $item }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="bg-white border border-slate-200 rounded-xl p-6">
            <h3 class="font-semibold text-ink mb-3">Tidak Termasuk</h3>
            @if(empty($tourPackage->exclusions))
                <p class="text-sm text-slate-500">Belum ada data.</p>
            @else
                <ul class="space-y-2 text-sm text-slate-600">
                    @foreach($tourPackage->exclusions as $item)
                        <li class="flex items-start gap-2">
                            <span class="material-symbols-outlined text-red-600 text-base">cancel</span>
                            <span>{{ $item }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    <div class="bg-white border border-slate-200 rounded-xl p-6">
        <h3 class="font-semibold text-ink mb-3">Titik Kumpul</h3>
        @if(! $tourPackage->meeting_point && ! ($tourPackage->meeting_latitude && $tourPackage->meeting_longitude))
            <p class="text-sm text-slate-500">Belum ada titik kumpul.</p>
        @else
            <p class="text-sm text-slate-600">{{ $tourPackage->meeting_point ?: 'Koordinat tersedia di peta.' }}</p>
            @if($tourPackage->meeting_latitude && $tourPackage->meeting_longitude)
                <p class="text-xs text-slate-500 mt-1">
                    {{ $tourPackage->meeting_latitude }}, {{ $tourPackage->meeting_longitude }}
                </p>
                <a href="https://www.google.com/maps/search/?api=1&query={{ $tourPackage->meeting_latitude }},{{ $tourPackage->meeting_longitude }}"
                   target="_blank" rel="noopener"
                   class="mt-3 inline-flex items-center gap-1 text-sm text-primary hover:underline">
                    <span class="material-symbols-outlined text-base">map</span>
                    Buka di Google Maps
                </a>
            @endif
        @endif
    </div>

    <div class="bg-white border border-slate-200 rounded-xl p-6">
        <h3 class="font-semibold text-ink mb-3">Syarat &amp; Ketentuan</h3>
        <p class="text-sm text-slate-600 whitespace-pre-line">{{ $tourPackage->terms ?: 'Belum ada syarat & ketentuan.' }}</p>
    </div>

    @if($tourPackage->images->isNotEmpty())
    <div class="bg-white border border-slate-200 rounded-xl p-6">
        <h3 class="font-semibold text-ink mb-4">Galeri Foto</h3>
        <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
            @foreach($tourPackage->images as $image)
                <img src="{{ $image->url }}" alt="{{ $image->caption ?? $tourPackage->title }}"
                     class="w-full h-28 object-cover rounded-lg border border-slate-200">
            @endforeach
        </div>
    </div>
    @endif
</div>
@endsection

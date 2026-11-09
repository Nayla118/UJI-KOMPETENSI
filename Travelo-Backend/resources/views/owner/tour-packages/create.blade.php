@extends('layouts.owner')

@section('title', 'Tambah Paket Wisata | Travelo Owner')

@section('content')
<header class="bg-white sticky top-0 z-20 border-b border-slate-200 px-8 py-4">
    <div class="max-w-3xl mx-auto">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold tracking-tight">Tambah Paket Wisata</h2>
                <p class="text-sm text-slate-500">Buat paket wisata untuk destinasi Anda.</p>
            </div>
            <a href="{{ route('owner.tour-packages.index') }}" class="text-primary hover:underline text-sm">
                <span class="material-symbols-outlined text-[18px] mr-1">arrow_back</span>
                Kembali
            </a>
        </div>
    </div>
</header>

<div class="p-8 max-w-3xl mx-auto space-y-6">
    @if($errors->any())
    <div class="bg-red-50 border border-red-200 rounded-2xl p-4">
        <p class="font-semibold text-red-800">Periksa kembali data berikut:</p>
        <ul class="text-sm text-red-700 mt-2 space-y-1">
            @foreach($errors->all() as $error)
            <li>• {{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <form method="POST" action="{{ route('owner.tour-packages.store') }}" enctype="multipart/form-data" class="space-y-5">
        @csrf

        <article class="bg-white border border-slate-200 rounded-2xl p-6">
            <label class="block text-sm font-medium text-slate-700 mb-1">Destinasi <span class="text-red-500">*</span></label>
            <select name="destination_id" required
                class="w-full pl-4 py-2.5 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none">
                @foreach($destinations as $dest)
                    <option value="{{ $dest->id }}" {{ old('destination_id') == $dest->id ? 'selected' : '' }}>{{ $dest->name }}</option>
                @endforeach
            </select>
        </article>

        @include('owner.tour-packages.partials.content-fields', ['tourPackage' => null])

        <article class="bg-white border border-slate-200 rounded-2xl p-6">
            <h3 class="text-lg font-bold mb-4 flex items-center gap-2">
                <span class="material-symbols-outlined text-primary">image</span>
                Foto Utama
            </h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Upload foto utama</label>
                    <input type="file" name="cover_image" accept="image/jpeg,image/png,image/webp,image/gif"
                        class="block w-full text-sm text-slate-600 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-primary/10 file:text-primary hover:file:bg-primary/20">
                    <p class="text-xs text-slate-500 mt-1">JPG, PNG, WEBP, atau GIF, maks 4MB.</p>
                    @error('cover_image')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Atau URL gambar (opsional)</label>
                    <input type="url" name="image" value="{{ old('image') }}"
                        placeholder="https://..."
                        class="w-full pl-4 py-2.5 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 outline-none">
                    @error('image')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>
        </article>

        @include('owner.tour-packages.partials.gallery-upload', ['tourPackage' => null])

        <div class="pt-4 flex justify-end gap-3">
            <a href="{{ route('owner.tour-packages.index') }}" class="px-5 py-2.5 rounded-lg text-slate-600 hover:bg-blush transition-colors">Batal</a>
            <button type="submit" class="bg-primary hover:bg-primary/90 text-white px-5 py-2.5 rounded-lg font-semibold shadow-sm">Simpan Paket</button>
        </div>
    </form>
</div>

@include('admin.tour-packages.partials.content-scripts')
@endsection

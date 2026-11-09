@extends('layouts.owner')

@section('title', 'Tambah Destinasi | Travelo Owner')

@section('content')
<header class="bg-white sticky top-0 z-20 border-b border-slate-200 px-8 py-4">
    <div class="max-w-7xl mx-auto">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold tracking-tight">Tambah Destinasi</h2>
                <p class="text-sm text-slate-500">Isi data destinasi yang akan Anda kelola.</p>
            </div>
            <a href="{{ route('owner.destinations.index') }}" class="text-primary hover:underline text-sm">
                <span class="material-symbols-outlined text-[18px] mr-1">arrow_back</span>
                Kembali
            </a>
        </div>
    </div>
</header>

<div class="p-8 max-w-2xl mx-auto space-y-6">
    <form method="POST" action="{{ route('owner.destinations.store') }}" class="space-y-5">
        @csrf

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Nama Destinasi <span class="text-red-500">*</span></label>
                <input type="text" name="name" value="{{ old('name') }}" required
                    class="w-full pl-4 py-2.5 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none"
                    placeholder="Contoh: Gunung Bromo">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Kota <span class="text-red-500">*</span></label>
                <input type="text" name="city" value="{{ old('city') }}" required
                    class="w-full pl-4 py-2.5 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none"
                    placeholder="Contoh: Probolinggo">
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Negara <span class="text-red-500">*</span></label>
                <input type="text" name="country" value="{{ old('country', 'Indonesia') }}" required
                    class="w-full pl-4 py-2.5 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none"
                    placeholder="Contoh: Indonesia">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Kategori <span class="text-red-500">*</span></label>
                <select name="category" required
                    class="w-full pl-4 py-2.5 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none">
                    @foreach(['pantai' => 'Pantai', 'gunung' => 'Gunung', 'alam' => 'Alam', 'budaya' => 'Budaya', 'kota' => 'Kota', 'kuliner' => 'Kuliner'] as $key => $label)
                        <option value="{{ $key }}" {{ old('category') == $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Deskripsi</label>
            <textarea name="description" rows="4"
                class="w-full pl-4 py-2.5 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none">{{ old('description') }}</textarea>
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">URL Gambar (opsional)</label>
            <input type="url" name="image" value="{{ old('image') }}"
                class="w-full pl-4 py-2.5 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none"
                placeholder="https://example.com/foto.jpg">
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Rating (0-5)</label>
                <input type="number" name="rating" value="{{ old('rating') }}" step="0.1" min="0" max="5"
                    class="w-full pl-4 py-2.5 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none">
            </div>
            <div class="flex items-end">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="is_popular" value="1" {{ old('is_popular') ? 'checked' : '' }}>
                    <span class="text-sm text-slate-700">Tandai sebagai populer</span>
                </label>
            </div>
        </div>

        <div class="pt-4 flex justify-end gap-3">
            <a href="{{ route('owner.destinations.index') }}" class="px-5 py-2.5 rounded-lg text-slate-600 hover:bg-blush transition-colors">Batal</a>
            <button type="submit" class="bg-primary hover:bg-primary/90 text-white px-5 py-2.5 rounded-lg font-semibold shadow-sm">Simpan Destinasi</button>
        </div>
    </form>
</div>
@endsection

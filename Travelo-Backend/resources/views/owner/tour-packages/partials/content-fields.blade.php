@php
    $inclusionsValue = old('inclusions', $tourPackage ? implode("\n", (array) ($tourPackage->inclusions ?? [])) : '');
    if (is_array($inclusionsValue)) {
        $inclusionsValue = implode("\n", $inclusionsValue);
    }
    $exclusionsValue = old('exclusions', $tourPackage ? implode("\n", (array) ($tourPackage->exclusions ?? [])) : '');
    if (is_array($exclusionsValue)) {
        $exclusionsValue = implode("\n", $exclusionsValue);
    }

    $initialItinerary = $tourPackage
        ? $tourPackage->itineraryDays->map(fn ($day) => [
            'day_number' => $day->day_number,
            'title' => $day->title,
            'description' => $day->description,
        ])->all()
        : [];
    if (count($initialItinerary) === 0) {
        $initialItinerary = [['day_number' => 1, 'title' => '', 'description' => '']];
    }
    $itineraryRows = old('itinerary', $initialItinerary);
@endphp

<article class="bg-white border border-slate-200 rounded-2xl p-6">
    <h3 class="text-lg font-bold mb-4 flex items-center gap-2">
        <span class="material-symbols-outlined text-primary">info</span>
        Informasi Paket
    </h3>
    <div class="space-y-5">
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Judul Paket <span class="text-red-500">*</span></label>
            <input type="text" name="title" value="{{ old('title', $tourPackage?->title) }}" required
                class="w-full pl-4 py-2.5 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Deskripsi</label>
            <textarea name="description" rows="4"
                class="w-full pl-4 py-2.5 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none">{{ old('description', $tourPackage?->description) }}</textarea>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Harga (IDR) <span class="text-red-500">*</span></label>
                <input type="number" name="price" value="{{ old('price', $tourPackage?->price) }}" required min="0"
                    class="w-full pl-4 py-2.5 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Durasi (hari) <span class="text-red-500">*</span></label>
                <input type="number" name="duration_days" value="{{ old('duration_days', $tourPackage?->duration_days ?? 1) }}" required min="1"
                    class="w-full pl-4 py-2.5 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Maks Orang <span class="text-red-500">*</span></label>
                <input type="number" name="max_people" value="{{ old('max_people', $tourPackage?->max_people ?? 10) }}" required min="1"
                    class="w-full pl-4 py-2.5 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none">
            </div>
        </div>
    </div>
</article>

<article class="bg-white border border-slate-200 rounded-2xl p-6">
    <div class="flex items-center justify-between mb-3">
        <h3 class="text-lg font-bold flex items-center gap-2">
            <span class="material-symbols-outlined text-primary">route</span>
            Itinerary
        </h3>
        <button type="button" onclick="addItineraryRow()" class="inline-flex items-center gap-1 px-3 py-2 text-sm font-medium text-primary bg-primary/10 rounded-lg hover:bg-primary/20">
            <span class="material-symbols-outlined text-base">add</span>
            Tambah hari
        </button>
    </div>
    <p class="text-sm text-slate-500 mb-4">Satu baris per hari. Gunakan panah untuk mengubah urutan.</p>

    <div id="itinerary-rows" class="space-y-4">
        @foreach($itineraryRows as $index => $row)
        <div class="itinerary-row border border-slate-200 rounded-xl p-4 bg-slate-50/60" data-itinerary-row>
            <div class="grid grid-cols-1 md:grid-cols-[80px_1fr_auto] gap-3 items-start">
                <div>
                    <label class="text-xs font-semibold text-slate-500 uppercase">Hari</label>
                    <input type="number" name="itinerary[{{ $index }}][day_number]" value="{{ $row['day_number'] ?? $index + 1 }}" min="1"
                        class="mt-1 w-full px-3 py-2 bg-white border border-slate-200 rounded-lg focus:ring-2 focus:ring-primary/20 outline-none">
                </div>
                <div>
                    <label class="text-xs font-semibold text-slate-500 uppercase">Judul</label>
                    <input type="text" name="itinerary[{{ $index }}][title]" value="{{ $row['title'] ?? '' }}" placeholder="Contoh: Penjemputan & city tour"
                        class="mt-1 w-full px-3 py-2 bg-white border border-slate-200 rounded-lg focus:ring-2 focus:ring-primary/20 outline-none">
                </div>
                <div class="flex items-center gap-1 pt-5">
                    <button type="button" onclick="moveItineraryRow(this, -1)" class="p-2 text-slate-500 hover:bg-slate-200 rounded" title="Naikkan">
                        <span class="material-symbols-outlined text-lg">arrow_upward</span>
                    </button>
                    <button type="button" onclick="moveItineraryRow(this, 1)" class="p-2 text-slate-500 hover:bg-slate-200 rounded" title="Turunkan">
                        <span class="material-symbols-outlined text-lg">arrow_downward</span>
                    </button>
                    <button type="button" onclick="removeItineraryRow(this)" class="p-2 text-red-600 hover:bg-red-50 rounded" title="Hapus">
                        <span class="material-symbols-outlined text-lg">delete</span>
                    </button>
                </div>
            </div>
            <div class="mt-3">
                <label class="text-xs font-semibold text-slate-500 uppercase">Deskripsi</label>
                <textarea name="itinerary[{{ $index }}][description]" rows="3" placeholder="Kegiatan pada hari ini"
                    class="mt-1 w-full px-3 py-2 bg-white border border-slate-200 rounded-lg focus:ring-2 focus:ring-primary/20 outline-none">{{ $row['description'] ?? '' }}</textarea>
            </div>
        </div>
        @endforeach
    </div>
</article>

<article class="bg-white border border-slate-200 rounded-2xl p-6">
    <h3 class="text-lg font-bold mb-4 flex items-center gap-2">
        <span class="material-symbols-outlined text-primary">checklist</span>
        Termasuk & Tidak Termasuk
    </h3>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Termasuk</label>
            <textarea name="inclusions" rows="6" placeholder="Akomodasi&#10;Guide berlisensi&#10;Tiket masuk"
                class="w-full pl-4 py-2.5 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 outline-none">{{ $inclusionsValue }}</textarea>
            <p class="text-xs text-slate-500 mt-1">Satu item per baris.</p>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Tidak Termasuk</label>
            <textarea name="exclusions" rows="6" placeholder="Pengeluaran pribadi&#10;Asuransi perjalanan&#10;Aktivitas opsional"
                class="w-full pl-4 py-2.5 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 outline-none">{{ $exclusionsValue }}</textarea>
            <p class="text-xs text-slate-500 mt-1">Satu item per baris.</p>
        </div>
    </div>
</article>

<article class="bg-white border border-slate-200 rounded-2xl p-6">
    <h3 class="text-lg font-bold mb-4 flex items-center gap-2">
        <span class="material-symbols-outlined text-primary">pin_drop</span>
        Titik Kumpul & Syarat
    </h3>
    <div class="space-y-5">
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Titik kumpul</label>
            <input type="text" name="meeting_point" value="{{ old('meeting_point', $tourPackage?->meeting_point) }}"
                placeholder="Contoh: Bandara Juanda Terminal 1"
                class="w-full pl-4 py-2.5 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 outline-none">
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Latitude</label>
                <input type="number" step="any" name="meeting_latitude" value="{{ old('meeting_latitude', $tourPackage?->meeting_latitude) }}"
                    placeholder="-7.3795"
                    class="w-full pl-4 py-2.5 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Longitude</label>
                <input type="number" step="any" name="meeting_longitude" value="{{ old('meeting_longitude', $tourPackage?->meeting_longitude) }}"
                    placeholder="112.7870"
                    class="w-full pl-4 py-2.5 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 outline-none">
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Syarat & ketentuan</label>
            <textarea name="terms" rows="6" placeholder="Kebijakan pembatalan, barang bawaan, catatan penting..."
                class="w-full pl-4 py-2.5 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 outline-none">{{ old('terms', $tourPackage?->terms) }}</textarea>
        </div>
    </div>
</article>

<template id="itinerary-template">
    <div class="itinerary-row border border-slate-200 rounded-xl p-4 bg-slate-50/60" data-itinerary-row>
        <div class="grid grid-cols-1 md:grid-cols-[80px_1fr_auto] gap-3 items-start">
            <div>
                <label class="text-xs font-semibold text-slate-500 uppercase">Hari</label>
                <input type="number" name="itinerary[__INDEX__][day_number]" value="__DAY__" min="1"
                    class="mt-1 w-full px-3 py-2 bg-white border border-slate-200 rounded-lg focus:ring-2 focus:ring-primary/20 outline-none">
            </div>
            <div>
                <label class="text-xs font-semibold text-slate-500 uppercase">Judul</label>
                <input type="text" name="itinerary[__INDEX__][title]" placeholder="Contoh: Penjemputan & city tour"
                    class="mt-1 w-full px-3 py-2 bg-white border border-slate-200 rounded-lg focus:ring-2 focus:ring-primary/20 outline-none">
            </div>
            <div class="flex items-center gap-1 pt-5">
                <button type="button" onclick="moveItineraryRow(this, -1)" class="p-2 text-slate-500 hover:bg-slate-200 rounded" title="Naikkan">
                    <span class="material-symbols-outlined text-lg">arrow_upward</span>
                </button>
                <button type="button" onclick="moveItineraryRow(this, 1)" class="p-2 text-slate-500 hover:bg-slate-200 rounded" title="Turunkan">
                    <span class="material-symbols-outlined text-lg">arrow_downward</span>
                </button>
                <button type="button" onclick="removeItineraryRow(this)" class="p-2 text-red-600 hover:bg-red-50 rounded" title="Hapus">
                    <span class="material-symbols-outlined text-lg">delete</span>
                </button>
            </div>
        </div>
        <div class="mt-3">
            <label class="text-xs font-semibold text-slate-500 uppercase">Deskripsi</label>
            <textarea name="itinerary[__INDEX__][description]" rows="3" placeholder="Kegiatan pada hari ini"
                class="mt-1 w-full px-3 py-2 bg-white border border-slate-200 rounded-lg focus:ring-2 focus:ring-primary/20 outline-none"></textarea>
        </div>
    </div>
</template>

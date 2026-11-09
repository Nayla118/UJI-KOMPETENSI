@php($existingImages = $tourPackage?->images ?? collect())

<article class="bg-white border border-slate-200 rounded-2xl p-6">
    <h3 class="text-lg font-bold mb-1 flex items-center gap-2">
        <span class="material-symbols-outlined text-primary">photo_library</span>
        Galeri Foto
    </h3>
    <p class="text-sm text-slate-500 mb-4">Unggah maksimal 10 foto (JPG, PNG, WEBP, atau GIF, maks 4MB per foto).</p>

    @if($existingImages->isNotEmpty())
    <div class="grid grid-cols-2 gap-3 mb-4">
        @foreach($existingImages as $image)
        <label class="relative block rounded-lg overflow-hidden border border-slate-200 cursor-pointer">
            <img src="{{ $image->url }}" alt="{{ $image->caption ?? 'Foto galeri' }}" class="w-full h-24 object-cover">
            <span class="absolute inset-x-0 bottom-0 bg-black/60 text-white text-xs px-2 py-1 flex items-center gap-1">
                <input type="checkbox" name="remove_images[]" value="{{ $image->id }}" class="rounded border-white/60">
                Hapus
            </span>
        </label>
        @endforeach
    </div>
    @endif

    <input type="file" name="images[]" multiple accept="image/jpeg,image/png,image/webp,image/gif"
        class="block w-full text-sm text-slate-600 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-primary/10 file:text-primary hover:file:bg-primary/20">
    <p id="gallery-file-list" class="text-xs text-slate-500 mt-2"></p>

    @error('images')<p class="text-sm text-red-600 mt-2">{{ $message }}</p>@enderror
    @error('images.*')<p class="text-sm text-red-600 mt-2">{{ $message }}</p>@enderror
</article>

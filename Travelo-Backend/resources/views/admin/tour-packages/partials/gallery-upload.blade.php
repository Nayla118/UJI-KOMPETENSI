@php($existingImages = $tourPackage?->images ?? collect())

<div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
    <h3 class="text-lg font-semibold mb-1 flex items-center gap-2">
        <span class="material-symbols-outlined text-primary">photo_library</span>
        Gallery
    </h3>
    <p class="text-sm text-slate-500 mb-4">Upload up to 10 photos (JPG, PNG, WEBP or GIF, max 4MB each).</p>

    @if($existingImages->isNotEmpty())
    <div class="grid grid-cols-2 gap-3 mb-4">
        @foreach($existingImages as $image)
        <label class="relative block rounded-lg overflow-hidden border border-slate-200 group cursor-pointer">
            <img src="{{ $image->url }}" alt="{{ $image->caption ?? 'Gallery image' }}" class="w-full h-24 object-cover">
            <span class="absolute inset-x-0 bottom-0 bg-black/60 text-white text-xs px-2 py-1 flex items-center gap-1">
                <input type="checkbox" name="remove_images[]" value="{{ $image->id }}" class="rounded border-white/60">
                Remove
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
    @error('remove_images.*')<p class="text-sm text-red-600 mt-2">{{ $message }}</p>@enderror
</div>

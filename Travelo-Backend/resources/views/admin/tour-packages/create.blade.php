@extends('layouts.admin')

@section('title', 'Create Tour Package - TravelAdmin')

<!-- Header -->
@section('content')
<header class="h-16 border-b border-slate-200 bg-white/80 backdrop-blur flex items-center justify-between px-8 sticky top-0 z-10">
    <h2 class="text-xl font-semibold">Create New Package</h2>
    <div class="flex items-center gap-4">
        <a href="{{ route('admin.tour-packages.index') }}" class="p-2 text-slate-500 hover:bg-slate-100 rounded-full">
            <span class="material-symbols-outlined">notifications</span>
        </a>
        <a href="{{ route('admin.tour-packages.index') }}" class="px-4 py-2 text-sm font-medium text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50">
            Cancel
        </a>
        <button type="submit" form="tour-package-form" class="px-4 py-2 text-sm font-medium text-white bg-primary rounded-lg hover:bg-primary/90 shadow-sm">
            Save Package
        </button>
    </div>
</header>

<div class="max-w-5xl mx-auto p-8">
    <form id="tour-package-form" method="POST" action="{{ route('admin.tour-packages.store') }}" enctype="multipart/form-data" class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        @csrf
        <!-- Left Column: Form Details -->
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
                <h3 class="text-lg font-semibold mb-6 flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary">info</span>
                    Package Information
                </h3>
                <div class="space-y-5">
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium text-slate-700">Tour Title</label>
                        <input name="title" value="{{ old('title') }}" class="w-full rounded-lg border-slate-200 focus:ring-primary focus:border-primary px-4 py-3" placeholder="e.g. Luxury Safari in Kenya" type="text" required/>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-medium text-slate-700">Destination</label>
                            <select name="destination_id" class="w-full rounded-lg border-slate-200 focus:ring-primary focus:border-primary px-4 py-3" required>
                                <option value="">Select location</option>
                                @foreach($destinations as $destination)
                                <option value="{{ $destination->id }}">{{ $destination->city }}, {{ $destination->country }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-medium text-slate-700">Price per Person ($)</label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">$</span>
                                <input name="price" value="{{ old('price') }}" class="w-full pl-8 pr-4 py-3 rounded-lg border-slate-200 focus:ring-primary focus:border-primary" placeholder="0.00" type="number" step="0.01" required/>
                            </div>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-medium text-slate-700">Duration (Days)</label>
                            <input name="duration_days" value="{{ old('duration_days') }}" class="w-full rounded-lg border-slate-200 focus:ring-primary focus:border-primary px-4 py-3" placeholder="7" type="number" min="1" required/>
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-medium text-slate-700">Max Group Size</label>
                            <input name="max_people" value="{{ old('max_people') }}" class="w-full rounded-lg border-slate-200 focus:ring-primary focus:border-primary px-4 py-3" placeholder="12" type="number" min="1" required/>
                        </div>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium text-slate-700">Description</label>
                        <textarea name="description" class="w-full rounded-lg border-slate-200 focus:ring-primary focus:border-primary px-4 py-3" placeholder="Describe the tour itinerary, highlights and inclusions..." rows="6">{{ old('description') }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Media and Settings -->
        <div class="space-y-6">
            <!-- Media Upload -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary">image</span>
                    Media
                </h3>
                <div class="border-2 border-dashed border-slate-200 rounded-xl p-8 text-center hover:border-primary transition-colors cursor-pointer group">
                    <input type="file" name="image" id="image-upload" class="hidden" accept="image/*" onchange="previewImage(event)"/>
                    <label for="image-upload" class="cursor-pointer">
                        <div class="mb-4 inline-flex items-center justify-center w-12 h-12 rounded-full bg-primary/10 text-primary group-hover:bg-primary group-hover:text-white transition-all">
                            <span class="material-symbols-outlined">cloud_upload</span>
                        </div>
                        <p class="text-sm font-medium">Click to upload cover photo</p>
                        <p class="text-xs text-slate-500 mt-1">PNG, JPG up to 10MB</p>
                    </label>
                </div>
                <div class="mt-4" id="image-preview-container" style="display: none;">
                    <div class="aspect-video bg-slate-100 rounded-lg overflow-hidden relative">
                        <img id="image-preview" class="w-full h-full object-cover" src="" alt="Preview"/>
                        <button type="button" onclick="removeImage()" class="absolute top-1 right-1 p-1 bg-red-500 text-white rounded shadow">
                            <span class="material-symbols-outlined text-xs">close</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Quick Preview Card -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="h-32 bg-primary/20 bg-cover bg-center" id="preview-banner">
                    <div class="w-full h-full flex items-center justify-center">
                        <span class="material-symbols-outlined text-4xl text-primary/50">photo</span>
                    </div>
                </div>
                <div class="p-4">
                    <div class="flex justify-between items-start">
                        <span class="text-xs font-bold uppercase tracking-wider text-primary">Preview Card</span>
                        <span class="bg-green-100 text-green-700 text-[10px] px-2 py-0.5 rounded-full font-bold">DRAFT</span>
                    </div>
                    <h4 id="preview-title" class="mt-2 font-bold text-slate-900 truncate">Tour Package Title</h4>
                    <div class="flex items-center gap-1 mt-1 text-slate-500 text-xs">
                        <span class="material-symbols-outlined text-sm">location_on</span>
                        <span id="preview-destination">Destination Name</span>
                    </div>
                    <div class="mt-4 pt-4 border-t border-slate-100 flex justify-between items-center">
                        <p id="preview-price" class="text-lg font-bold text-primary">$0</p>
                        <p id="preview-duration" class="text-xs text-slate-500">7 Days</p>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
    function previewImage(event) {
        const file = event.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('image-preview').src = e.target.result;
                document.getElementById('image-preview-container').style.display = 'block';
                document.getElementById('preview-banner').style.backgroundImage = 'url(' + e.target.result + ')';
            };
            reader.readAsDataURL(file);
        }
    }

    function removeImage() {
        document.getElementById('image-upload').value = '';
        document.getElementById('image-preview-container').style.display = 'none';
        document.getElementById('preview-banner').style.backgroundImage = '';
    }

    // Live preview
    document.querySelector('input[name="title"]').addEventListener('input', function(e) {
        document.getElementById('preview-title').textContent = e.target.value || 'Tour Package Title';
    });

    document.querySelector('select[name="destination_id"]').addEventListener('change', function(e) {
        const selected = e.target.options[e.target.selectedIndex];
        document.getElementById('preview-destination').textContent = selected.text || 'Destination Name';
    });

    document.querySelector('input[name="price"]').addEventListener('input', function(e) {
        document.getElementById('preview-price').textContent = '$' + parseFloat(e.target.value || 0).toFixed(2);
    });

    document.querySelector('input[name="duration_days"]').addEventListener('input', function(e) {
        document.getElementById('preview-duration').textContent = (e.target.value || 0) + ' Days';
    });
</script>
@endsection

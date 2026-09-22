@extends('layouts.admin')

@section('title', 'Edit Tour Package - Travelo Admin')

@section('content')
<div class="p-8">
    <!-- Page Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
        <div>
            <div class="flex items-center gap-2 text-sm text-slate-500 mb-2">
                <a href="{{ route('admin.tour-packages.index') }}" class="hover:text-primary transition-colors">Tour Packages</a>
                <span class="material-symbols-outlined text-xs">chevron_right</span>
                <span class="text-slate-700 font-medium">Edit Package</span>
            </div>
            <h1 class="text-3xl font-bold text-slate-900">Edit Tour Package</h1>
            <p class="text-slate-500 mt-1">Update tour package details and pricing</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.tour-packages.index') }}" class="inline-flex items-center gap-2 px-5 py-2.5 bg-white text-slate-700 font-semibold rounded-xl border border-slate-200 hover:bg-slate-50 transition-all shadow-sm">
                <span class="material-symbols-outlined text-lg">arrow_back</span>
                Back
            </a>
            <button type="submit" form="tour-package-form" class="inline-flex items-center gap-2 px-6 py-2.5 bg-linear-to-r from-primary to-primary-dark text-white font-semibold rounded-xl shadow-lg shadow-primary/25 hover:shadow-primary/40 hover:-translate-y-0.5 transition-all duration-200">
                <span class="material-symbols-outlined text-lg">save</span>
                Save Changes
            </button>
        </div>
    </div>

    <!-- Success/Error Messages -->
    @if(session('success'))
    <div class="mb-6 bg-green-50 border border-green-200 rounded-xl p-4 flex items-start gap-3">
        <span class="material-symbols-outlined text-green-600 text-2xl">check_circle</span>
        <div>
            <p class="font-semibold text-green-800">Success!</p>
            <p class="text-sm text-green-700">{{ session('success') }}</p>
        </div>
    </div>
    @endif

    @if($errors->any())
    <div class="mb-6 bg-red-50 border border-red-200 rounded-xl p-4">
        <div class="flex items-start gap-3">
            <span class="material-symbols-outlined text-red-600 text-2xl">error</span>
            <div>
                <p class="font-semibold text-red-800">Please correct the following errors:</p>
                <ul class="text-sm text-red-700 mt-2 space-y-1">
                    @foreach($errors->all() as $error)
                    <li>• {{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
    @endif

    <!-- Main Form -->
    <form id="tour-package-form" action="{{ route('admin.tour-packages.update', $tourPackage->id) }}" method="POST" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Main Content (2 columns) -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Package Information -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="bg-linear-to-r from-primary/5 to-transparent px-6 py-4 border-b border-slate-100">
                        <h2 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary">info</span>
                            Package Information
                        </h2>
                        <p class="text-sm text-slate-500 mt-1">Basic details about your tour package</p>
                    </div>
                    <div class="p-6 space-y-6">
                        <div class="space-y-2">
                            <label for="title" class="block text-sm font-semibold text-slate-700 flex items-center gap-2">
                                <span class="material-symbols-outlined text-primary text-base">badge</span>
                                Package Title
                                <span class="text-red-500">*</span>
                            </label>
                            <input
                                type="text"
                                id="title"
                                name="title"
                                value="{{ old('title', $tourPackage->title) }}"
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none text-slate-700 bg-white"
                                placeholder="e.g. Ultimate Bali Adventure Tour"
                                required
                            >
                            <p class="text-xs text-slate-500">Create a compelling title that attracts travelers</p>
                        </div>

                        <div class="space-y-2">
                            <label for="description" class="block text-sm font-semibold text-slate-700 flex items-center gap-2">
                                <span class="material-symbols-outlined text-primary text-base">description</span>
                                Description
                            </label>
                            <textarea
                                id="description"
                                name="description"
                                rows="6"
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none text-slate-700 bg-white resize-none"
                                placeholder="Describe what makes this tour special..."
                            >{{ old('description', $tourPackage->description) }}</textarea>
                            <p class="text-xs text-slate-500">Include highlights, itinerary, and what's included</p>
                        </div>
                    </div>
                </div>

                <!-- Pricing & Details -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="bg-linear-to-r from-primary/5 to-transparent px-6 py-4 border-b border-slate-100">
                        <h2 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary">tune</span>
                            Pricing & Details
                        </h2>
                        <p class="text-sm text-slate-500 mt-1">Set pricing and package specifications</p>
                    </div>
                    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <label for="price" class="flex text-sm font-semibold text-slate-700 items-center gap-2">
                                <span class="material-symbols-outlined text-primary text-base">attach_money</span>
                                Price (IDR)
                                <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 font-semibold">Rp</span>
                                <input
                                    type="number"
                                    id="price"
                                    name="price"
                                    value="{{ old('price', $tourPackage->price) }}"
                                    class="w-full pl-12 pr-4 py-3 rounded-xl border border-slate-200 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none text-slate-700 bg-white"
                                    placeholder="0"
                                    min="0"
                                    step="0.01"
                                    required
                                >
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label for="duration_days" class="flex text-sm font-semibold text-slate-700 items-center gap-2">
                                <span class="material-symbols-outlined text-primary text-base">calendar_month</span>
                                Duration (Days)
                                <span class="text-red-500">*</span>
                            </label>
                            <input
                                type="number"
                                id="duration_days"
                                name="duration_days"
                                value="{{ old('duration_days', $tourPackage->duration_days) }}"
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none text-slate-700 bg-white"
                                placeholder="5"
                                min="1"
                                required
                            >
                        </div>

                        <div class="space-y-2">
                            <label for="max_people" class="flex text-sm font-semibold text-slate-700 items-center gap-2">
                                <span class="material-symbols-outlined text-primary text-base">people</span>
                                Max People
                                <span class="text-red-500">*</span>
                            </label>
                            <input
                                type="number"
                                id="max_people"
                                name="max_people"
                                value="{{ old('max_people', $tourPackage->max_people) }}"
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none text-slate-700 bg-white"
                                placeholder="10"
                                min="1"
                                required
                            >
                        </div>

                        <div class="space-y-2">
                            <label for="rating" class="flex text-sm font-semibold text-slate-700 items-center gap-2">
                                <span class="material-symbols-outlined text-primary text-base">star</span>
                                Rating
                            </label>
                            <input
                                type="number"
                                id="rating"
                                name="rating"
                                value="{{ old('rating', $tourPackage->rating ?? 4.5) }}"
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none text-slate-700 bg-white"
                                placeholder="4.5"
                                min="0"
                                max="5"
                                step="0.1"
                            >
                            <p class="text-xs text-slate-500">0-5 scale</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar (1 column) -->
            <div class="space-y-6">
                <!-- Destination Selection -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="bg-linear-to-r from-primary/5 to-transparent px-6 py-4 border-b border-slate-100">
                        <h2 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary">location_on</span>
                            Destination
                        </h2>
                        <p class="text-sm text-slate-500 mt-1">Where the tour takes place</p>
                    </div>
                    <div class="p-6">
                        <div class="space-y-2">
                            <label for="destination_id" class="block text-sm font-semibold text-slate-700 flex items-center gap-2">
                                <span class="material-symbols-outlined text-primary text-base">map</span>
                                Select Destination
                                <span class="text-red-500">*</span>
                            </label>
                            <select
                                id="destination_id"
                                name="destination_id"
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none text-slate-700 bg-white cursor-pointer"
                                required
                            >
                                <option value="">Choose a destination...</option>
                                @foreach($destinations as $destination)
                                <option value="{{ $destination->id }}" {{ $destination->id == $tourPackage->destination_id ? 'selected' : '' }}>
                                    {{ $destination->name }} - {{ $destination->city }}, {{ $destination->country }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Package Image -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="bg-linear-to-r from-primary/5 to-transparent px-6 py-4 border-b border-slate-100">
                        <h2 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary">image</span>
                            Package Image
                        </h2>
                        <p class="text-sm text-slate-500 mt-1">Showcase your tour</p>
                    </div>
                    <div class="p-6 space-y-4">
                        <div class="space-y-2">
                            <label class="flex text-sm font-semibold text-slate-700 items-center gap-2">
                                <span class="material-symbols-outlined text-primary text-base">photo_library</span>
                                Cover Photo
                            </label>

                            <div class="relative group overflow-hidden rounded-xl mb-4" id="image-preview-container">
                                @if($tourPackage->image)
                                <x-image-with-fallback
                                    :src="$tourPackage->image"
                                    :alt="$tourPackage->title"
                                    type="tour-package"
                                    containerClass="w-full h-48"
                                />
                                @else
                                <div class="w-full h-48 flex items-center justify-center bg-slate-100">
                                    <span class="material-symbols-outlined text-slate-300 text-5xl">image</span>
                                </div>
                                @endif
                                <div class="absolute inset-0 bg-linear-to-t from-black/60 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-all duration-300 flex items-end justify-between p-4">
                                    <span class="text-white font-semibold text-sm">{{ $tourPackage->title }}</span>
                                    <button type="button" onclick="document.getElementById('image-upload').click()" class="text-white hover:text-primary transition-colors">
                                        <span class="material-symbols-outlined">edit</span>
                                    </button>
                                </div>
                            </div>

                            <div id="upload-area" class="relative border-2 border-dashed border-slate-300 rounded-xl p-6 text-center hover:border-primary/50 transition-colors cursor-pointer bg-slate-50/50 {{ $tourPackage->image ? '' : '' }}" onclick="document.getElementById('image-upload').click()">
                                <input
                                    type="file"
                                    id="image-upload"
                                    name="image"
                                    accept="image/*"
                                    class="hidden"
                                    onchange="previewImage(this)"
                                >
                                <div class="space-y-3">
                                    <div class="w-16 h-16 mx-auto rounded-full bg-primary/10 flex items-center justify-center">
                                        <span class="material-symbols-outlined text-primary text-3xl">cloud_upload</span>
                                    </div>
                                    <div>
                                        <p class="text-sm font-semibold text-slate-700" id="upload-text">
                                            {{ $tourPackage->image ? 'Click to change image' : 'Click to upload image' }}
                                        </p>
                                        <p class="text-xs text-slate-500 mt-1">PNG, JPG, GIF up to 2MB</p>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center justify-between p-3 bg-slate-50 rounded-lg" id="image-status-message">
                                <div class="flex items-center gap-2">
                                    <span class="material-symbols-outlined" id="status-icon">{{ $tourPackage->image ? 'check_circle' : 'image' }}</span>
                                    <span class="text-sm" id="status-text" style="color: {{ $tourPackage->image ? '#16a34a' : '#6b7280' }}">
                                        {{ $tourPackage->image ? 'Current image will be kept' : 'No image selected' }}
                                    </span>
                                </div>
                                @if($tourPackage->image)
                                <button type="button" onclick="removeImage()" class="inline-flex items-center gap-1 px-3 py-1.5 bg-red-50 text-red-600 font-medium rounded-lg hover:bg-red-100 transition-all text-xs">
                                    <span class="material-symbols-outlined text-sm">delete</span>
                                    Remove
                                </button>
                                @endif
                            </div>

                            @error('image')
                            <p class="text-sm text-red-600 mt-2 flex items-center gap-2">
                                <span class="material-symbols-outlined text-sm">error</span>
                                {{ $message }}
                            </p>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Package Stats -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="bg-linear-to-r from-primary/5 to-transparent px-6 py-4 border-b border-slate-100">
                        <h2 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary">insights</span>
                            Package Stats
                        </h2>
                        <p class="text-sm text-slate-500 mt-1">Quick overview</p>
                    </div>
                    <div class="p-6 space-y-3">
                        <div class="bg-linear-to-br from-slate-50 to-white rounded-xl p-4 border border-slate-100 shadow-sm">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-blue-100 flex items-center justify-center">
                                    <span class="material-symbols-outlined text-blue-600">calendar_today</span>
                                </div>
                                <div>
                                    <p class="text-2xl font-bold text-slate-800">{{ $tourPackage->duration_days }}</p>
                                    <p class="text-xs text-slate-500 uppercase tracking-wide font-semibold">Days</p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-linear-to-br from-slate-50 to-white rounded-xl p-4 border border-slate-100 shadow-sm">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-green-100 flex items-center justify-center">
                                    <span class="material-symbols-outlined text-green-600">people</span>
                                </div>
                                <div>
                                    <p class="text-2xl font-bold text-slate-800">{{ $tourPackage->max_people }}</p>
                                    <p class="text-xs text-slate-500 uppercase tracking-wide font-semibold">Max Guests</p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-linear-to-br from-slate-50 to-white rounded-xl p-4 border border-slate-100 shadow-sm">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-amber-100 flex items-center justify-center">
                                    <span class="material-symbols-outlined text-amber-600">attach_money</span>
                                </div>
                                <div>
                                    <p class="text-2xl font-bold text-slate-800">Rp {{ number_format($tourPackage->price, 0, ',', '.') }}</p>
                                    <p class="text-xs text-slate-500 uppercase tracking-wide font-semibold">Price</p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-linear-to-br from-slate-50 to-white rounded-xl p-4 border border-slate-100 shadow-sm">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-purple-100 flex items-center justify-center">
                                    <span class="material-symbols-outlined text-purple-600">star</span>
                                </div>
                                <div>
                                    <p class="text-2xl font-bold text-slate-800">{{ number_format($tourPackage->rating ?? 4.5, 1) }}</p>
                                    <p class="text-xs text-slate-500 uppercase tracking-wide font-semibold">Rating</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Help Card -->
                <div class="bg-linear-to-br from-primary/10 to-blue-600/10 rounded-2xl p-6 border border-primary/20">
                    <div class="flex items-start gap-3">
                        <span class="material-symbols-outlined text-primary text-3xl">lightbulb</span>
                        <div>
                            <p class="font-bold text-slate-900">Pro Tip</p>
                            <p class="text-sm text-slate-600 mt-1">
                                High-quality images can increase bookings by up to 40%. Make sure your cover photo showcases the best experience!
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sticky Bottom Action Bar -->
        <div class="sticky bottom-0 bg-white/80 backdrop-blur-lg border-t border-slate-200 py-4 -mx-8 px-8 flex items-center justify-between">
            <div class="text-sm text-slate-600">
                <span class="font-semibold text-slate-900">Last updated:</span> {{ $tourPackage->updated_at->diffForHumans() }}
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.tour-packages.index') }}" class="inline-flex items-center gap-2 px-5 py-2.5 bg-white text-slate-700 font-semibold rounded-xl border border-slate-200 hover:bg-slate-50 transition-all shadow-sm">
                    Cancel
                </a>
                <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 bg-linear-to-r from-primary to-primary-dark text-white font-semibold rounded-xl shadow-lg shadow-primary/25 hover:shadow-primary/40 hover:-translate-y-0.5 transition-all duration-200">
                    <span class="material-symbols-outlined text-lg">save</span>
                    Save Changes
                </button>
            </div>
        </div>
    </form>
</div>

@push('scripts')
<script>
    function previewImage(input) {
        if (input.files && input.files[0]) {
            const file = input.files[0];
            const reader = new FileReader();

            reader.onload = function(e) {
                const container = document.getElementById('image-preview-container');
                
                // Create new image preview
                const newHTML = `
                    <img src="${e.target.result}" class="w-full h-48 object-cover" alt="New Preview">
                    <div class="absolute inset-0 bg-linear-to-t from-black/60 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-all duration-300 flex items-end justify-between p-4">
                        <span class="text-white font-semibold text-sm">New Image Preview</span>
                        <button type="button" onclick="document.getElementById('image-upload').click()" class="text-white hover:text-primary transition-colors">
                            <span class="material-symbols-outlined">edit</span>
                        </button>
                    </div>
                `;
                container.innerHTML = newHTML;
                
                // Update status message
                const statusText = document.getElementById('status-text');
                const statusIcon = document.getElementById('status-icon');
                if (statusText) {
                    statusText.textContent = 'New image will replace the current one';
                    statusText.style.color = '#16a34a';
                }
                if (statusIcon) {
                    statusIcon.textContent = 'check_circle';
                    statusIcon.style.color = '#16a34a';
                }
                
                // Update upload text
                document.getElementById('upload-text').textContent = 'Click to change image';
            }

            reader.readAsDataURL(file);
        }
    }

    function removeImage() {
        const imageUpload = document.getElementById('image-upload');
        const container = document.getElementById('image-preview-container');
        const statusText = document.getElementById('status-text');
        const statusIcon = document.getElementById('status-icon');

        if (imageUpload) {
            imageUpload.value = '';
        }

        // Reset to placeholder
        const newHTML = `
            <div class="w-full h-48 flex items-center justify-center bg-slate-100">
                <span class="material-symbols-outlined text-slate-300 text-5xl">image</span>
            </div>
        `;
        container.innerHTML = newHTML;
        
        // Update status message
        if (statusText) {
            statusText.textContent = 'Image will be removed';
            statusText.style.color = '#dc2626';
        }
        if (statusIcon) {
            statusIcon.textContent = 'delete';
            statusIcon.style.color = '#dc2626';
        }
        
        // Update upload text
        document.getElementById('upload-text').textContent = 'Click to upload image';
    }
</script>
@endpush
@endsection

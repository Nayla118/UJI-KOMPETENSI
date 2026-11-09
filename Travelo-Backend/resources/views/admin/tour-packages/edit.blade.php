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
            <p class="text-slate-500 mt-1">Update tour package details, itinerary and media</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.tour-packages.index') }}" class="inline-flex items-center gap-2 px-5 py-2.5 bg-white text-slate-700 font-semibold rounded-xl border border-slate-200 hover:bg-slate-50 transition-all shadow-sm">
                <span class="material-symbols-outlined text-lg">arrow_back</span>
                Back
            </a>
            <button type="submit" form="tour-package-form" class="inline-flex items-center gap-2 px-6 py-2.5 bg-primary text-white font-semibold rounded-xl shadow-sm transition-colors duration-200">
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
            @include('admin.tour-packages.partials.content-fields', ['tourPackage' => $tourPackage])

            <!-- Sidebar (1 column) -->
            <div class="space-y-6">
                <!-- Destination Selection -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="bg-blush/40 px-6 py-4 border-b border-slate-100">
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
                                <option value="{{ $destination->id }}" {{ old('destination_id', $tourPackage->destination_id) == $destination->id ? 'selected' : '' }}>
                                    {{ $destination->name }} - {{ $destination->city }}, {{ $destination->country }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Package Image -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="bg-blush/40 px-6 py-4 border-b border-slate-100">
                        <h2 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary">image</span>
                            Cover Image
                        </h2>
                        <p class="text-sm text-slate-500 mt-1">Showcase your tour</p>
                    </div>
                    <div class="p-6 space-y-4">
                        <div class="space-y-2">
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
                            </div>

                            <div id="upload-area" class="relative border-2 border-dashed border-slate-300 rounded-xl p-6 text-center hover:border-primary/50 transition-colors cursor-pointer bg-slate-50/50" onclick="document.getElementById('image-upload').click()">
                                <input
                                    type="file"
                                    id="image-upload"
                                    name="image"
                                    accept="image/jpeg,image/png,image/webp,image/gif"
                                    class="hidden"
                                    onchange="previewImage(this)"
                                >
                                <div class="space-y-3">
                                    <div class="w-16 h-16 mx-auto rounded-full bg-primary/10 flex items-center justify-center">
                                        <span class="material-symbols-outlined text-primary text-3xl">cloud_upload</span>
                                    </div>
                                    <div>
                                        <p class="text-sm font-semibold text-slate-700" id="upload-text">
                                            {{ $tourPackage->image ? 'Click to change cover' : 'Click to upload cover' }}
                                        </p>
                                        <p class="text-xs text-slate-500 mt-1">JPG, PNG, WEBP or GIF up to 4MB</p>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center justify-between p-3 bg-slate-50 rounded-lg" id="image-status-message">
                                <div class="flex items-center gap-2">
                                    <span class="material-symbols-outlined" id="status-icon">{{ $tourPackage->image ? 'check_circle' : 'image' }}</span>
                                    <span class="text-sm" id="status-text" style="color: {{ $tourPackage->image ? '#4F7D66' : '#6E5A68' }}">
                                        {{ $tourPackage->image ? 'Current cover will be kept' : 'No cover selected' }}
                                    </span>
                                </div>
                                @if($tourPackage->image)
                                <label class="inline-flex items-center gap-1 px-3 py-1.5 bg-red-50 text-red-600 font-medium rounded-lg hover:bg-red-100 transition-all text-xs cursor-pointer">
                                    <input type="checkbox" name="remove_cover" value="1" class="rounded border-red-300">
                                    Remove cover
                                </label>
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

                @include('admin.tour-packages.partials.gallery-upload', ['tourPackage' => $tourPackage])

                <!-- Package Stats -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="bg-blush/40 px-6 py-4 border-b border-slate-100">
                        <h2 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary">insights</span>
                            Package Stats
                        </h2>
                        <p class="text-sm text-slate-500 mt-1">Quick overview</p>
                    </div>
                    <div class="p-6 space-y-3">
                        <div class="bg-cream rounded-xl p-4 border border-slate-100 shadow-sm">
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
                        <div class="bg-cream rounded-xl p-4 border border-slate-100 shadow-sm">
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
                        <div class="bg-cream rounded-xl p-4 border border-slate-100 shadow-sm">
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
                        <div class="bg-cream rounded-xl p-4 border border-slate-100 shadow-sm">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-blush flex items-center justify-center">
                                    <span class="material-symbols-outlined text-primary">star</span>
                                </div>
                                <div>
                                    @if (($tourPackage->destination?->reviews_count ?? 0) > 0)
                                        <p class="text-2xl font-bold text-slate-800">{{ number_format($tourPackage->destination->reviews_avg_rating, 1) }}</p>
                                        <p class="text-xs text-slate-500 uppercase tracking-wide font-semibold">Rating ({{ $tourPackage->destination->reviews_count }})</p>
                                    @else
                                        <p class="text-sm font-semibold text-slate-500">No reviews yet</p>
                                        <p class="text-xs text-slate-500 uppercase tracking-wide font-semibold">Destination rating</p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Help Card -->
                <div class="bg-blush rounded-2xl p-6 border border-primary/20">
                    <div class="flex items-start gap-3">
                        <span class="material-symbols-outlined text-primary text-3xl">lightbulb</span>
                        <div>
                            <p class="font-bold text-slate-900">Pro Tip</p>
                            <p class="text-sm text-slate-600 mt-1">
                                A complete itinerary and a real photo gallery help travellers understand exactly what they are buying.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sticky Bottom Action Bar -->
        <div class="sticky bottom-0 bg-white border-t border-slate-200 py-4 -mx-8 px-8 flex items-center justify-between">
            <div class="text-sm text-slate-600">
                <span class="font-semibold text-slate-900">Last updated:</span> {{ $tourPackage->updated_at->diffForHumans() }}
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.tour-packages.index') }}" class="inline-flex items-center gap-2 px-5 py-2.5 bg-white text-slate-700 font-semibold rounded-xl border border-slate-200 hover:bg-slate-50 transition-all shadow-sm">
                    Cancel
                </a>
                <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 bg-primary text-white font-semibold rounded-xl shadow-sm transition-colors duration-200">
                    <span class="material-symbols-outlined text-lg">save</span>
                    Save Changes
                </button>
            </div>
        </div>
    </form>
</div>

<script>
    function previewImage(input) {
        if (input.files && input.files[0]) {
            const file = input.files[0];
            const reader = new FileReader();

            reader.onload = function(e) {
                const container = document.getElementById('image-preview-container');

                container.innerHTML = `
                    <img src="${e.target.result}" class="w-full h-48 object-cover" alt="New Preview">
                `;

                const statusText = document.getElementById('status-text');
                const statusIcon = document.getElementById('status-icon');
                if (statusText) {
                    statusText.textContent = 'New cover will replace the current one';
                    statusText.style.color = '#4F7D66';
                }
                if (statusIcon) {
                    statusIcon.textContent = 'check_circle';
                    statusIcon.style.color = '#4F7D66';
                }

                document.getElementById('upload-text').textContent = 'Click to change cover';
            }

            reader.readAsDataURL(file);
        }
    }
</script>
@include('admin.tour-packages.partials.content-scripts')
@endsection

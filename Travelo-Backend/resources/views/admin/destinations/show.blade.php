@extends('layouts.admin')

@section('title', 'Destination Details - Travelo Admin')
@section('header', 'Destination Details')

@section('content')
<div class="space-y-6">
    <div class="flex justify-between items-center">
        <a href="{{ route('admin.destinations.index') }}" class="text-primary hover:text-blue-700 flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to Destinations
        </a>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.destinations.edit', $destination->id) }}" class="bg-primary hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center gap-2 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                Edit
            </a>
            <form action="{{ route('admin.destinations.destroy', $destination->id) }}" method="POST" class="inline">
                @csrf
                @method('DELETE')
                <button type="submit" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg flex items-center gap-2 transition-colors" onclick="return confirm('Are you sure you want to delete this destination? This action cannot be undone.')">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                    Delete
                </button>
            </form>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Info -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Image & Basic Info -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <x-image-with-fallback
                    :src="$destination->image"
                    :alt="$destination->name"
                    type="destination"
                    containerClass="w-full h-64"
                />
                <div class="p-6">
                    <div class="flex items-start justify-between">
                        <div>
                            <h3 class="text-2xl font-bold text-gray-800">{{ $destination->name }}</h3>
                            <p class="text-gray-500 mt-1">{{ $destination->city }}, {{ $destination->country }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            @if($destination->is_popular)
                            <span class="bg-amber-100 text-amber-800 px-3 py-1 rounded-full text-sm font-medium">
                                <span class="inline-flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                    </svg>
                                    Popular
                                </span>
                            </span>
                            @endif
                            <span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm font-medium flex items-center gap-1">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                </svg>
                                {{ number_format($destination->rating, 1) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Description -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Description</h3>
                <p class="text-gray-600 leading-relaxed">
                    {{ $destination->description ?: 'No description available.' }}
                </p>
            </div>

            <!-- Tour Packages -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Tour Packages ({{ $destination->tourPackages->count() }})</h3>
                @if($destination->tourPackages->count() > 0)
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach($destination->tourPackages as $package)
                    <a href="{{ route('admin.tour-packages.show', $package->id) }}" class="flex items-center gap-4 p-4 border border-gray-100 rounded-lg hover:bg-slate-50 transition-colors">
                        @if($package->image)
                        <div class="w-16 h-16 bg-cover bg-center rounded-lg shrink-0" style="background-image: url('{{ asset('storage/' . $package->image) }}')"></div>
                        @else
                        <div class="w-16 h-16 bg-slate-100 rounded-lg flex items-center justify-center shrink-0">
                            <svg class="w-8 h-8 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        @endif
                        <div class="flex-1 min-w-0">
                            <h4 class="font-medium text-gray-800 truncate">{{ $package->title }}</h4>
                            <p class="text-sm text-gray-500">{{ $package->duration_days }} days • Rp {{ number_format($package->price, 0, ',', '.') }}</p>
                        </div>
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                    @endforeach
                </div>
                @else
                <p class="text-gray-500 text-center py-4">No tour packages available for this destination.</p>
                @endif
            </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Quick Info -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Quick Information</h3>
                <div class="space-y-4">
                    <div class="flex justify-between items-center py-2 border-b border-gray-100">
                        <span class="text-gray-500">Destination ID</span>
                        <span class="font-medium text-gray-800">#{{ $destination->id }}</span>
                    </div>
                    <div class="flex justify-between items-center py-2 border-b border-gray-100">
                        <span class="text-gray-500">Created At</span>
                        <span class="font-medium text-gray-800">{{ $destination->created_at->format('d M Y') }}</span>
                    </div>
                    <div class="flex justify-between items-center py-2 border-b border-gray-100">
                        <span class="text-gray-500">Last Updated</span>
                        <span class="font-medium text-gray-800">{{ $destination->updated_at->format('d M Y') }}</span>
                    </div>
                    <div class="flex justify-between items-center py-2">
                        <span class="text-gray-500">Total Packages</span>
                        <span class="font-medium text-gray-800">{{ $destination->tourPackages->count() }}</span>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Quick Actions</h3>
                <div class="space-y-3">
                    <a href="{{ route('admin.tour-packages.create') }}?destination_id={{ $destination->id }}" class="w-full flex items-center justify-center gap-2 px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                        </svg>
                        Add Tour Package
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

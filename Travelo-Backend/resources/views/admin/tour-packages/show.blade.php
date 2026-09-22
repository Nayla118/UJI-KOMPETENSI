@extends('layouts.admin')

@section('title', 'Tour Package Details - Travelo Admin')
@section('header', 'Tour Package Details')

@section('content')
<div class="space-y-6">
    <div class="flex justify-between items-center">
        <a href="{{ route('admin.tour-packages.index') }}" class="text-primary hover:text-blue-700 flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to Tour Packages
        </a>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.tour-packages.edit', $tourPackage->id) }}" class="bg-primary hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center gap-2 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                Edit
            </a>
            <form action="{{ route('admin.tour-packages.destroy', $tourPackage->id) }}" method="POST" class="inline">
                @csrf
                @method('DELETE')
                <button type="submit" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg flex items-center gap-2 transition-colors" onclick="return confirm('Are you sure you want to delete this tour package? This action cannot be undone.')">
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
                    :src="$tourPackage->image"
                    :alt="$tourPackage->title"
                    type="tour-package"
                    containerClass="w-full h-64"
                />
                <div class="p-6">
                    <h3 class="text-2xl font-bold text-gray-800">{{ $tourPackage->title }}</h3>
                    <a href="{{ route('admin.destinations.show', $tourPackage->destination->id) }}" class="text-primary hover:text-blue-700 mt-2 inline-flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        {{ $tourPackage->destination->name }}, {{ $tourPackage->destination->city }}
                    </a>
                </div>
            </div>

            <!-- Description -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Description</h3>
                <p class="text-gray-600 leading-relaxed">
                    {{ $tourPackage->description ?: 'No description available.' }}
                </p>
            </div>

            <!-- Recent Bookings -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Recent Bookings ({{ $tourPackage->bookings->count() }})</h3>
                @if($tourPackage->bookings->count() > 0)
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="border-b border-gray-100">
                                <th class="pb-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Booking ID</th>
                                <th class="pb-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Customer</th>
                                <th class="pb-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Date</th>
                                <th class="pb-3 text-xs font-semibold uppercase tracking-wider text-gray-500">People</th>
                                <th class="pb-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Status</th>
                                <th class="pb-3 text-xs font-semibold uppercase tracking-wider text-gray-500 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach($tourPackage->bookings->take(5) as $booking)
                            <tr>
                                <td class="py-3 font-medium">#{{ $booking->id }}</td>
                                <td class="py-3">{{ $booking->user->name ?? 'N/A' }}</td>
                                <td class="py-3">{{ $booking->booking_date->format('d M Y') }}</td>
                                <td class="py-3">{{ $booking->people_count }}</td>
                                <td class="py-3">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full
                                        @if($booking->booking_status === 'confirmed') bg-green-100 text-green-800
                                        @elseif($booking->booking_status === 'pending') bg-yellow-100 text-yellow-800
                                        @elseif($booking->booking_status === 'cancelled') bg-red-100 text-red-800
                                        @else bg-blue-100 text-blue-800 @endif">
                                        {{ ucfirst($booking->booking_status) }}
                                    </span>
                                </td>
                                <td class="py-3 text-right">Rp {{ number_format($booking->total_price, 0, ',', '.') }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <p class="text-gray-500 text-center py-4">No bookings yet for this tour package.</p>
                @endif
            </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Package Details -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Package Details</h3>
                <div class="space-y-4">
                    <div class="flex justify-between items-center py-2 border-b border-gray-100">
                        <span class="text-gray-500 flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Price
                        </span>
                        <span class="font-bold text-primary">Rp {{ number_format($tourPackage->price, 0, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between items-center py-2 border-b border-gray-100">
                        <span class="text-gray-500 flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            Duration
                        </span>
                        <span class="font-medium text-gray-800">{{ $tourPackage->duration_days }} Days</span>
                    </div>
                    <div class="flex justify-between items-center py-2 border-b border-gray-100">
                        <span class="text-gray-500 flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                            Max People
                        </span>
                        <span class="font-medium text-gray-800">{{ $tourPackage->max_people }} People</span>
                    </div>
                    <div class="flex justify-between items-center py-2">
                        <span class="text-gray-500 flex items-center gap-2">
                            <svg class="w-4 h-4 text-amber-400" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                            </svg>
                            Rating
                        </span>
                        <span class="font-medium text-gray-800">{{ number_format($tourPackage->rating, 1) }}</span>
                    </div>
                </div>
            </div>

            <!-- Quick Info -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Quick Information</h3>
                <div class="space-y-4">
                    <div class="flex justify-between items-center py-2 border-b border-gray-100">
                        <span class="text-gray-500">Package ID</span>
                        <span class="font-medium text-gray-800">#{{ $tourPackage->id }}</span>
                    </div>
                    <div class="flex justify-between items-center py-2 border-b border-gray-100">
                        <span class="text-gray-500">Created At</span>
                        <span class="font-medium text-gray-800">{{ $tourPackage->created_at->format('d M Y') }}</span>
                    </div>
                    <div class="flex justify-between items-center py-2 border-b border-gray-100">
                        <span class="text-gray-500">Last Updated</span>
                        <span class="font-medium text-gray-800">{{ $tourPackage->updated_at->format('d M Y') }}</span>
                    </div>
                    <div class="flex justify-between items-center py-2">
                        <span class="text-gray-500">Total Bookings</span>
                        <span class="font-medium text-gray-800">{{ $tourPackage->bookings->count() }}</span>
                    </div>
                </div>
            </div>

            <!-- Destination Link -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Destination</h3>
                <a href="{{ route('admin.destinations.show', $tourPackage->destination->id) }}" class="flex items-center gap-4 p-3 border border-gray-100 rounded-lg hover:bg-slate-50 transition-colors">
                    @if($tourPackage->destination->image)
                    <div class="w-12 h-12 bg-cover bg-center rounded-lg shrink-0" style="background-image: url('{{ asset('storage/' . $tourPackage->destination->image) }}')"></div>
                    @else
                    <div class="w-12 h-12 bg-slate-100 rounded-lg flex items-center justify-center shrink-0">
                        <svg class="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </div>
                    @endif
                    <div class="flex-1 min-w-0">
                        <h4 class="font-medium text-gray-800">{{ $tourPackage->destination->name }}</h4>
                        <p class="text-sm text-gray-500">{{ $tourPackage->destination->city }}, {{ $tourPackage->destination->country }}</p>
                    </div>
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
            </div>
        </div>
    </div>
</div>
@endsection

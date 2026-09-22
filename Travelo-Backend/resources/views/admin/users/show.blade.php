@extends('layouts.admin')

@section('title', 'User Details - Travelo Admin')
@section('header', 'User Details')

@section('content')
<div class="space-y-6">
    <div class="flex justify-between items-center">
        <a href="{{ route('admin.users.index') }}" class="text-primary hover:text-blue-700">
            <i class="fas fa-arrow-left mr-2"></i>Back to Users
        </a>
        <div class="flex gap-2">
            <a href="{{ route('admin.users.edit', $user->id) }}" class="px-4 py-2 bg-yellow-500 text-white rounded-lg hover:bg-yellow-600">
                <i class="fas fa-edit mr-2"></i>Edit
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- User Info -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">User Information</h3>
            <div class="space-y-4">
                <div class="flex justify-between">
                    <span class="text-gray-500">User ID</span>
                    <span class="font-medium text-gray-800">#{{ $user->id }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Name</span>
                    <span class="font-medium text-gray-800">{{ $user->name }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Email</span>
                    <span class="font-medium text-gray-800">{{ $user->email }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Photo</span>
                    @if($user->photo)
                        <img src="{{ $user->photo }}" alt="{{ $user->name }}" class="w-12 h-12 rounded-full">
                    @else
                        <span class="text-gray-400">No photo</span>
                    @endif
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Firebase UID</span>
                    <span class="font-mono text-xs text-gray-600">{{ $user->firebase_uid }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Joined</span>
                    <span class="font-medium text-gray-800">{{ $user->created_at->format('d M Y H:i') }}</span>
                </div>
            </div>
        </div>

        <!-- Statistics -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Statistics</h3>
            <div class="grid grid-cols-2 gap-4">
                <div class="bg-purple-50 rounded-lg p-4">
                    <p class="text-sm text-purple-600">Total Bookings</p>
                    <p class="text-2xl font-bold text-purple-800">{{ $user->bookings->count() }}</p>
                </div>
                <div class="bg-green-50 rounded-lg p-4">
                    <p class="text-sm text-green-600">Total Spent</p>
                    <p class="text-2xl font-bold text-green-800">Rp {{ number_format($user->bookings->sum('total_price'), 0, ',', '.') }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- User's Bookings -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="p-6 border-b border-gray-100">
            <h3 class="text-lg font-semibold text-gray-800">Booking History</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Package</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">People</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($user->bookings as $booking)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-800">#{{ $booking->id }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $booking->tourPackage->title }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $booking->booking_date->format('d M Y') }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $booking->people_count }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">Rp {{ number_format($booking->total_price, 0, ',', '.') }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ booking_status_badge($booking->booking_status) }}">
                                {{ ucfirst($booking->booking_status) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <a href="{{ route('admin.bookings.show', $booking->id) }}" class="text-primary hover:text-blue-700">
                                <i class="fas fa-eye"></i>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-6 py-4 text-center text-sm text-gray-500">No bookings yet</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

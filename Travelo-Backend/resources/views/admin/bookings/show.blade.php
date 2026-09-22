@extends('layouts.admin')

@section('title', 'Booking Details - Travelo Admin')
@section('header', 'Booking Details')

@section('content')
<div class="space-y-6">
    <div class="flex justify-between items-center">
        <a href="{{ route('admin.bookings.index') }}" class="text-primary hover:text-blue-700">
            <i class="fas fa-arrow-left mr-2"></i>Back to Bookings
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Booking Info -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Booking Information</h3>
            <div class="space-y-4">
                <div class="flex justify-between">
                    <span class="text-gray-500">Booking ID</span>
                    <span class="font-medium text-gray-800">#{{ $booking->id }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Booking Date</span>
                    <span class="font-medium text-gray-800">{{ $booking->booking_date->format('d M Y') }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">People Count</span>
                    <span class="font-medium text-gray-800">{{ $booking->people_count }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Total Price</span>
                    <span class="font-medium text-gray-800">Rp {{ number_format($booking->total_price, 0, ',', '.') }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Payment Status</span>
                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ payment_status_badge($booking->payment_status) }}">
                        {{ ucfirst($booking->payment_status) }}
                    </span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Booking Status</span>
                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ booking_status_badge($booking->booking_status) }}">
                        {{ ucfirst($booking->booking_status) }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Customer Info -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Customer Information</h3>
            <div class="space-y-4">
                <div class="flex justify-between">
                    <span class="text-gray-500">Name</span>
                    <span class="font-medium text-gray-800">{{ $booking->user->name }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Email</span>
                    <span class="font-medium text-gray-800">{{ $booking->user->email }}</span>
                </div>
            </div>
        </div>

        <!-- Package Info -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Tour Package Information</h3>
            <div class="space-y-4">
                <div class="flex justify-between">
                    <span class="text-gray-500">Package</span>
                    <span class="font-medium text-gray-800">{{ $booking->tourPackage->title }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Destination</span>
                    <span class="font-medium text-gray-800">{{ $booking->tourPackage->destination->name }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Duration</span>
                    <span class="font-medium text-gray-800">{{ $booking->tourPackage->duration_days }} days</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Price per Person</span>
                    <span class="font-medium text-gray-800">Rp {{ number_format($booking->tourPackage->price, 0, ',', '.') }}</span>
                </div>
            </div>
        </div>

        <!-- Payment Info -->
        @if($booking->payment)
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Payment Information</h3>
            <div class="space-y-4">
                <div class="flex justify-between">
                    <span class="text-gray-500">Transaction ID</span>
                    <span class="font-medium text-gray-800">{{ $booking->payment->midtrans_transaction_id ?? 'N/A' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Payment Method</span>
                    <span class="font-medium text-gray-800">{{ $booking->payment->payment_method ?? 'N/A' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Amount</span>
                    <span class="font-medium text-gray-800">Rp {{ number_format($booking->payment->amount, 0, ',', '.') }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Status</span>
                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ payment_status_badge($booking->payment->payment_status) }}">
                        {{ ucfirst($booking->payment->payment_status) }}
                    </span>
                </div>
            </div>
        </div>
        @endif
    </div>

    <!-- Status Update Forms -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Update Status</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <form action="{{ route('admin.bookings.updateStatus', $booking->id) }}" method="POST">
                @csrf
                @method('PATCH')
                <div class="flex gap-2">
                    <select name="booking_status" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg">
                        <option value="pending" {{ $booking->booking_status === 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="confirmed" {{ $booking->booking_status === 'confirmed' ? 'selected' : '' }}>Confirmed</option>
                        <option value="cancelled" {{ $booking->booking_status === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                        <option value="completed" {{ $booking->booking_status === 'completed' ? 'selected' : '' }}>Completed</option>
                    </select>
                    <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700">Update</button>
                </div>
            </form>

            <form action="{{ route('admin.bookings.updatePaymentStatus', $booking->id) }}" method="POST">
                @csrf
                @method('PATCH')
                <div class="flex gap-2">
                    <select name="payment_status" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg">
                        <option value="pending" {{ $booking->payment_status === 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="paid" {{ $booking->payment_status === 'paid' ? 'selected' : '' }}>Paid</option>
                        <option value="failed" {{ $booking->payment_status === 'failed' ? 'selected' : '' }}>Failed</option>
                        <option value="expired" {{ $booking->payment_status === 'expired' ? 'selected' : '' }}>Expired</option>
                    </select>
                    <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

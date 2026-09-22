@extends('layouts.admin')

@section('title', 'Payment Details - Travelo Admin')
@section('header', 'Payment Details')

@section('content')
<div class="space-y-6">
    <div class="flex justify-between items-center">
        <a href="{{ route('admin.payments.index') }}" class="text-primary hover:text-blue-700">
            <i class="fas fa-arrow-left mr-2"></i>Back to Payments
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Payment Info -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Payment Information</h3>
            <div class="space-y-4">
                <div class="flex justify-between">
                    <span class="text-gray-500">Payment ID</span>
                    <span class="font-medium text-gray-800">#{{ $payment->id }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Transaction ID</span>
                    <span class="font-medium text-gray-800">{{ $payment->midtrans_transaction_id ?? 'N/A' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Payment Method</span>
                    <span class="font-medium text-gray-800">{{ $payment->payment_method ?? 'N/A' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Amount</span>
                    <span class="font-medium text-gray-800">Rp {{ number_format($payment->amount, 0, ',', '.') }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Status</span>
                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ payment_status_badge($payment->payment_status) }}">
                        {{ ucfirst($payment->payment_status) }}
                    </span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Created At</span>
                    <span class="font-medium text-gray-800">{{ $payment->created_at->format('d M Y H:i') }}</span>
                </div>
            </div>
        </div>

        <!-- Booking Info -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Booking Information</h3>
            <div class="space-y-4">
                <div class="flex justify-between">
                    <span class="text-gray-500">Booking ID</span>
                    <span class="font-medium text-gray-800">#{{ $payment->booking->id }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Customer</span>
                    <span class="font-medium text-gray-800">{{ $payment->booking->user->name }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Package</span>
                    <span class="font-medium text-gray-800">{{ $payment->booking->tourPackage->title }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Booking Date</span>
                    <span class="font-medium text-gray-800">{{ $payment->booking->booking_date->format('d M Y') }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">People</span>
                    <span class="font-medium text-gray-800">{{ $payment->booking->people_count }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Total Price</span>
                    <span class="font-medium text-gray-800">Rp {{ number_format($payment->booking->total_price, 0, ',', '.') }}</span>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

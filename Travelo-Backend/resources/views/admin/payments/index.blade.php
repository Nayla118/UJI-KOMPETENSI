@extends('layouts.admin')

@section('title', 'Payment Logs - TravelAdmin')

<!-- Header -->
@section('content')
<!-- Header -->
<header class="h-16 border-b border-slate-200 bg-white flex items-center justify-between px-8">
    <div class="flex items-center gap-2">
        <span class="material-symbols-outlined text-slate-400">history</span>
        <h2 class="text-lg font-semibold">Payment History</h2>
    </div>
    <div class="flex items-center gap-4">
        <div class="relative w-64">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-lg">search</span>
            <input class="w-full bg-slate-100 border-none rounded-lg py-2 pl-10 pr-4 text-sm focus:ring-2 focus:ring-primary" placeholder="Search transactions..." type="text"/>
        </div>
    </div>
</header>

<!-- Content Area -->
<div class="flex-1 overflow-y-auto p-8">
    <!-- Top Section -->
    <div class="mb-8">
        <h3 class="text-3xl font-extrabold tracking-tight mb-2">Payment Logs</h3>
        <p class="text-slate-500">Real-time detailed history of all transactions processed via Midtrans Gateway.</p>
    </div>

    <!-- Tabs & Filters -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
        <div class="flex border-b border-slate-200 overflow-x-auto">
            <a href="{{ route('admin.payments.index') }}" class="px-4 py-2 text-sm font-bold text-primary border-b-2 border-primary whitespace-nowrap">All Transactions</a>
            <a href="{{ route('admin.payments.index', ['status' => 'success']) }}" class="px-4 py-2 text-sm font-medium text-slate-500 hover:text-slate-700 whitespace-nowrap">Success</a>
            <a href="{{ route('admin.payments.index', ['status' => 'pending']) }}" class="px-4 py-2 text-sm font-medium text-slate-500 hover:text-slate-700 whitespace-nowrap">Pending</a>
            <a href="{{ route('admin.payments.index', ['status' => 'settlement']) }}" class="px-4 py-2 text-sm font-medium text-slate-500 hover:text-slate-700 whitespace-nowrap">Settlement</a>
        </div>
    </div>

    <!-- Table Card -->
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200">
                        <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase tracking-wider">Transaction ID</th>
                        <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase tracking-wider">Booking ID</th>
                        <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase tracking-wider">Amount</th>
                        <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase tracking-wider">Payment Method</th>
                        <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase tracking-wider">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse($payments as $payment)
                    <tr class="hover:bg-slate-50/50 transition-colors">
                        <td class="px-6 py-5 text-sm font-semibold">
                            {{ $payment->midtrans_transaction_id ?? 'N/A' }}
                        </td>
                        <td class="px-6 py-5 text-sm text-slate-600">
                            #BK-{{ str_pad($payment->booking_id, 4, '0', STR_PAD_LEFT) }}
                        </td>
                        <td class="px-6 py-5 text-sm font-medium text-slate-900">
                            ${{ number_format($payment->amount, 0, ',', '.') }}
                        </td>
                        <td class="px-6 py-5">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-slate-100 text-slate-700">
                                <span class="material-symbols-outlined text-sm">credit_card</span>
                                {{ $payment->payment_method ?? 'N/A' }}
                            </span>
                        </td>
                        <td class="px-6 py-5">
                            @if($payment->payment_status === 'success' || $payment->payment_status === 'settlement')
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700">
                                <span class="material-symbols-outlined text-sm">check_circle</span>
                                {{ ucfirst($payment->payment_status) }}
                            </span>
                            @elseif($payment->payment_status === 'pending')
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-700">
                                <span class="material-symbols-outlined text-sm">schedule</span>
                                Pending
                            </span>
                            @else
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-700">
                                {{ ucfirst($payment->payment_status) }}
                            </span>
                            @endif
                        </td>
                        <td class="px-6 py-5 text-sm text-slate-500">
                            {{ $payment->created_at->format('M d, Y, H:i') }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-8 text-center text-slate-400">
                            No payment records found
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <!-- Pagination -->
        <div class="px-6 py-4 bg-slate-50 border-t border-slate-200 flex items-center justify-between">
            <p class="text-xs text-slate-500">Showing {{ $payments->firstItem() ?? 0 }} to {{ $payments->lastItem() ?? 0 }} of {{ $payments->total() }} results</p>
            <div class="flex gap-2">
                @if($payments->previousPageUrl())
                <a href="{{ $payments->previousPageUrl() }}" class="px-3 py-1 text-xs font-medium border border-slate-300 rounded bg-white disabled:opacity-50">Previous</a>
                @else
                <button class="px-3 py-1 text-xs font-medium border border-slate-300 rounded bg-white disabled:opacity-50" disabled>Previous</button>
                @endif

                @if($payments->nextPageUrl())
                <a href="{{ $payments->nextPageUrl() }}" class="px-3 py-1 text-xs font-medium border border-slate-300 rounded bg-white">Next</a>
                @else
                <button class="px-3 py-1 text-xs font-medium border border-slate-300 rounded bg-white disabled:opacity-50" disabled>Next</button>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

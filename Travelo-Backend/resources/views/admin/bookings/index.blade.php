@extends('layouts.admin')

@section('title', 'Booking Management - TravelAdmin')

<!-- Header -->
@section('content')
<header class="h-16 border-b border-slate-200 bg-white/80 backdrop-blur-md sticky top-0 z-10 px-8 flex items-center justify-between">
    <div class="flex items-center gap-4">
        <span class="material-symbols-outlined text-slate-400">search</span>
        <input class="bg-transparent border-none focus:ring-0 text-sm w-64" placeholder="Search bookings..." type="text"/>
    </div>
    <div class="flex items-center gap-4">
        <button class="relative p-2 text-slate-500 hover:bg-slate-100 rounded-full transition-colors">
            <span class="material-symbols-outlined">notifications</span>
            <span class="absolute top-2 right-2 w-2 h-2 bg-red-500 rounded-full border-2 border-white"></span>
        </button>
    </div>
</header>

<div class="p-8 max-w-[1400px] mx-auto">
    <!-- Page Title -->
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-8">
        <div>
            <h2 class="text-3xl font-black text-slate-900 tracking-tight">Booking Management</h2>
            <p class="text-slate-500 mt-1">Review, update, and manage travel reservations</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.export.bookings') }}" class="flex items-center gap-2 bg-white border border-slate-200 px-4 py-2 rounded-lg text-sm font-semibold hover:bg-slate-50 transition-colors">
                <span class="material-symbols-outlined text-sm">download</span>
                Export Report
            </a>
        </div>
    </div>

    <!-- Tabs -->
    <div class="border-b border-slate-200 mb-6">
        <div class="flex gap-8">
            <a href="{{ route('admin.bookings.index') }}" class="pb-4 text-sm font-bold border-b-2 {{ !request('status') ? 'border-primary text-primary' : 'border-transparent text-slate-500' }}">All Bookings</a>
            <a href="{{ route('admin.bookings.index', ['status' => 'pending']) }}" class="pb-4 text-sm font-semibold border-b-2 {{ request('status') == 'pending' ? 'border-primary text-primary' : 'border-transparent text-slate-500' }} relative">
                Pending
                @if($pendingCount > 0)
                <span class="ml-1.5 px-2 py-0.5 bg-amber-100 text-amber-700 text-[10px] rounded-full">{{ $pendingCount }}</span>
                @endif
            </a>
            <a href="{{ route('admin.bookings.index', ['status' => 'confirmed']) }}" class="pb-4 text-sm font-semibold border-b-2 {{ request('status') == 'confirmed' ? 'border-primary text-primary' : 'border-transparent text-slate-500' }}">Confirmed</a>
            <a href="{{ route('admin.bookings.index', ['status' => 'cancelled']) }}" class="pb-4 text-sm font-semibold border-b-2 {{ request('status') == 'cancelled' ? 'border-primary text-primary' : 'border-transparent text-slate-500' }}">Cancelled</a>
        </div>
    </div>

    <!-- Data Table -->
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Booking ID</th>
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">User</th>
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Tour Package</th>
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Date</th>
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Total Price</th>
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($bookings as $booking)
                    <tr class="hover:bg-slate-50/50 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="font-bold text-primary">#BK-{{ str_pad($booking->id, 4, '0', STR_PAD_LEFT) }}</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center overflow-hidden">
                                    @if($booking->user && $booking->user->photo)
                                    <img alt="User" class="w-full h-full object-cover" src="{{ $booking->user->photo }}"/>
                                    @else
                                    <span class="text-xs font-bold text-slate-500">{{ $booking->user ? strtoupper(substr($booking->user->name, 0, 2)) : 'N/A' }}</span>
                                    @endif
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-sm font-semibold">{{ $booking->user->name ?? 'Unknown' }}</span>
                                    <span class="text-xs text-slate-500">{{ $booking->user->email ?? '' }}</span>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="text-sm font-medium">{{ $booking->tourPackage->title ?? 'Unknown Package' }}</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="text-sm text-slate-600">{{ \Carbon\Carbon::parse($booking->booking_date)->format('M d, Y') }}</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="text-sm font-bold text-slate-900">${{ number_format($booking->total_price, 2) }}</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($booking->payment_status === 'paid')
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-green-100 text-green-700 border border-green-200">
                                Paid
                            </span>
                            @elseif($booking->payment_status === 'pending')
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-700 border border-amber-200">
                                Pending
                            </span>
                            @else
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-600 border border-slate-200">
                                {{ ucfirst($booking->payment_status) }}
                            </span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('admin.bookings.show', $booking->id) }}" class="p-1.5 text-slate-400 hover:text-primary transition-colors" title="View Details">
                                    <span class="material-symbols-outlined text-xl">visibility</span>
                                </a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-6 py-8 text-center text-slate-400">
                            No bookings found
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <!-- Pagination -->
        <div class="px-6 py-4 bg-slate-50 border-t border-slate-200 flex items-center justify-between">
            <span class="text-xs font-medium text-slate-500">Showing {{ $bookings->firstItem() ?? 0 }} to {{ $bookings->lastItem() ?? 0 }} of {{ $bookings->total() }} bookings</span>
            <div class="flex gap-2">
                @if($bookings->previousPageUrl())
                <a href="{{ $bookings->previousPageUrl() }}" class="p-2 border border-slate-200 rounded-lg hover:bg-white transition-colors">
                    <span class="material-symbols-outlined text-sm">chevron_left</span>
                </a>
                @else
                <button class="p-2 border border-slate-200 rounded-lg opacity-50" disabled>
                    <span class="material-symbols-outlined text-sm">chevron_left</span>
                </button>
                @endif

                @foreach($bookings->getUrlRange(1, $bookings->lastPage()) as $page => $url)
                    @if($page == $bookings->currentPage())
                    <button class="px-3 py-1 bg-primary text-white text-xs font-bold rounded-lg">{{ $page }}</button>
                    @else
                    <a href="{{ $url }}" class="px-3 py-1 hover:bg-white text-xs font-bold rounded-lg border border-transparent hover:border-slate-200 transition-all">{{ $page }}</a>
                    @endif
                @endforeach

                @if($bookings->nextPageUrl())
                <a href="{{ $bookings->nextPageUrl() }}" class="p-2 border border-slate-200 rounded-lg hover:bg-white transition-colors">
                    <span class="material-symbols-outlined text-sm">chevron_right</span>
                </a>
                @else
                <button class="p-2 border border-slate-200 rounded-lg opacity-50" disabled>
                    <span class="material-symbols-outlined text-sm">chevron_right</span>
                </button>
                @endif
            </div>
        </div>
    </div>

    <!-- Stats Footer -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mt-8">
        <div class="bg-white p-6 rounded-xl border border-slate-200">
            <p class="text-slate-500 text-xs font-bold uppercase tracking-widest mb-1">Total Revenue</p>
            <h4 class="text-2xl font-black">${{ number_format($totalRevenue, 0) }}</h4>
            <p class="text-green-500 text-xs font-bold flex items-center gap-1 mt-2">
                <span class="material-symbols-outlined text-xs">trending_up</span>
                From paid bookings
            </p>
        </div>
        <div class="bg-white p-6 rounded-xl border border-slate-200">
            <p class="text-slate-500 text-xs font-bold uppercase tracking-widest mb-1">Active Bookings</p>
            <h4 class="text-2xl font-black">{{ $activeBookings }}</h4>
            <p class="text-slate-500 text-xs font-bold flex items-center gap-1 mt-2">
                <span class="material-symbols-outlined text-xs">info</span>
                {{ $activeBookings }} confirmed
            </p>
        </div>
        <div class="bg-white p-6 rounded-xl border border-slate-200">
            <p class="text-slate-500 text-xs font-bold uppercase tracking-widest mb-1">Pending Review</p>
            <h4 class="text-2xl font-black text-amber-500">{{ $pendingCount }}</h4>
            <p class="text-slate-500 text-xs font-bold flex items-center gap-1 mt-2">
                <span class="material-symbols-outlined text-xs">timer</span>
                Awaiting action
            </p>
        </div>
        <div class="bg-white p-6 rounded-xl border border-slate-200">
            <p class="text-slate-500 text-xs font-bold uppercase tracking-widest mb-1">Total Bookings</p>
            <h4 class="text-2xl font-black">{{ $bookings->total() }}</h4>
            <p class="text-slate-500 text-xs font-bold flex items-center gap-1 mt-2">
                <span class="material-symbols-outlined text-xs">calendar_month</span>
                All time
            </p>
        </div>
    </div>
</div>
@endsection

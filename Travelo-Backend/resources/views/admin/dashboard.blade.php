@extends('layouts.admin')

@section('title', 'Dashboard | Travel Admin')

<!-- Header -->
@section('content')
<header class="bg-white/80 backdrop-blur-md sticky top-0 z-20 border-b border-slate-200 px-8 py-4">
    <div class="flex items-center justify-between max-w-7xl mx-auto">
        <div>
            <h2 class="text-2xl font-bold tracking-tight">Dashboard</h2>
            <p class="text-sm text-slate-500">Welcome back! Here's your travel overview.</p>
        </div>
    </div>
</header>

<div class="p-8 max-w-7xl mx-auto space-y-6">
    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Total Destinations -->
        <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200/60 flex flex-col justify-between">
            <div class="flex items-center justify-between mb-4">
                <div class="p-2 bg-blue-50 text-blue-600 rounded-lg">
                    <span class="material-symbols-outlined">map</span>
                </div>
                @if($destinationGrowth > 0)
                <span class="text-xs font-medium text-emerald-600 bg-emerald-50 px-2 py-1 rounded">+{{ $destinationGrowth }}% this month</span>
                @elseif($destinationGrowth < 0)
                <span class="text-xs font-medium text-red-600 bg-red-50 px-2 py-1 rounded">{{ $destinationGrowth }}% this month</span>
                @else
                <span class="text-xs font-medium text-slate-500 bg-slate-50 px-2 py-1 rounded">No change</span>
                @endif
            </div>
            <div>
                <p class="text-slate-500 text-sm font-medium">Total Destinations</p>
                <h3 class="text-3xl font-bold mt-1 text-slate-900">{{ $totalDestinations }}</h3>
            </div>
        </div>

        <!-- Tour Packages -->
        <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200/60 flex flex-col justify-between">
            <div class="flex items-center justify-between mb-4">
                <div class="p-2 bg-purple-50 text-purple-600 rounded-lg">
                    <span class="material-symbols-outlined">inventory_2</span>
                </div>
                @if($tourPackageGrowth > 0)
                <span class="text-xs font-medium text-emerald-600 bg-emerald-50 px-2 py-1 rounded">+{{ $tourPackageGrowth }}% new</span>
                @elseif($tourPackageGrowth < 0)
                <span class="text-xs font-medium text-red-600 bg-red-50 px-2 py-1 rounded">{{ $tourPackageGrowth }}%</span>
                @else
                <span class="text-xs font-medium text-slate-500 bg-slate-50 px-2 py-1 rounded">No change</span>
                @endif
            </div>
            <div>
                <p class="text-slate-500 text-sm font-medium">Tour Packages</p>
                <h3 class="text-3xl font-bold mt-1 text-slate-900">{{ $totalTourPackages }}</h3>
            </div>
        </div>

        <!-- Total Bookings -->
        <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200/60 flex flex-col justify-between">
            <div class="flex items-center justify-between mb-4">
                <div class="p-2 bg-orange-50 text-orange-600 rounded-lg">
                    <span class="material-symbols-outlined">book_online</span>
                </div>
                @if($bookingGrowth > 0)
                <span class="text-xs font-medium text-emerald-600 bg-emerald-50 px-2 py-1 rounded">+{{ $bookingGrowth }}% vs LM</span>
                @elseif($bookingGrowth < 0)
                <span class="text-xs font-medium text-red-600 bg-red-50 px-2 py-1 rounded">{{ $bookingGrowth }}% vs LM</span>
                @else
                <span class="text-xs font-medium text-slate-500 bg-slate-50 px-2 py-1 rounded">No change</span>
                @endif
            </div>
            <div>
                <p class="text-slate-500 text-sm font-medium">Total Bookings</p>
                <h3 class="text-3xl font-bold mt-1 text-slate-900">{{ $totalBookings }}</h3>
            </div>
        </div>

        <!-- Total Revenue -->
        <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200/60 flex flex-col justify-between">
            <div class="flex items-center justify-between mb-4">
                <div class="p-2 bg-emerald-50 text-emerald-600 rounded-lg">
                    <span class="material-symbols-outlined">payments</span>
                </div>
                @if($revenueGrowth >= 100)
                <span class="text-xs font-medium text-emerald-600 bg-emerald-50 px-2 py-1 rounded">Target reached</span>
                @elseif($revenueGrowth > 0)
                <span class="text-xs font-medium text-emerald-600 bg-emerald-50 px-2 py-1 rounded">+{{ $revenueGrowth }}% vs LM</span>
                @elseif($revenueGrowth < 0)
                <span class="text-xs font-medium text-red-600 bg-red-50 px-2 py-1 rounded">{{ $revenueGrowth }}% vs LM</span>
                @else
                <span class="text-xs font-medium text-slate-500 bg-slate-50 px-2 py-1 rounded">No revenue</span>
                @endif
            </div>
            <div>
                <p class="text-slate-500 text-sm font-medium">Total Revenue</p>
                <h3 class="text-3xl font-bold mt-1 text-slate-900">${{ number_format($totalRevenue, 0, ',', ',') }}</h3>
            </div>
        </div>
    </div>

    <!-- Middle Section: Chart and Top Destination -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Revenue Overview -->
        <div class="lg:col-span-2 bg-white p-6 rounded-xl shadow-sm border border-slate-200/60">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-lg font-bold text-slate-900">Revenue Overview</h2>
                <select class="bg-slate-50 border-none rounded-lg text-xs font-medium focus:ring-0">
                    <option>Last 6 months</option>
                    <option>Last year</option>
                </select>
            </div>
            <div class="h-64 relative">
                @php
                $maxRevenue = max($revenueData) > 0 ? max($revenueData) : 15000;
                $points = [];
                foreach($revenueData as $index => $value) {
                    $x = ($index / (count($revenueData) - 1)) * 400;
                    $y = 130 - ($value / $maxRevenue * 110);
                    $points[] = "$x $y";
                }
                $pathD = 'M' . implode(' T', $points);
                $fillPath = $pathD . ' V 150 H 0 Z';
                @endphp
                <svg class="w-full h-full" preserveaspectratio="none" viewbox="0 0 400 150">
                    <defs>
                        <lineargradient id="gradient" x1="0" x2="0" y1="0" y2="1">
                            <stop offset="0%" stop-color="#2563EB" stop-opacity="0.2"></stop>
                            <stop offset="100%" stop-color="#2563EB" stop-opacity="0"></stop>
                        </lineargradient>
                    </defs>
                    <path d="{{ $fillPath }}" fill="url(#gradient)"></path>
                    <path d="{{ $pathD }}" fill="none" stroke="#2563EB" stroke-linecap="round" stroke-width="3"></path>
                </svg>
                <div class="absolute inset-0 flex flex-col justify-between pointer-events-none text-[10px] text-slate-400">
                    <div class="border-b border-slate-100 w-full pb-1">{{ number_format($maxRevenue, 0, ',', ',') }}</div>
                    <div class="border-b border-slate-100 w-full pb-1">{{ number_format($maxRevenue * 0.75, 0, ',', ',') }}</div>
                    <div class="border-b border-slate-100 w-full pb-1">{{ number_format($maxRevenue * 0.5, 0, ',', ',') }}</div>
                    <div class="border-b border-slate-100 w-full pb-1">{{ number_format($maxRevenue * 0.25, 0, ',', ',') }}</div>
                    <div class="pb-1">0</div>
                </div>
            </div>
            <div class="flex justify-between mt-4 px-2">
                @foreach($months as $month)
                <span class="text-xs text-slate-400">{{ $month }}</span>
                @endforeach
            </div>
        </div>

        <!-- Popular Destination -->
        <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200/60">
            <h2 class="text-lg font-bold text-slate-900 mb-6">Popular Destination</h2>

            @if($topDestination)
            <div class="relative rounded-lg overflow-hidden h-40 mb-4">
                <div class="absolute inset-0 bg-black/30 z-10"></div>
                @if($topDestination->image)
                <img class="w-full h-full object-cover" src="{{ asset('storage/' . $topDestination->image) }}" alt="{{ $topDestination->name }}">
                @else
                <div class="w-full h-full bg-slate-200 flex items-center justify-center">
                    <span class="material-symbols-outlined text-4xl text-slate-400">image</span>
                </div>
                @endif
                <div class="absolute bottom-3 left-3 z-20">
                    <p class="text-white font-bold text-lg">{{ $topDestination->name }}</p>
                    <p class="text-white/80 text-xs">{{ $topDestination->total_bookings }} bookings</p>
                </div>
            </div>
            @else
            <div class="relative rounded-lg overflow-hidden h-40 mb-4 bg-slate-100 flex items-center justify-center">
                <span class="material-symbols-outlined text-4xl text-slate-400">map</span>
            </div>
            @endif

            <div class="space-y-4">
                @foreach($popularDestinations->skip(1)->take(3) as $index => $destination)
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div class="w-2 h-2 rounded-full @if($index == 0) bg-blue-500 @elseif($index == 1) bg-emerald-500 @else bg-purple-500 @endif"></div>
                        <span class="text-sm font-medium">{{ $destination->name }}</span>
                    </div>
                    <span class="text-sm text-slate-500">{{ $destination->total_bookings }}</span>
                </div>
                @endforeach

                @if($popularDestinations->count() == 0)
                <div class="text-center text-slate-400 text-sm py-4">
                    No destinations yet
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Recent Bookings Table -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200/60 overflow-hidden">
        <div class="p-6 border-b border-slate-200 flex items-center justify-between">
            <h2 class="text-lg font-bold text-slate-900">Recent Bookings</h2>
            <a href="{{ route('admin.bookings.index') }}" class="text-primary text-sm font-semibold hover:underline">View All</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50/50 text-slate-500 text-xs font-semibold uppercase tracking-wider">
                        <th class="px-6 py-4">User</th>
                        <th class="px-6 py-4">Package</th>
                        <th class="px-6 py-4">Date</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4">Amount</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($recentBookings as $booking)
                    <tr>
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-xs font-bold text-slate-600">
                                    {{ $booking->user ? strtoupper(substr($booking->user->name, 0, 2)) : 'N/A' }}
                                </div>
                                <span class="text-sm font-medium text-slate-900">{{ $booking->user->name ?? 'Unknown' }}</span>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="text-sm text-slate-600">{{ $booking->tourPackage->title ?? 'Unknown Package' }}</span>
                        </td>
                        <td class="px-6 py-4">
                            <span class="text-sm text-slate-600">{{ \Carbon\Carbon::parse($booking->booking_date)->format('M d, Y') }}</span>
                        </td>
                        <td class="px-6 py-4">
                            @if($booking->payment_status === 'paid')
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
                                Paid
                            </span>
                            @elseif($booking->payment_status === 'pending')
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">
                                Pending
                            </span>
                            @else
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                Failed
                            </span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <span class="text-sm font-semibold text-slate-900">${{ number_format($booking->total_price, 0, ',', ',') }}</span>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <a href="{{ route('admin.bookings.show', $booking->id) }}" class="text-slate-400 hover:text-slate-600">
                                <span class="material-symbols-outlined text-xl">visibility</span>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-8 text-center text-slate-400">
                            No bookings yet
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

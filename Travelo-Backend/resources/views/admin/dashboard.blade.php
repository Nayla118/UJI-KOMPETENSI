@extends('layouts.admin')

@section('title', 'Dashboard | Travelo Admin')

@section('content')
<header class="bg-white sticky top-0 z-20 border-b border-slate-200 px-4 sm:px-8 py-4">
    <div class="flex flex-wrap items-center justify-between gap-4 max-w-7xl mx-auto">
        <div>
            <h2 class="text-2xl font-bold tracking-tight">Dashboard</h2>
            <p class="text-sm text-slate-500">
                {{ $report['range']['start'] }} – {{ $report['range']['end'] }} · last 30 days
            </p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.reports.index') }}"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-primary text-white text-sm font-semibold hover:bg-primary-dark transition-colors">
                <span class="material-symbols-outlined text-[18px]">analytics</span>
                Open reports
            </a>
        </div>
    </div>
</header>

<div class="p-4 sm:p-8 max-w-7xl mx-auto space-y-6">
    <!-- Summary cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200/60 flex flex-col justify-between">
            <div class="flex items-center justify-between mb-4">
                <div class="p-2 bg-emerald-50 text-emerald-600 rounded-lg">
                    <span class="material-symbols-outlined">payments</span>
                </div>
                <span class="text-xs font-medium text-slate-500 bg-slate-50 px-2 py-1 rounded">Net revenue</span>
            </div>
            <div>
                <p class="text-slate-500 text-sm font-medium">Revenue</p>
                <h3 class="text-2xl font-bold mt-1 text-slate-900">
                    Rp {{ number_format($report['cards']['revenue_net'], 0, ',', '.') }}
                </h3>
                <p class="text-xs text-slate-500 mt-1">
                    Gross Rp {{ number_format($report['cards']['revenue_gross'], 0, ',', '.') }}
                    @if($report['cards']['revenue_refunds'] > 0)
                        · refunds Rp {{ number_format($report['cards']['revenue_refunds'], 0, ',', '.') }}
                    @endif
                </p>
            </div>
        </div>

        <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200/60 flex flex-col justify-between">
            <div class="flex items-center justify-between mb-4">
                <div class="p-2 bg-orange-50 text-orange-600 rounded-lg">
                    <span class="material-symbols-outlined">book_online</span>
                </div>
                <span class="text-xs font-medium text-slate-500 bg-slate-50 px-2 py-1 rounded">
                    {{ $report['cards']['cancelled'] }} cancelled
                </span>
            </div>
            <div>
                <p class="text-slate-500 text-sm font-medium">Bookings</p>
                <h3 class="text-3xl font-bold mt-1 text-slate-900">{{ number_format($report['cards']['bookings']) }}</h3>
                <p class="text-xs text-slate-500 mt-1">
                    Cancellation rate {{ $report['cards']['cancellation_rate'] }}%
                </p>
            </div>
        </div>

        <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200/60 flex flex-col justify-between">
            <div class="flex items-center justify-between mb-4">
                <div class="p-2 bg-blue-50 text-blue-600 rounded-lg">
                    <span class="material-symbols-outlined">group_add</span>
                </div>
                <span class="text-xs font-medium text-slate-500 bg-slate-50 px-2 py-1 rounded">Travellers</span>
            </div>
            <div>
                <p class="text-slate-500 text-sm font-medium">New users</p>
                <h3 class="text-3xl font-bold mt-1 text-slate-900">{{ number_format($report['cards']['new_users']) }}</h3>
                <p class="text-xs text-slate-500 mt-1">Sign-ups in this period</p>
            </div>
        </div>

        <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200/60 flex flex-col justify-between">
            <div class="flex items-center justify-between mb-4">
                <div class="p-2 bg-amber-50 text-amber-600 rounded-lg">
                    <span class="material-symbols-outlined">star</span>
                </div>
                <span class="text-xs font-medium text-slate-500 bg-slate-50 px-2 py-1 rounded">
                    {{ number_format($report['cards']['review_count']) }} reviews
                </span>
            </div>
            <div>
                <p class="text-slate-500 text-sm font-medium">Average rating</p>
                <h3 class="text-3xl font-bold mt-1 text-slate-900">
                    {{ $report['cards']['review_count'] > 0 ? number_format($report['cards']['average_rating'], 2) : '—' }}
                </h3>
                <p class="text-xs text-slate-500 mt-1">
                    Occupancy {{ $report['cards']['occupancy_rate'] }}% in this period
                </p>
            </div>
        </div>
    </div>

    <!-- Main charts -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-white p-6 rounded-xl shadow-sm border border-slate-200/60">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-bold text-slate-900">Revenue trend</h2>
                <span class="text-xs text-slate-500">Net = gross − refunded cancellations</span>
            </div>
            <div class="h-72">
                <canvas id="chart-revenue" aria-label="Revenue trend chart" role="img"></canvas>
            </div>
        </div>

        <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200/60">
            <h2 class="text-lg font-bold text-slate-900 mb-4">Bookings by status</h2>
            <div class="h-72">
                <canvas id="chart-status" aria-label="Bookings by status chart" role="img"></canvas>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-white p-6 rounded-xl shadow-sm border border-slate-200/60">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-bold text-slate-900">Booking trend</h2>
                <span class="text-xs text-slate-500">All vs paid bookings</span>
            </div>
            <div class="h-64">
                <canvas id="chart-bookings" aria-label="Booking trend chart" role="img"></canvas>
            </div>
        </div>

        <!-- Top packages -->
        <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200/60">
            <h2 class="text-lg font-bold text-slate-900 mb-4">Top packages</h2>

            @forelse($report['top_packages'] as $index => $package)
                <div class="flex items-start justify-between gap-3 py-2 border-b border-slate-100 last:border-0">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-slate-900 truncate">{{ $package['title'] }}</p>
                        <p class="text-xs text-slate-500">{{ $package['destination'] }} · {{ $package['people'] }} travellers</p>
                    </div>
                    <div class="text-right shrink-0">
                        <p class="text-sm font-semibold text-slate-900">{{ $package['bookings'] }} bookings</p>
                        <p class="text-xs text-slate-500">Rp {{ number_format($package['revenue'], 0, ',', '.') }}</p>
                    </div>
                </div>
            @empty
                <p class="text-sm text-slate-400 py-6 text-center">No paid bookings in this period.</p>
            @endforelse
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
                                {{ ucfirst($booking->payment_status) }}
                            </span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <span class="text-sm font-semibold text-slate-900">Rp {{ number_format($booking->total_price, 0, ',', '.') }}</span>
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

@push('scripts')
<script id="report-chart-data" type="application/json">{!! json_encode($chartData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) !!}</script>
@vite('resources/js/reports.js')
@endpush

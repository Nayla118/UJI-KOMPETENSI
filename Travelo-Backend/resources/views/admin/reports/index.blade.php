@extends('layouts.admin')

@section('title', 'Reports | Travelo Admin')

@section('content')
<header class="bg-white sticky top-0 z-20 border-b border-slate-200 px-4 sm:px-8 py-4">
    <div class="flex flex-wrap items-center justify-between gap-4 max-w-7xl mx-auto">
        <div>
            <h2 class="text-2xl font-bold tracking-tight">Reports &amp; Analytics</h2>
            <p class="text-sm text-slate-500">
                {{ $report['range']['start'] }} – {{ $report['range']['end'] }}
                ({{ $report['range']['days'] }} days, grouped by {{ $report['range']['period'] }})
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('admin.reports.export', array_merge(request()->query(), ['format' => 'csv'])) }}"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-primary text-white text-sm font-semibold hover:bg-primary-dark transition-colors">
                <span class="material-symbols-outlined text-[18px]">download</span>
                Export CSV
            </a>
            <a href="{{ route('admin.reports.export', array_merge(request()->query(), ['format' => 'pdf'])) }}"
               target="_blank" rel="noopener"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-slate-200 bg-white text-sm font-semibold text-slate-700 hover:bg-blush transition-colors">
                <span class="material-symbols-outlined text-[18px]">print</span>
                Print / PDF
            </a>
        </div>
    </div>
</header>

<div class="p-4 sm:p-8 max-w-7xl mx-auto space-y-6">
    <!-- Filters -->
    <form method="GET" action="{{ route('admin.reports.index') }}"
          class="bg-white p-5 rounded-xl shadow-sm border border-slate-200/60">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 items-end">
            <label class="block">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Start date</span>
                <input type="date" name="start_date" value="{{ $startDate }}"
                       class="mt-1 w-full rounded-lg border-slate-200 text-sm focus:border-primary focus:ring-primary">
            </label>
            <label class="block">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">End date</span>
                <input type="date" name="end_date" value="{{ $endDate }}"
                       class="mt-1 w-full rounded-lg border-slate-200 text-sm focus:border-primary focus:ring-primary">
            </label>
            <label class="block">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Group by</span>
                <select name="period"
                        class="mt-1 w-full rounded-lg border-slate-200 text-sm focus:border-primary focus:ring-primary">
                    <option value="day" @selected($period === 'day')>Day</option>
                    <option value="week" @selected($period === 'week')>Week</option>
                    <option value="month" @selected($period === 'month')>Month</option>
                </select>
            </label>
            <div class="flex items-center gap-2">
                <button type="submit"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-slate-900 text-white text-sm font-semibold hover:bg-slate-700 transition-colors">
                    <span class="material-symbols-outlined text-[18px]">filter_alt</span>
                    Apply
                </button>
                <a href="{{ route('admin.reports.index') }}"
                   class="inline-flex items-center px-4 py-2.5 rounded-lg border border-slate-200 text-sm font-semibold text-slate-600 hover:bg-slate-50">
                    Reset
                </a>
            </div>
            <p class="text-xs text-slate-500 lg:text-right">
                Max {{ config('travelo.reports.max_range_days') ?? 366 }} days ·
                cached {{ config('travelo.reports.cache_ttl') ?? 300 }}s
            </p>
        </div>
        @if($errors->any())
            <p class="mt-3 text-sm text-red-600">{{ $errors->first() }}</p>
        @endif
    </form>

    <!-- Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200/60">
            <p class="text-slate-500 text-sm font-medium">Net revenue</p>
            <h3 class="text-2xl font-bold mt-1 text-slate-900">
                Rp {{ number_format($report['cards']['revenue_net'], 0, ',', '.') }}
            </h3>
            <p class="text-xs text-slate-500 mt-1">
                Gross Rp {{ number_format($report['cards']['revenue_gross'], 0, ',', '.') }} ·
                refunds Rp {{ number_format($report['cards']['revenue_refunds'], 0, ',', '.') }}
            </p>
        </div>

        <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200/60">
            <p class="text-slate-500 text-sm font-medium">Bookings</p>
            <h3 class="text-2xl font-bold mt-1 text-slate-900">{{ number_format($report['cards']['bookings']) }}</h3>
            <p class="text-xs text-slate-500 mt-1">
                {{ $report['cards']['cancelled'] }} cancelled ({{ $report['cards']['cancellation_rate'] }}%)
            </p>
        </div>

        <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200/60">
            <p class="text-slate-500 text-sm font-medium">Occupancy</p>
            <h3 class="text-2xl font-bold mt-1 text-slate-900">{{ $report['cards']['occupancy_rate'] }}%</h3>
            <p class="text-xs text-slate-500 mt-1">
                {{ number_format($report['cards']['occupancy_booked']) }} of
                {{ number_format($report['cards']['occupancy_capacity']) }} seats filled
            </p>
        </div>

        <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200/60">
            <p class="text-slate-500 text-sm font-medium">New travellers</p>
            <h3 class="text-2xl font-bold mt-1 text-slate-900">{{ number_format($report['cards']['new_users']) }}</h3>
            <p class="text-xs text-slate-500 mt-1">
                Rating {{ $report['cards']['review_count'] > 0 ? number_format($report['cards']['average_rating'], 2) : '—' }}
                from {{ number_format($report['cards']['review_count']) }} reviews
            </p>
        </div>
    </div>

    <!-- Charts -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-white p-6 rounded-xl shadow-sm border border-slate-200/60">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-bold text-slate-900">Revenue</h2>
                <span class="text-xs text-slate-500">Recognised at payment · refunds counted at refund date</span>
            </div>
            <div class="h-72">
                <canvas id="chart-revenue" aria-label="Revenue chart" role="img"></canvas>
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
                <span class="text-xs text-slate-500">All, paid and cancelled</span>
            </div>
            <div class="h-64">
                <canvas id="chart-bookings" aria-label="Booking trend chart" role="img"></canvas>
            </div>
        </div>

        <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200/60">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-bold text-slate-900">Top packages</h2>
                <span class="text-xs text-slate-500">Revenue</span>
            </div>
            <div class="h-64">
                <canvas id="chart-packages" aria-label="Top packages chart" role="img"></canvas>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200/60">
            <h2 class="text-lg font-bold text-slate-900 mb-4">New travellers</h2>
            <div class="h-56">
                <canvas id="chart-users" aria-label="New travellers chart" role="img"></canvas>
            </div>
        </div>

        <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200/60">
            <h2 class="text-lg font-bold text-slate-900 mb-4">Occupancy by package (top 8)</h2>
            <div class="h-56">
                <canvas id="chart-occupancy" aria-label="Occupancy chart" role="img"></canvas>
            </div>
        </div>
    </div>

    <!-- Revenue table -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200/60 overflow-hidden">
        <div class="p-6 border-b border-slate-200">
            <h2 class="text-lg font-bold text-slate-900">Revenue by {{ $report['range']['period'] }}</h2>
            <p class="text-xs text-slate-500 mt-1">
                Paid bookings only. A refund reduces “net” on its own refund date; it is never
                back-dated into the original sale period.
            </p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50/50 text-slate-500 text-xs font-semibold uppercase tracking-wider">
                        <th class="px-6 py-4">Period</th>
                        <th class="px-6 py-4 text-right">Gross</th>
                        <th class="px-6 py-4 text-right">Refunds</th>
                        <th class="px-6 py-4 text-right">Net</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($report['revenue']['series'] as $row)
                        <tr>
                            <td class="px-6 py-3 text-sm text-slate-700">{{ $row['label'] }}</td>
                            <td class="px-6 py-3 text-sm text-right text-slate-700">Rp {{ number_format($row['gross'], 0, ',', '.') }}</td>
                            <td class="px-6 py-3 text-sm text-right text-red-600">Rp {{ number_format($row['refunds'], 0, ',', '.') }}</td>
                            <td class="px-6 py-3 text-sm text-right font-semibold text-slate-900">Rp {{ number_format($row['net'], 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-6 py-8 text-center text-slate-400">No data.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Rankings -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl shadow-sm border border-slate-200/60 overflow-hidden">
            <div class="p-6 border-b border-slate-200">
                <h2 class="text-lg font-bold text-slate-900">Top 5 packages</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50/50 text-slate-500 text-xs font-semibold uppercase tracking-wider">
                            <th class="px-4 py-3">Package</th>
                            <th class="px-4 py-3 text-right">Bookings</th>
                            <th class="px-4 py-3 text-right">Revenue</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($report['top_packages'] as $row)
                            <tr>
                                <td class="px-4 py-3">
                                    <p class="text-sm font-medium text-slate-900">{{ $row['title'] }}</p>
                                    <p class="text-xs text-slate-500">{{ $row['destination'] }} · {{ $row['people'] }} travellers</p>
                                </td>
                                <td class="px-4 py-3 text-sm text-right text-slate-700">{{ $row['bookings'] }}</td>
                                <td class="px-4 py-3 text-sm text-right font-semibold text-slate-900">
                                    Rp {{ number_format($row['revenue'], 0, ',', '.') }}
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-4 py-8 text-center text-slate-400">No paid bookings.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-slate-200/60 overflow-hidden">
            <div class="p-6 border-b border-slate-200">
                <h2 class="text-lg font-bold text-slate-900">Top 5 destinations</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50/50 text-slate-500 text-xs font-semibold uppercase tracking-wider">
                            <th class="px-4 py-3">Destination</th>
                            <th class="px-4 py-3 text-right">Bookings</th>
                            <th class="px-4 py-3 text-right">Revenue</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($report['top_destinations'] as $row)
                            <tr>
                                <td class="px-4 py-3">
                                    <p class="text-sm font-medium text-slate-900">{{ $row['name'] }}</p>
                                    <p class="text-xs text-slate-500">{{ $row['people'] }} travellers</p>
                                </td>
                                <td class="px-4 py-3 text-sm text-right text-slate-700">{{ $row['bookings'] }}</td>
                                <td class="px-4 py-3 text-sm text-right font-semibold text-slate-900">
                                    Rp {{ number_format($row['revenue'], 0, ',', '.') }}
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-4 py-8 text-center text-slate-400">No paid bookings.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Occupancy -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200/60 overflow-hidden">
        <div class="p-6 border-b border-slate-200">
            <h2 class="text-lg font-bold text-slate-900">Occupancy by package</h2>
            <p class="text-xs text-slate-500 mt-1">
                Seats filled vs capacity per visit date. Capacity uses the same rules as the
                availability API (default max people, date overrides, closed dates).
            </p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50/50 text-slate-500 text-xs font-semibold uppercase tracking-wider">
                        <th class="px-6 py-4">Package</th>
                        <th class="px-6 py-4 text-right">Seats filled</th>
                        <th class="px-6 py-4 text-right">Capacity</th>
                        <th class="px-6 py-4 text-right">Occupancy</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($report['occupancy']['packages'] as $row)
                        <tr>
                            <td class="px-6 py-3 text-sm text-slate-700">{{ $row['title'] }}</td>
                            <td class="px-6 py-3 text-sm text-right text-slate-700">{{ number_format($row['booked']) }}</td>
                            <td class="px-6 py-3 text-sm text-right text-slate-700">{{ number_format($row['capacity']) }}</td>
                            <td class="px-6 py-3 text-right">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold {{ $row['occupancy_rate'] >= 70 ? 'bg-emerald-100 text-emerald-800' : ($row['occupancy_rate'] >= 40 ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600') }}">
                                    {{ $row['occupancy_rate'] }}%
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-6 py-8 text-center text-slate-400">No packages.</td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="bg-slate-50/70 font-semibold text-slate-900">
                        <td class="px-6 py-3 text-sm">All packages</td>
                        <td class="px-6 py-3 text-sm text-right">{{ number_format($report['occupancy']['overall_booked']) }}</td>
                        <td class="px-6 py-3 text-sm text-right">{{ number_format($report['occupancy']['overall_capacity']) }}</td>
                        <td class="px-6 py-3 text-sm text-right">{{ $report['occupancy']['overall_rate'] }}%</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- Cancellations / refunds + ratings -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl shadow-sm border border-slate-200/60 overflow-hidden">
            <div class="p-6 border-b border-slate-200">
                <h2 class="text-lg font-bold text-slate-900">Cancellations &amp; refunds</h2>
                <p class="text-xs text-slate-500 mt-1">
                    {{ $report['cancellations']['cancelled'] }} of
                    {{ $report['cancellations']['total_bookings'] }} bookings cancelled
                    ({{ $report['cancellations']['rate'] }}%).
                    Refunds are manual: “refunded” is set only after the money is returned.
                </p>
            </div>
            <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-2">Cancellation requests</h3>
                    @foreach($report['cancellations']['request_status'] as $status => $total)
                        <div class="flex justify-between py-1 text-sm">
                            <span class="text-slate-600">{{ ucfirst($status) }}</span>
                            <span class="font-semibold text-slate-900">{{ $total }}</span>
                        </div>
                    @endforeach
                </div>
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-2">Refund status</h3>
                    @foreach($report['cancellations']['refund_status'] as $status => $total)
                        <div class="flex justify-between py-1 text-sm">
                            <span class="text-slate-600">{{ str_replace('_', ' ', ucfirst($status)) }}</span>
                            <span class="font-semibold text-slate-900">{{ $total }}</span>
                        </div>
                    @endforeach
                    <div class="flex justify-between py-2 mt-2 border-t border-slate-100 text-sm">
                        <span class="text-slate-600">Refunded amount</span>
                        <span class="font-semibold text-slate-900">
                            Rp {{ number_format($report['cancellations']['refunded_amount'], 0, ',', '.') }}
                        </span>
                    </div>
                </div>
            </div>
            @if(! empty($report['cancellations']['reasons']))
                <div class="px-6 pb-6">
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-2">Top cancellation reasons</h3>
                    @foreach($report['cancellations']['reasons'] as $reason)
                        <div class="flex justify-between gap-4 py-1 text-sm border-b border-slate-50 last:border-0">
                            <span class="text-slate-600 line-clamp-2">{{ $reason['reason'] }}</span>
                            <span class="font-semibold text-slate-900 shrink-0">{{ $reason['total'] }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-slate-200/60 overflow-hidden">
            <div class="p-6 border-b border-slate-200">
                <h2 class="text-lg font-bold text-slate-900">Ratings</h2>
                <p class="text-xs text-slate-500 mt-1">
                    Published reviews only.
                    Average {{ $report['ratings']['count'] > 0 ? number_format($report['ratings']['average'], 2) : '—' }}
                    from {{ $report['ratings']['count'] }} reviews.
                </p>
            </div>
            <div class="p-6 space-y-3">
                @foreach(array_reverse($report['ratings']['distribution'], true) as $rating => $total)
                    @php
                        $maxRating = max(1, max($report['ratings']['distribution']));
                        $width = round($total / $maxRating * 100);
                    @endphp
                    <div class="flex items-center gap-3">
                        <span class="w-12 text-sm text-slate-600">{{ $rating }} star</span>
                        <div class="flex-1 h-2.5 rounded-full bg-slate-100 overflow-hidden">
                            <div class="h-full rounded-full bg-amber-400" style="width: {{ $width }}%"></div>
                        </div>
                        <span class="w-8 text-right text-sm font-semibold text-slate-900">{{ $total }}</span>
                    </div>
                @endforeach
                <p class="pt-2 text-xs text-slate-500">
                    Rating distribution for reviews created in the selected period.
                </p>
            </div>
        </div>
    </div>

    <p class="text-xs text-slate-500">
        Generated {{ \Carbon\Carbon::parse($report['generated_at'])->format('d M Y H:i') }} ·
        Data cached for {{ config('travelo.reports.cache_ttl') ?? 300 }} seconds per filter combination.
    </p>
</div>
@endsection

@push('scripts')
<script id="report-chart-data" type="application/json">{!! json_encode($chartData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) !!}</script>
@vite('resources/js/reports.js')
@endpush

@extends('layouts.admin')

@section('title', 'Promo Codes - Travelo Admin')

@section('content')
<header class="bg-white sticky top-0 z-20 border-b border-slate-200 px-8 py-4">
    <div class="flex items-center justify-between max-w-7xl mx-auto">
        <div>
            <h2 class="text-2xl font-bold tracking-tight">Promo Codes</h2>
            <p class="text-sm text-slate-500">Vouchers, quotas and redemption history</p>
        </div>
        <a href="{{ route('admin.promos.create') }}" class="bg-primary hover:bg-primary/90 text-white px-5 py-2.5 rounded-lg font-semibold flex items-center gap-2 transition-all shadow-sm">
            <span class="material-symbols-outlined text-[20px]">add</span>
            Add New
        </a>
    </div>
</header>

<div class="p-8 max-w-7xl mx-auto space-y-6">
    @if (session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 px-6 py-4 rounded-xl">{{ session('success') }}</div>
    @endif

    @if (session('error'))
        <div class="bg-red-50 border border-red-200 text-red-800 px-6 py-4 rounded-xl">{{ session('error') }}</div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Total codes</p>
            <p class="text-2xl font-bold text-slate-800 mt-1">{{ $stats['total'] }}</p>
        </div>
        <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Active codes</p>
            <p class="text-2xl font-bold text-slate-800 mt-1">{{ $stats['active'] }}</p>
        </div>
        <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Active redemptions</p>
            <p class="text-2xl font-bold text-slate-800 mt-1">{{ $stats['redemptions'] }}</p>
        </div>
    </div>

    <div class="flex flex-col md:flex-row gap-4 items-center justify-between">
        <form method="GET" action="{{ route('admin.promos.index') }}" class="relative w-full md:w-96">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">search</span>
            <input name="search" value="{{ $search }}" class="w-full pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none" placeholder="Search code or description..." type="text"/>
        </form>
        <form method="GET" action="{{ route('admin.promos.index') }}" class="w-full md:w-56">
            @if($search !== '')<input type="hidden" name="search" value="{{ $search }}">@endif
            <select name="status" onchange="this.form.submit()" class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none text-slate-600">
                <option value="">All statuses</option>
                <option value="active" @selected($status === 'active')>Active</option>
                <option value="inactive" @selected($status === 'inactive')>Inactive</option>
                <option value="expired" @selected($status === 'expired')>Expired</option>
            </select>
        </form>
    </div>

    <div class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200">
                        <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500">Code</th>
                        <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500">Discount</th>
                        <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500">Validity</th>
                        <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500">Usage</th>
                        <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500">Status</th>
                        <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($promos as $promo)
                        @php($expired = $promo->expires_at !== null && $promo->expires_at->isPast())
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-6 py-4">
                                <a href="{{ route('admin.promos.show', $promo) }}" class="font-mono font-bold text-slate-800 hover:text-primary">{{ $promo->code }}</a>
                                @if($promo->description)
                                    <p class="text-xs text-slate-400 max-w-[220px] truncate">{{ $promo->description }}</p>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-slate-600 text-sm">
                                @if($promo->isPercent())
                                    {{ rtrim(rtrim(number_format((float) $promo->discount_value, 2, '.', ''), '0'), '.') }}%
                                    @if($promo->max_discount !== null)
                                        <span class="text-xs text-slate-400">(max Rp {{ number_format((float) $promo->max_discount, 0, ',', '.') }})</span>
                                    @endif
                                @else
                                    Rp {{ number_format((float) $promo->discount_value, 0, ',', '.') }}
                                @endif
                                @if((float) $promo->min_transaction > 0)
                                    <p class="text-xs text-slate-400">Min Rp {{ number_format((float) $promo->min_transaction, 0, ',', '.') }}</p>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-slate-600 text-sm">
                                <p>{{ $promo->starts_at?->format('d M Y H:i') ?? 'Immediately' }}</p>
                                <p class="text-xs text-slate-400">until {{ $promo->expires_at?->format('d M Y H:i') ?? 'no expiry' }}</p>
                            </td>
                            <td class="px-6 py-4 text-slate-600 text-sm">
                                <span class="font-semibold text-slate-800">{{ $promo->used_count }}</span>
                                / {{ $promo->usage_limit ?? '∞' }}
                                <p class="text-xs text-slate-400">{{ $promo->per_user_limit }}× per user</p>
                            </td>
                            <td class="px-6 py-4">
                                @if(! $promo->is_active)
                                    <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-slate-100 text-slate-600">Inactive</span>
                                @elseif($expired)
                                    <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-red-100 text-red-700">Expired</span>
                                @else
                                    <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-green-100 text-green-700">Active</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('admin.promos.show', $promo) }}" class="p-2 text-slate-400 hover:text-primary transition-colors" title="Usage">
                                        <span class="material-symbols-outlined">visibility</span>
                                    </a>
                                    <a href="{{ route('admin.promos.edit', $promo) }}" class="p-2 text-slate-400 hover:text-primary transition-colors" title="Edit">
                                        <span class="material-symbols-outlined">edit</span>
                                    </a>
                                    <form action="{{ route('admin.promos.toggle', $promo) }}" method="POST" class="inline">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="p-2 text-slate-400 hover:text-amber-600 transition-colors" title="{{ $promo->is_active ? 'Deactivate' : 'Activate' }}">
                                            <span class="material-symbols-outlined">{{ $promo->is_active ? 'pause_circle' : 'play_circle' }}</span>
                                        </button>
                                    </form>
                                    <form action="{{ route('admin.promos.destroy', $promo) }}" method="POST" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="p-2 text-slate-400 hover:text-red-500 transition-colors" onclick="return confirm('Delete this promo code? Existing bookings keep their discount.')" title="Delete">
                                            <span class="material-symbols-outlined">delete</span>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-slate-400">No promo codes found</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-6 py-4 bg-slate-50 border-t border-slate-200 flex items-center justify-between">
            <div class="text-sm text-slate-500">
                Showing <span class="font-semibold">{{ $promos->firstItem() ?? 0 }}</span> to <span class="font-semibold">{{ $promos->lastItem() ?? 0 }}</span> of <span class="font-semibold">{{ $promos->total() }}</span> results
            </div>
            <div class="flex items-center gap-2">
                @if($promos->previousPageUrl())
                    <a href="{{ $promos->previousPageUrl() }}" class="px-3 py-1.5 border border-slate-200 rounded-lg text-sm font-medium hover:bg-white transition-all">Previous</a>
                @else
                    <button class="px-3 py-1.5 border border-slate-200 rounded-lg text-sm font-medium opacity-50 cursor-not-allowed" disabled>Previous</button>
                @endif
                @if($promos->nextPageUrl())
                    <a href="{{ $promos->nextPageUrl() }}" class="px-3 py-1.5 border border-slate-200 rounded-lg text-sm font-medium hover:bg-white transition-all">Next</a>
                @else
                    <button class="px-3 py-1.5 border border-slate-200 rounded-lg text-sm font-medium opacity-50 cursor-not-allowed" disabled>Next</button>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

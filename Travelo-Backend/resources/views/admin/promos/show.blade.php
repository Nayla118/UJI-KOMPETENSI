@extends('layouts.admin')

@section('title', $promo->code.' - Promo Code - Travelo Admin')

@section('content')
<header class="bg-white sticky top-0 z-20 border-b border-slate-200 px-8 py-4">
    <div class="flex items-center justify-between max-w-7xl mx-auto">
        <div>
            <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Promo code</p>
            <h2 class="text-2xl font-bold tracking-tight font-mono">{{ $promo->code }}</h2>
            <p class="text-sm text-slate-500">{{ $promo->description ?: 'No description' }}</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.promos.index') }}" class="text-sm font-semibold text-slate-600 hover:text-primary">Back to promos</a>
            <a href="{{ route('admin.promos.edit', $promo) }}" class="bg-primary hover:bg-primary/90 text-white px-5 py-2.5 rounded-lg font-semibold flex items-center gap-2 transition-all shadow-sm">
                <span class="material-symbols-outlined text-[20px]">edit</span>
                Edit
            </a>
        </div>
    </div>
</header>

<div class="p-8 max-w-7xl mx-auto space-y-6">
    @if (session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 px-6 py-4 rounded-xl">{{ session('success') }}</div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm space-y-4">
            <h3 class="text-lg font-semibold text-slate-800">Discount</h3>
            <div class="flex justify-between text-sm"><span class="text-slate-500">Type</span><span class="font-medium">{{ $promo->isPercent() ? 'Percent' : 'Fixed amount' }}</span></div>
            <div class="flex justify-between text-sm"><span class="text-slate-500">Value</span><span class="font-medium">{{ $promo->isPercent() ? rtrim(rtrim(number_format((float) $promo->discount_value, 2, '.', ''), '0'), '.').'%' : 'Rp '.number_format((float) $promo->discount_value, 0, ',', '.') }}</span></div>
            <div class="flex justify-between text-sm"><span class="text-slate-500">Max discount</span><span class="font-medium">{{ $promo->max_discount !== null ? 'Rp '.number_format((float) $promo->max_discount, 0, ',', '.') : '—' }}</span></div>
            <div class="flex justify-between text-sm"><span class="text-slate-500">Minimum transaction</span><span class="font-medium">Rp {{ number_format((float) $promo->min_transaction, 0, ',', '.') }}</span></div>
        </div>

        <div class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm space-y-4">
            <h3 class="text-lg font-semibold text-slate-800">Usage</h3>
            <div class="flex justify-between text-sm"><span class="text-slate-500">Used</span><span class="font-medium">{{ $promo->used_count }} / {{ $promo->usage_limit ?? '∞' }}</span></div>
            <div class="flex justify-between text-sm"><span class="text-slate-500">Per user</span><span class="font-medium">{{ $promo->per_user_limit }}×</span></div>
            <div class="flex justify-between text-sm"><span class="text-slate-500">Remaining</span><span class="font-medium">{{ $promo->remaining_uses ?? 'Unlimited' }}</span></div>
            <div class="flex justify-between text-sm"><span class="text-slate-500">Status</span>
                <span class="px-2 py-0.5 text-xs font-semibold rounded-full {{ $promo->is_active ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-600' }}">{{ $promo->is_active ? 'Active' : 'Inactive' }}</span>
            </div>
        </div>

        <div class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm space-y-4">
            <h3 class="text-lg font-semibold text-slate-800">Schedule & scope</h3>
            <div class="flex justify-between text-sm"><span class="text-slate-500">Starts</span><span class="font-medium">{{ $promo->starts_at?->format('d M Y H:i') ?? 'Immediately' }}</span></div>
            <div class="flex justify-between text-sm"><span class="text-slate-500">Expires</span><span class="font-medium">{{ $promo->expires_at?->format('d M Y H:i') ?? 'Never' }}</span></div>
            <div class="flex justify-between text-sm"><span class="text-slate-500">Package</span><span class="font-medium">{{ $promo->tourPackage?->title ?? 'All packages' }}</span></div>
            <div class="flex justify-between text-sm"><span class="text-slate-500">Destination</span><span class="font-medium">{{ $promo->destination?->name ?? 'All destinations' }}</span></div>
        </div>
    </div>

    <div class="flex justify-end">
        <form action="{{ route('admin.promos.toggle', $promo) }}" method="POST">
            @csrf
            @method('PATCH')
            <button type="submit" class="px-5 py-2.5 rounded-lg text-sm font-bold border transition-colors {{ $promo->is_active ? 'border-amber-200 text-amber-700 hover:bg-amber-50' : 'border-green-200 text-green-700 hover:bg-green-50' }}">
                {{ $promo->is_active ? 'Deactivate code' : 'Activate code' }}
            </button>
        </form>
    </div>

    <div class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm">
        <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-slate-800">Redemption history</h3>
            <span class="text-xs text-slate-400">Released redemptions no longer consume quota.</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200">
                        <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500">User</th>
                        <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500">Booking</th>
                        <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500">Discount</th>
                        <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500">Status</th>
                        <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500">Used at</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($usage as $redemption)
                        <tr class="hover:bg-slate-50/50">
                            <td class="px-6 py-4">
                                <p class="text-sm font-semibold text-slate-800">{{ $redemption->user?->name ?? 'Deleted user' }}</p>
                                <p class="text-xs text-slate-400">{{ $redemption->user?->email }}</p>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600">
                                @if($redemption->booking)
                                    <a href="{{ route('admin.bookings.show', $redemption->booking) }}" class="text-primary hover:underline">#{{ $redemption->booking_id }}</a>
                                    <p class="text-xs text-slate-400">{{ $redemption->booking->booking_date?->format('d M Y') }} · {{ ucfirst($redemption->booking->booking_status) }}</p>
                                @else
                                    <span class="text-slate-400">Deleted booking</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600">Rp {{ number_format((float) $redemption->discount_amount, 0, ',', '.') }}</td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-0.5 text-xs font-semibold rounded-full {{ $redemption->status === 'applied' ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-600' }}">{{ ucfirst($redemption->status) }}</span>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600">{{ $redemption->created_at?->format('d M Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-6 py-8 text-center text-slate-400">This code has not been used yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($usage->hasPages())
            <div class="px-6 py-4 bg-slate-50 border-t border-slate-200 flex items-center justify-between">
                <div class="text-sm text-slate-500">Showing {{ $usage->firstItem() }} to {{ $usage->lastItem() }} of {{ $usage->total() }} results</div>
                <div class="flex items-center gap-2">
                    @if($usage->previousPageUrl())
                        <a href="{{ $usage->previousPageUrl() }}" class="px-3 py-1.5 border border-slate-200 rounded-lg text-sm font-medium hover:bg-white">Previous</a>
                    @endif
                    @if($usage->nextPageUrl())
                        <a href="{{ $usage->nextPageUrl() }}" class="px-3 py-1.5 border border-slate-200 rounded-lg text-sm font-medium hover:bg-white">Next</a>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>
@endsection

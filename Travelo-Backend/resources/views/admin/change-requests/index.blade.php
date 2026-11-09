@extends('layouts.admin')

@section('title', 'Change Requests - Travelo Admin')

@section('content')
<div class="flex-1 overflow-y-auto">
    <header class="h-16 border-b border-slate-200 bg-white flex items-center justify-between px-8">
        <div class="flex items-center gap-2 text-slate-500">
            <span class="material-symbols-outlined text-slate-400">published_with_changes</span>
            <h1 class="text-lg font-semibold text-slate-900">Cancellation requests</h1>
        </div>
        <p class="text-sm text-slate-500">Approval is the only step that changes a booking.</p>
    </header>

    <div class="p-8">
        <div class="max-w-7xl mx-auto space-y-6">
            @if (session('success'))
                <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <a href="{{ route('admin.change-requests.index', ['status' => 'pending']) }}"
                   class="rounded-2xl border border-amber-200 bg-amber-50 p-5 hover:border-amber-300">
                    <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Pending</p>
                    <p class="mt-2 text-3xl font-bold text-amber-900">{{ $stats['pending'] }}</p>
                </a>
                <a href="{{ route('admin.change-requests.index', ['status' => 'approved']) }}"
                   class="rounded-2xl border border-green-200 bg-green-50 p-5 hover:border-green-300">
                    <p class="text-xs font-semibold uppercase tracking-wide text-green-700">Approved</p>
                    <p class="mt-2 text-3xl font-bold text-green-900">{{ $stats['approved'] }}</p>
                </a>
                <a href="{{ route('admin.change-requests.index', ['status' => 'rejected']) }}"
                   class="rounded-2xl border border-slate-200 bg-white p-5 hover:border-slate-300">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Rejected</p>
                    <p class="mt-2 text-3xl font-bold text-slate-900">{{ $stats['rejected'] }}</p>
                </a>
            </div>

            <form method="GET" action="{{ route('admin.change-requests.index') }}" class="rounded-2xl border border-slate-200 bg-white p-5">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <label class="block text-sm">
                        <span class="mb-1 block font-medium text-slate-700">Search</span>
                        <input type="search" name="search" value="{{ request('search') }}"
                               placeholder="Booking id, traveller name, email"
                               class="w-full rounded-lg border-slate-300 text-sm focus:border-primary focus:ring-primary">
                    </label>
                    <label class="block text-sm">
                        <span class="mb-1 block font-medium text-slate-700">Status</span>
                        <select name="status" class="w-full rounded-lg border-slate-300 text-sm focus:border-primary focus:ring-primary">
                            <option value="">All statuses</option>
                            @foreach (\App\Models\BookingChangeRequest::STATUSES as $option)
                                <option value="{{ $option }}" @selected($status === $option)>{{ ucfirst($option) }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
                <div class="mt-4 flex items-center gap-3">
                    <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary/90">
                        Apply filters
                    </button>
                    <a href="{{ route('admin.change-requests.index') }}" class="text-sm font-medium text-slate-600 hover:text-slate-900">Reset</a>
                </div>
            </form>

            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-5 py-3">Request</th>
                                <th class="px-5 py-3">Booking</th>
                                <th class="px-5 py-3">Dates</th>
                                <th class="px-5 py-3">Traveller reason</th>
                                <th class="px-5 py-3">Decision</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($requests as $changeRequest)
                                <tr class="align-top">
                                    <td class="px-5 py-4">
                                        <div class="flex items-center gap-2">
                                            <span class="rounded-full px-2.5 py-1 text-xs font-semibold
                                                {{ $changeRequest->type === 'cancellation' ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700' }}">
                                                {{ $changeRequest->typeLabel() }}
                                            </span>
                                            <span class="text-xs text-slate-400">#{{ $changeRequest->id }}</span>
                                        </div>
                                        <p class="mt-2 text-xs text-slate-500">{{ $changeRequest->created_at?->format('d M Y H:i') }}</p>
                                        <span class="mt-2 inline-block rounded-full px-2.5 py-1 text-xs font-semibold
                                            @class([
                                                'bg-amber-100 text-amber-800' => $changeRequest->status === 'pending',
                                                'bg-green-100 text-green-800' => $changeRequest->status === 'approved',
                                                'bg-slate-100 text-slate-700' => $changeRequest->status === 'rejected',
                                                'bg-red-100 text-red-700' => $changeRequest->status === 'cancelled',
                                            ])">
                                            {{ ucfirst($changeRequest->status) }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-4">
                                        <a href="{{ route('admin.bookings.show', $changeRequest->booking_id) }}" class="font-semibold text-primary hover:underline">
                                            Booking #{{ $changeRequest->booking_id }}
                                        </a>
                                        <p class="mt-1 text-slate-700">{{ $changeRequest->booking?->tourPackage?->title ?? 'Deleted package' }}</p>
                                        <p class="mt-1 text-xs text-slate-500">
                                            {{ $changeRequest->user?->name ?? $changeRequest->booking?->user?->name ?? 'Deleted user' }}
                                            · {{ $changeRequest->user?->email ?? $changeRequest->booking?->user?->email }}
                                        </p>
                                        <p class="mt-1 text-xs text-slate-500">{{ $changeRequest->booking?->people_count }} traveller(s)</p>
                                    </td>
                                    <td class="px-5 py-4">
                                        <p class="text-xs uppercase tracking-wide text-slate-400">Visit date</p>
                                        <p class="font-medium text-slate-800">{{ $changeRequest->original_booking_date?->format('d M Y') }}</p>
                                        @if ($changeRequest->status === 'approved')
                                            <p class="mt-2 text-xs uppercase tracking-wide text-slate-400">Refund</p>
                                            @php
                                                $refundPct = $changeRequest->refund_percentage ?? null;
                                                $refundAmt = $changeRequest->refund_amount ?? null;
                                            @endphp
                                            <p class="font-medium {{ $changeRequest->refund_status === 'refunded' ? 'text-green-700' : ($changeRequest->refund_status === 'failed' ? 'text-red-700' : 'text-amber-700') }}">
                                                {{ ucfirst(str_replace('_', ' ', $changeRequest->refund_status)) }}
                                            </p>
                                            @if ($refundPct !== null)
                                                <p class="mt-1 text-xs text-slate-500">
                                                    {{ $refundPct }}% · IDR {{ number_format((float) ($refundAmt ?? 0), 0, ',', '.') }}
                                                </p>
                                            @endif
                                            @if ($changeRequest->refund_status === 'failed' && $changeRequest->refund_failure_reason)
                                                <p class="mt-1 text-xs text-red-600">{{ $changeRequest->refund_failure_reason }}</p>
                                            @endif
                                        @endif
                                    </td>
                                    <td class="max-w-sm px-5 py-4">
                                        <p class="whitespace-pre-line text-slate-700">{{ $changeRequest->reason ?: 'No reason given.' }}</p>
                                        @if ($changeRequest->admin_note)
                                            <p class="mt-2 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600">
                                                <span class="font-semibold">Admin note:</span> {{ $changeRequest->admin_note }}
                                            </p>
                                        @endif
                                    </td>
                                    <td class="w-72 px-5 py-4">
                                        @if ($changeRequest->isPending())
                                            <form method="POST" action="{{ route('admin.change-requests.approve', $changeRequest) }}" class="space-y-2">
                                                @csrf
                                                @method('PATCH')
                                                <textarea name="admin_note" rows="2" maxlength="1000"
                                                          placeholder="Optional note for the traveller"
                                                          class="w-full rounded-lg border-slate-300 text-xs focus:border-primary focus:ring-primary"></textarea>
                                                <button type="submit" class="w-full rounded-lg bg-green-600 px-3 py-2 text-xs font-semibold text-white hover:bg-green-700">
                                                    Approve cancellation
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.change-requests.reject', $changeRequest) }}" class="mt-2 space-y-2">
                                                @csrf
                                                @method('PATCH')
                                                <textarea name="admin_note" rows="2" maxlength="1000" required minlength="3"
                                                          placeholder="Reason for rejection (required)"
                                                          class="w-full rounded-lg border-slate-300 text-xs focus:border-primary focus:ring-primary"></textarea>
                                                <button type="submit" class="w-full rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-700 hover:bg-red-100">
                                                    Reject request
                                                </button>
                                            </form>
                                        @elseif ($changeRequest->status === 'approved' && $changeRequest->refund_status !== 'refunded')
                                            @if ($changeRequest->refund_status === 'failed')
                                                <form method="POST" action="{{ route('admin.change-requests.refund-retry', $changeRequest) }}" class="mt-2 space-y-2">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="w-full rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-700 hover:bg-amber-100">
                                                        Retry refund
                                                    </button>
                                                    @if ($changeRequest->refund_failure_reason)
                                                        <p class="mt-1 text-xs text-red-600">{{ $changeRequest->refund_failure_reason }}</p>
                                                    @endif
                                                </form>
                                            @else
                                                <form method="POST" action="{{ route('admin.change-requests.refund', $changeRequest) }}" class="space-y-2">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="text" name="refund_note" maxlength="1000"
                                                           placeholder="Refund reference / note (optional)"
                                                           class="w-full rounded-lg border-slate-300 text-xs focus:border-primary focus:ring-primary">
                                                    <button type="submit" class="w-full rounded-lg bg-primary px-3 py-2 text-xs font-semibold text-white hover:bg-primary/90">
                                                        Mark refund as sent
                                                    </button>
                                                </form>
                                            @endif
                                        @else
                                            <p class="text-xs text-slate-500">
                                                Reviewed {{ $changeRequest->reviewed_at?->format('d M Y H:i') ?? '—' }}
                                                @if ($changeRequest->reviewer)
                                                    by {{ $changeRequest->reviewer->name }}
                                                @endif
                                            </p>
                                            @if ($changeRequest->refunded_at)
                                                <p class="mt-1 text-xs text-green-700">Refunded {{ $changeRequest->refunded_at->format('d M Y') }}</p>
                                            @endif
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-5 py-10 text-center text-sm text-slate-500">
                                        No change requests match these filters.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div>{{ $requests->links() }}</div>
        </div>
    </div>
</div>
@endsection

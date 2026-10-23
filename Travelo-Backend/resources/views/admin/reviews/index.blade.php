@extends('layouts.admin')

@section('title', 'Review Moderation - Travelo Admin')

@section('content')
<div class="flex-1 overflow-y-auto">
    <header class="h-16 border-b border-slate-200 bg-white flex items-center justify-between px-8">
        <div class="flex items-center gap-2 text-slate-500">
            <span class="material-symbols-outlined text-slate-400">reviews</span>
            <h1 class="text-lg font-semibold text-slate-900">Verified reviews</h1>
        </div>
        <p class="text-sm text-slate-500">Only published reviews feed public ratings.</p>
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
                <div class="rounded-2xl border border-slate-200 bg-white p-5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">All reviews</p>
                    <p class="mt-2 text-3xl font-bold text-slate-900">{{ $stats['total'] }}</p>
                </div>
                <div class="rounded-2xl border border-green-200 bg-green-50 p-5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-green-700">Published</p>
                    <p class="mt-2 text-3xl font-bold text-green-800">{{ $stats['published'] }}</p>
                </div>
                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Hidden</p>
                    <p class="mt-2 text-3xl font-bold text-amber-800">{{ $stats['hidden'] }}</p>
                </div>
            </div>

            <form method="GET" action="{{ route('admin.reviews.index') }}" class="rounded-2xl border border-slate-200 bg-white p-5">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3 xl:grid-cols-6">
                    <label class="block text-sm">
                        <span class="mb-1 block font-medium text-slate-700">Search</span>
                        <input type="search" name="search" value="{{ request('search') }}"
                               placeholder="Comment, reviewer, booking id"
                               class="w-full rounded-lg border-slate-300 text-sm focus:border-primary focus:ring-primary">
                    </label>
                    <label class="block text-sm">
                        <span class="mb-1 block font-medium text-slate-700">Status</span>
                        <select name="status" class="w-full rounded-lg border-slate-300 text-sm focus:border-primary focus:ring-primary">
                            <option value="">All statuses</option>
                            <option value="published" @selected($status === 'published')>Published</option>
                            <option value="hidden" @selected($status === 'hidden')>Hidden</option>
                        </select>
                    </label>
                    <label class="block text-sm">
                        <span class="mb-1 block font-medium text-slate-700">Destination</span>
                        <select name="destination_id" class="w-full rounded-lg border-slate-300 text-sm focus:border-primary focus:ring-primary">
                            <option value="">All destinations</option>
                            @foreach ($destinations as $destination)
                                <option value="{{ $destination->id }}" @selected($destinationId === $destination->id)>
                                    {{ $destination->name }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-sm">
                        <span class="mb-1 block font-medium text-slate-700">Rating</span>
                        <select name="rating" class="w-full rounded-lg border-slate-300 text-sm focus:border-primary focus:ring-primary">
                            <option value="">All ratings</option>
                            @for ($i = 5; $i >= 1; $i--)
                                <option value="{{ $i }}" @selected($rating === $i)>{{ $i }} star{{ $i === 1 ? '' : 's' }}</option>
                            @endfor
                        </select>
                    </label>
                    <label class="block text-sm">
                        <span class="mb-1 block font-medium text-slate-700">From</span>
                        <input type="date" name="from" value="{{ request('from') }}"
                               class="w-full rounded-lg border-slate-300 text-sm focus:border-primary focus:ring-primary">
                    </label>
                    <label class="block text-sm">
                        <span class="mb-1 block font-medium text-slate-700">To</span>
                        <input type="date" name="to" value="{{ request('to') }}"
                               class="w-full rounded-lg border-slate-300 text-sm focus:border-primary focus:ring-primary">
                    </label>
                </div>
                <div class="mt-4 flex items-center gap-3">
                    <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary/90">
                        Apply filters
                    </button>
                    <a href="{{ route('admin.reviews.index') }}" class="text-sm font-medium text-slate-600 hover:text-slate-900">
                        Reset
                    </a>
                </div>
            </form>

            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-5 py-3">Review</th>
                                <th class="px-5 py-3">Destination</th>
                                <th class="px-5 py-3">Visit</th>
                                <th class="px-5 py-3">Moderation</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($reviews as $review)
                                <tr class="align-top">
                                    <td class="max-w-md px-5 py-4">
                                        <div class="flex items-center gap-2">
                                            <span class="font-semibold text-slate-900">{{ $review->user?->name ?? 'Deleted user' }}</span>
                                            <span class="text-xs text-slate-400">{{ $review->user?->email }}</span>
                                        </div>
                                        <div class="mt-1 flex items-center gap-1 text-amber-500" aria-label="{{ $review->rating }} out of 5 stars">
                                            @for ($i = 1; $i <= 5; $i++)
                                                <span class="material-symbols-outlined text-base {{ $i <= $review->rating ? 'fill' : 'text-slate-300' }}">star</span>
                                            @endfor
                                            <span class="ml-1 text-xs font-semibold text-slate-600">{{ $review->rating }}/5</span>
                                        </div>
                                        <p class="mt-2 whitespace-pre-line text-slate-700">{{ $review->comment ?: 'No written comment.' }}</p>
                                        <p class="mt-2 text-xs text-slate-400">Review #{{ $review->id }}{{ $review->updated_at?->ne($review->created_at) ? ' · edited' : '' }}</p>
                                    </td>
                                    <td class="px-5 py-4">
                                        @if ($review->destination)
                                            <a href="{{ route('admin.destinations.show', $review->destination) }}" class="font-medium text-primary hover:underline">
                                                {{ $review->destination->name }}
                                            </a>
                                            <p class="mt-1 text-xs text-slate-500">{{ $review->destination->city }}, {{ $review->destination->country }}</p>
                                        @else
                                            <span class="text-slate-400">Deleted destination</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4">
                                        @if ($review->booking)
                                            <a href="{{ route('admin.bookings.show', $review->booking) }}" class="font-medium text-slate-900 hover:text-primary">
                                                Booking #{{ $review->booking->id }}
                                            </a>
                                            <p class="mt-1 text-xs text-slate-500">
                                                {{ $review->booking->booking_date?->format('M d, Y') ?? 'Date unavailable' }}
                                            </p>
                                            @if ($review->booking->ticket_status === \App\Models\Booking::TICKET_USED && $review->booking->ticket_used_at)
                                                <span class="mt-2 inline-flex rounded-full bg-green-50 px-2 py-0.5 text-xs font-semibold text-green-700">
                                                    Ticket checked in
                                                </span>
                                            @else
                                                <span class="mt-2 inline-flex rounded-full bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-700">
                                                    Check-in not recorded
                                                </span>
                                            @endif
                                        @else
                                            <span class="text-slate-400">Booking removed</span>
                                        @endif
                                        <p class="mt-2 text-xs text-slate-400">Submitted {{ $review->created_at?->format('M d, Y H:i') }}</p>
                                    </td>
                                    <td class="w-80 px-5 py-4">
                                        @if ($review->status === \App\Models\Review::STATUS_PUBLISHED)
                                            <span class="inline-flex rounded-full bg-green-50 px-2.5 py-1 text-xs font-semibold text-green-700">Published</span>
                                        @else
                                            <span class="inline-flex rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700">Hidden</span>
                                        @endif

                                        @if ($review->moderation_reason)
                                            <p class="mt-2 text-xs text-slate-500">
                                                Reason: {{ $review->moderation_reason }}
                                            </p>
                                            <p class="mt-1 text-xs text-slate-400">
                                                By {{ $review->moderator?->name ?? 'admin' }}
                                                @if ($review->moderated_at)
                                                    · {{ $review->moderated_at->format('M d, Y H:i') }}
                                                @endif
                                            </p>
                                        @endif

                                        <form method="POST" action="{{ route('admin.reviews.moderate', $review) }}" class="mt-3 space-y-2">
                                            @csrf
                                            @method('PATCH')
                                            <textarea name="reason" rows="2" required minlength="3" maxlength="500"
                                                      placeholder="Reason for this moderation decision"
                                                      class="w-full rounded-lg border-slate-300 text-xs focus:border-primary focus:ring-primary">{{ old('reason') }}</textarea>
                                            @if ($review->status === \App\Models\Review::STATUS_PUBLISHED)
                                                <button type="submit" name="action" value="hide"
                                                        class="w-full rounded-lg bg-amber-500 px-3 py-2 text-xs font-semibold text-white hover:bg-amber-600"
                                                        onclick="return confirm('Hide this review from public ratings?')">
                                                    Hide review
                                                </button>
                                            @else
                                                <button type="submit" name="action" value="restore"
                                                        class="w-full rounded-lg bg-green-600 px-3 py-2 text-xs font-semibold text-white hover:bg-green-700"
                                                        onclick="return confirm('Restore this review to public ratings?')">
                                                    Restore review
                                                </button>
                                            @endif
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-5 py-12 text-center text-slate-500">
                                        No reviews match these filters yet.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{ $reviews->links() }}
        </div>
    </div>
</div>
@endsection

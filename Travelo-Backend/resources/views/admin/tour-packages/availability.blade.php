@extends('layouts.admin')

@section('title', 'Availability - ' . $tourPackage->title)

@section('content')
@php
    $monthStart = \Carbon\Carbon::createFromFormat('!Y-m-d', $month . '-01');
    $prevMonth = $monthStart->copy()->subMonth()->format('Y-m');
    $nextMonth = $monthStart->copy()->addMonth()->format('Y-m');
    $formDate = old('date', $selectedDate);
    $editing = $tourPackage->dateAvailabilities->first(
        fn ($row) => $row->date?->toDateString() === $formDate
    );
@endphp

<div class="flex-1 overflow-y-auto">
    <header class="h-16 border-b border-slate-200 bg-white flex items-center justify-between px-8">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.tour-packages.show', $tourPackage) }}" class="inline-flex items-center justify-center size-9 rounded-xl text-slate-500 hover:bg-blush" aria-label="Back to tour package">
                <span class="material-symbols-outlined">arrow_back</span>
            </a>
            <div>
                <h1 class="text-lg font-semibold text-slate-900">{{ $tourPackage->title }}</h1>
                <p class="text-xs text-slate-500">{{ $tourPackage->destination?->name ?? 'Tour package' }} · default capacity {{ $defaultCapacity }} seats/day</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.tour-packages.availability', ['tourPackage' => $tourPackage, 'month' => $prevMonth]) }}"
               class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">Previous</a>
            <span class="min-w-[7rem] text-center text-sm font-semibold text-slate-800">{{ $monthStart->format('F Y') }}</span>
            <a href="{{ route('admin.tour-packages.availability', ['tourPackage' => $tourPackage, 'month' => $nextMonth]) }}"
               class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">Next</a>
        </div>
    </header>

    <div class="p-8">
        <div class="mx-auto max-w-7xl space-y-6">
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

            <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
                <div class="xl:col-span-2 overflow-hidden rounded-2xl border border-slate-200 bg-white">
                    <div class="border-b border-slate-100 px-5 py-4">
                        <h2 class="font-semibold text-slate-900">Daily capacity</h2>
                        <p class="mt-1 text-sm text-slate-500">
                            Seats held by pending, confirmed, and completed bookings. Failed, expired, and cancelled bookings release their seats automatically.
                        </p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-5 py-3">Date</th>
                                    <th class="px-5 py-3">Capacity</th>
                                    <th class="px-5 py-3">Booked</th>
                                    <th class="px-5 py-3">Remaining</th>
                                    <th class="px-5 py-3">Status</th>
                                    <th class="px-5 py-3">Override</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($days as $day)
                                    @php
                                        $date = \Carbon\Carbon::parse($day['date']);
                                        $isPast = $date->lt(now()->startOfDay());
                                    @endphp
                                    <tr class="{{ $isPast ? 'opacity-60' : '' }}">
                                        <td class="whitespace-nowrap px-5 py-3">
                                            <p class="font-medium text-slate-900">{{ $date->format('d M Y') }}</p>
                                            <p class="text-xs text-slate-500">{{ $date->format('l') }}</p>
                                        </td>
                                        <td class="px-5 py-3 text-slate-700">{{ $day['capacity'] }}</td>
                                        <td class="px-5 py-3 text-slate-700">{{ $day['booked'] }}</td>
                                        <td class="px-5 py-3 font-semibold {{ $day['remaining'] === 0 ? 'text-red-600' : 'text-green-700' }}">
                                            {{ $day['remaining'] }}
                                        </td>
                                        <td class="px-5 py-3">
                                            @if ($day['is_closed'])
                                                <span class="rounded-full bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-700">Closed</span>
                                            @elseif ($day['remaining'] === 0)
                                                <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800">Full</span>
                                            @else
                                                <span class="rounded-full bg-green-100 px-2.5 py-1 text-xs font-semibold text-green-800">Open</span>
                                            @endif
                                            @if (!empty($day['note']))
                                                <p class="mt-1 text-xs text-slate-500">{{ $day['note'] }}</p>
                                            @endif
                                        </td>
                                        <td class="px-5 py-3">
                                            @if ($day['has_override'])
                                                @php
                                                    $override = $tourPackage->dateAvailabilities->first(
                                                        fn ($row) => $row->date?->toDateString() === $day['date']
                                                    );
                                                @endphp
                                                <a href="{{ route('admin.tour-packages.availability', ['tourPackage' => $tourPackage, 'month' => $month, 'date' => $day['date']]) }}#date-override"
                                                   class="text-xs font-semibold text-primary hover:underline">Edit</a>
                                                @if ($override)
                                                    <form method="POST"
                                                          action="{{ route('admin.tour-packages.availability.destroy', ['tourPackage' => $tourPackage, 'availability' => $override->id]) }}"
                                                          class="inline"
                                                          onsubmit="return confirm('Remove this date override and use the package default?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="ml-2 text-xs font-semibold text-red-600 hover:underline">Remove</button>
                                                    </form>
                                                @endif
                                            @else
                                                <span class="text-xs text-slate-400">Default</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="space-y-6">
                    <form id="date-override" method="POST" action="{{ route('admin.tour-packages.availability.store', $tourPackage) }}"
                          class="rounded-2xl border border-slate-200 bg-white p-5 space-y-4">
                        @csrf
                        <div>
                            <h2 class="font-semibold text-slate-900">{{ $editing ? 'Edit date override' : 'Set a date override' }}</h2>
                            <p class="mt-1 text-sm text-slate-500">Lower/raise the capacity for one date, or close it completely.</p>
                        </div>

                        <label class="block text-sm">
                            <span class="mb-1 block font-medium text-slate-700">Date</span>
                            <input type="date" name="date" required min="{{ now()->toDateString() }}"
                                   value="{{ $formDate }}"
                                   class="w-full rounded-lg border-slate-300 text-sm focus:border-primary focus:ring-primary">
                        </label>

                        <label class="block text-sm">
                            <span class="mb-1 block font-medium text-slate-700">Capacity</span>
                            <input type="number" name="capacity" min="1" step="1"
                                   value="{{ old('capacity', $editing?->capacity ?? $defaultCapacity) }}"
                                   placeholder="{{ $defaultCapacity }}"
                                   class="w-full rounded-lg border-slate-300 text-sm focus:border-primary focus:ring-primary">
                            <span class="mt-1 block text-xs text-slate-500">Leave empty to use the package default ({{ $defaultCapacity }}).</span>
                        </label>

                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" name="is_closed" value="1" @checked(old('is_closed', $editing?->is_closed))
                                   class="rounded border-slate-300 text-primary focus:ring-primary">
                            Close this date for bookings
                        </label>

                        <label class="block text-sm">
                            <span class="mb-1 block font-medium text-slate-700">Note (optional)</span>
                            <input type="text" name="note" maxlength="255" value="{{ old('note', $editing?->note) }}"
                                   placeholder="Maintenance, private event, etc."
                                   class="w-full rounded-lg border-slate-300 text-sm focus:border-primary focus:ring-primary">
                        </label>

                        <button type="submit" class="w-full rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary/90">
                            Save date override
                        </button>
                    </form>

                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5 text-sm text-slate-600">
                        <p class="font-semibold text-slate-800">How capacity works</p>
                        <ul class="mt-2 list-disc space-y-1 pl-5">
                            <li>No override: the package default applies.</li>
                            <li>Seats are validated again inside the booking transaction, so two simultaneous bookings cannot overbook a date.</li>
                            <li>Cancelled, failed, and expired bookings release their seats.</li>
                            <li>Approved cancellations release the booking's seats immediately.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

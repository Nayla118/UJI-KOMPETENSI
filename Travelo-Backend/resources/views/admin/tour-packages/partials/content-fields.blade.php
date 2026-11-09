@php
    $inclusionsValue = old('inclusions', $tourPackage ? implode("\n", (array) ($tourPackage->inclusions ?? [])) : '');
    if (is_array($inclusionsValue)) {
        $inclusionsValue = implode("\n", $inclusionsValue);
    }
    $exclusionsValue = old('exclusions', $tourPackage ? implode("\n", (array) ($tourPackage->exclusions ?? [])) : '');
    if (is_array($exclusionsValue)) {
        $exclusionsValue = implode("\n", $exclusionsValue);
    }

    $initialItinerary = $tourPackage
        ? $tourPackage->itineraryDays->map(fn ($day) => [
            'day_number' => $day->day_number,
            'title' => $day->title,
            'description' => $day->description,
        ])->all()
        : [];
    if (count($initialItinerary) === 0) {
        $initialItinerary = [['day_number' => 1, 'title' => '', 'description' => '']];
    }
    $itineraryRows = old('itinerary', $initialItinerary);
@endphp

<!-- Left Column: Form Details -->
<div class="lg:col-span-2 space-y-6">
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
        <h3 class="text-lg font-semibold mb-6 flex items-center gap-2">
            <span class="material-symbols-outlined text-primary">info</span>
            Package Information
        </h3>
        <div class="space-y-5">
            <div class="flex flex-col gap-2">
                <label class="text-sm font-medium text-slate-700">Tour Title</label>
                <input name="title" value="{{ old('title', $tourPackage?->title) }}" class="w-full rounded-lg border-slate-200 focus:ring-primary focus:border-primary px-4 py-3" placeholder="e.g. Luxury Safari in Kenya" type="text" required/>
                @error('title')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-slate-700">Destination</label>
                    <select name="destination_id" class="w-full rounded-lg border-slate-200 focus:ring-primary focus:border-primary px-4 py-3" required>
                        <option value="">Select location</option>
                        @foreach($destinations as $destination)
                        <option value="{{ $destination->id }}" @selected(old('destination_id', $tourPackage?->destination_id) == $destination->id)>{{ $destination->city }}, {{ $destination->country }}</option>
                        @endforeach
                    </select>
                    @error('destination_id')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-slate-700">Price per Person (Rp)</label>
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">Rp</span>
                        <input name="price" value="{{ old('price', $tourPackage?->price) }}" class="w-full pl-12 pr-4 py-3 rounded-lg border-slate-200 focus:ring-primary focus:border-primary" placeholder="0" type="number" step="1" required/>
                    </div>
                    @error('price')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-slate-700">Duration (Days)</label>
                    <input name="duration_days" value="{{ old('duration_days', $tourPackage?->duration_days) }}" class="w-full rounded-lg border-slate-200 focus:ring-primary focus:border-primary px-4 py-3" placeholder="7" type="number" min="1" required/>
                    @error('duration_days')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-slate-700">Max Group Size</label>
                    <input name="max_people" value="{{ old('max_people', $tourPackage?->max_people) }}" class="w-full rounded-lg border-slate-200 focus:ring-primary focus:border-primary px-4 py-3" placeholder="12" type="number" min="1" required/>
                    @error('max_people')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>
            <div class="flex flex-col gap-2">
                <label class="text-sm font-medium text-slate-700">Description</label>
                <textarea name="description" class="w-full rounded-lg border-slate-200 focus:ring-primary focus:border-primary px-4 py-3" placeholder="Describe the tour highlights..." rows="5">{{ old('description', $tourPackage?->description) }}</textarea>
                @error('description')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
        </div>
    </div>

    <!-- Itinerary -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold flex items-center gap-2">
                <span class="material-symbols-outlined text-primary">route</span>
                Itinerary
            </h3>
            <button type="button" onclick="addItineraryRow()" class="inline-flex items-center gap-1 px-3 py-2 text-sm font-medium text-primary bg-primary/10 rounded-lg hover:bg-primary/20">
                <span class="material-symbols-outlined text-base">add</span>
                Add day
            </button>
        </div>
        <p class="text-sm text-slate-500 mb-4">Add one row per day. Use the arrows to reorder; the display order is saved.</p>

        <div id="itinerary-rows" class="space-y-4">
            @foreach($itineraryRows as $index => $row)
            <div class="itinerary-row rounded-xl border border-slate-200 p-4 bg-slate-50/50" data-itinerary-row>
                <div class="grid grid-cols-1 md:grid-cols-[90px_1fr_auto] gap-3 items-start">
                    <div>
                        <label class="text-xs font-semibold text-slate-500 uppercase">Day</label>
                        <input type="number" name="itinerary[{{ $index }}][day_number]" value="{{ $row['day_number'] ?? $index + 1 }}" min="1" class="mt-1 w-full rounded-lg border-slate-200 focus:ring-primary focus:border-primary px-3 py-2">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-slate-500 uppercase">Title</label>
                        <input type="text" name="itinerary[{{ $index }}][title]" value="{{ $row['title'] ?? '' }}" class="mt-1 w-full rounded-lg border-slate-200 focus:ring-primary focus:border-primary px-3 py-2" placeholder="e.g. Arrival & city tour">
                    </div>
                    <div class="flex items-center gap-1 pt-5">
                        <button type="button" onclick="moveItineraryRow(this, -1)" class="p-2 text-slate-500 hover:bg-slate-200 rounded" title="Move up">
                            <span class="material-symbols-outlined text-lg">arrow_upward</span>
                        </button>
                        <button type="button" onclick="moveItineraryRow(this, 1)" class="p-2 text-slate-500 hover:bg-slate-200 rounded" title="Move down">
                            <span class="material-symbols-outlined text-lg">arrow_downward</span>
                        </button>
                        <button type="button" onclick="removeItineraryRow(this)" class="p-2 text-red-600 hover:bg-red-50 rounded" title="Remove">
                            <span class="material-symbols-outlined text-lg">delete</span>
                        </button>
                    </div>
                </div>
                <div class="mt-3">
                    <label class="text-xs font-semibold text-slate-500 uppercase">Description</label>
                    <textarea name="itinerary[{{ $index }}][description]" rows="3" class="mt-1 w-full rounded-lg border-slate-200 focus:ring-primary focus:border-primary px-3 py-2" placeholder="What happens on this day?">{{ $row['description'] ?? '' }}</textarea>
                </div>
            </div>
            @endforeach
        </div>
        @error('itinerary.*.title')<p class="text-sm text-red-600 mt-2">{{ $message }}</p>@enderror
        @error('itinerary.*.day_number')<p class="text-sm text-red-600 mt-2">{{ $message }}</p>@enderror
    </div>

    <!-- Inclusions & exclusions -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
        <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
            <span class="material-symbols-outlined text-primary">checklist</span>
            Inclusions & Exclusions
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="flex flex-col gap-2">
                <label class="text-sm font-medium text-slate-700">What's included</label>
                <textarea name="inclusions" rows="6" class="w-full rounded-lg border-slate-200 focus:ring-primary focus:border-primary px-4 py-3" placeholder="Hotel accommodation&#10;Licensed guide&#10;Entrance tickets">{{ $inclusionsValue }}</textarea>
                <p class="text-xs text-slate-500">One item per line.</p>
            </div>
            <div class="flex flex-col gap-2">
                <label class="text-sm font-medium text-slate-700">What's not included</label>
                <textarea name="exclusions" rows="6" class="w-full rounded-lg border-slate-200 focus:ring-primary focus:border-primary px-4 py-3" placeholder="Personal expenses&#10;Travel insurance&#10;Optional activities">{{ $exclusionsValue }}</textarea>
                <p class="text-xs text-slate-500">One item per line.</p>
            </div>
        </div>
    </div>

    <!-- Meeting point & terms -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
        <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
            <span class="material-symbols-outlined text-primary">pin_drop</span>
            Meeting Point & Terms
        </h3>
        <div class="space-y-5">
            <div class="flex flex-col gap-2">
                <label class="text-sm font-medium text-slate-700">Meeting point</label>
                <input name="meeting_point" value="{{ old('meeting_point', $tourPackage?->meeting_point) }}" class="w-full rounded-lg border-slate-200 focus:ring-primary focus:border-primary px-4 py-3" placeholder="e.g. Juanda International Airport, Terminal 1" type="text"/>
                @error('meeting_point')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-slate-700">Latitude</label>
                    <input name="meeting_latitude" value="{{ old('meeting_latitude', $tourPackage?->meeting_latitude) }}" class="w-full rounded-lg border-slate-200 focus:ring-primary focus:border-primary px-4 py-3" placeholder="-7.3795" type="number" step="any"/>
                    @error('meeting_latitude')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-slate-700">Longitude</label>
                    <input name="meeting_longitude" value="{{ old('meeting_longitude', $tourPackage?->meeting_longitude) }}" class="w-full rounded-lg border-slate-200 focus:ring-primary focus:border-primary px-4 py-3" placeholder="112.7870" type="number" step="any"/>
                    @error('meeting_longitude')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>
            <div class="flex flex-col gap-2">
                <label class="text-sm font-medium text-slate-700">Terms & conditions</label>
                <textarea name="terms" rows="6" class="w-full rounded-lg border-slate-200 focus:ring-primary focus:border-primary px-4 py-3" placeholder="Cancellation policy, what to bring, important notes...">{{ old('terms', $tourPackage?->terms) }}</textarea>
                @error('terms')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
        </div>
    </div>
</div>

<template id="itinerary-template">
    <div class="itinerary-row rounded-xl border border-slate-200 p-4 bg-slate-50/50" data-itinerary-row>
        <div class="grid grid-cols-1 md:grid-cols-[90px_1fr_auto] gap-3 items-start">
            <div>
                <label class="text-xs font-semibold text-slate-500 uppercase">Day</label>
                <input type="number" name="itinerary[__INDEX__][day_number]" value="__DAY__" min="1" class="mt-1 w-full rounded-lg border-slate-200 focus:ring-primary focus:border-primary px-3 py-2">
            </div>
            <div>
                <label class="text-xs font-semibold text-slate-500 uppercase">Title</label>
                <input type="text" name="itinerary[__INDEX__][title]" class="mt-1 w-full rounded-lg border-slate-200 focus:ring-primary focus:border-primary px-3 py-2" placeholder="e.g. Arrival & city tour">
            </div>
            <div class="flex items-center gap-1 pt-5">
                <button type="button" onclick="moveItineraryRow(this, -1)" class="p-2 text-slate-500 hover:bg-slate-200 rounded" title="Move up">
                    <span class="material-symbols-outlined text-lg">arrow_upward</span>
                </button>
                <button type="button" onclick="moveItineraryRow(this, 1)" class="p-2 text-slate-500 hover:bg-slate-200 rounded" title="Move down">
                    <span class="material-symbols-outlined text-lg">arrow_downward</span>
                </button>
                <button type="button" onclick="removeItineraryRow(this)" class="p-2 text-red-600 hover:bg-red-50 rounded" title="Remove">
                    <span class="material-symbols-outlined text-lg">delete</span>
                </button>
            </div>
        </div>
        <div class="mt-3">
            <label class="text-xs font-semibold text-slate-500 uppercase">Description</label>
            <textarea name="itinerary[__INDEX__][description]" rows="3" class="mt-1 w-full rounded-lg border-slate-200 focus:ring-primary focus:border-primary px-3 py-2" placeholder="What happens on this day?"></textarea>
        </div>
    </div>
</template>

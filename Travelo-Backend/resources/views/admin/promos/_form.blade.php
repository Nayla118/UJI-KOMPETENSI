@php($editing = isset($promo) && $promo !== null)

<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
    <div class="p-8 space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="col-span-2">
                <h3 class="text-lg font-bold flex items-center gap-2 mb-1">
                    <span class="material-symbols-outlined text-primary">sell</span>
                    Promo details
                </h3>
                <p class="text-sm text-slate-500">Codes are stored uppercase and validated entirely on the server.</p>
            </div>

            <div class="flex flex-col gap-2">
                <label class="text-sm font-semibold text-slate-700">Code</label>
                <input name="code" value="{{ old('code', $promo->code ?? '') }}" class="w-full rounded-lg border-slate-200 focus:border-primary focus:ring-primary text-sm p-2.5 uppercase" placeholder="e.g. SUMMER10" type="text" required>
                @error('code')<p class="text-red-500 text-xs">{{ $message }}</p>@enderror
            </div>

            <div class="flex flex-col gap-2">
                <label class="text-sm font-semibold text-slate-700">Description</label>
                <input name="description" value="{{ old('description', $promo->description ?? '') }}" class="w-full rounded-lg border-slate-200 focus:border-primary focus:ring-primary text-sm p-2.5" placeholder="Shown on the admin usage page" type="text">
                @error('description')<p class="text-red-500 text-xs">{{ $message }}</p>@enderror
            </div>

            <div class="flex flex-col gap-2">
                <label class="text-sm font-semibold text-slate-700">Discount type</label>
                <select name="discount_type" class="w-full rounded-lg border-slate-200 focus:border-primary focus:ring-primary text-sm p-2.5" required>
                    <option value="percent" @selected(old('discount_type', $promo->discount_type ?? 'percent') === 'percent')>Percent (%)</option>
                    <option value="fixed" @selected(old('discount_type', $promo->discount_type ?? 'percent') === 'fixed')>Fixed amount (Rp)</option>
                </select>
                @error('discount_type')<p class="text-red-500 text-xs">{{ $message }}</p>@enderror
            </div>

            <div class="flex flex-col gap-2">
                <label class="text-sm font-semibold text-slate-700">Discount value</label>
                <input name="discount_value" value="{{ old('discount_value', $promo->discount_value ?? '') }}" class="w-full rounded-lg border-slate-200 focus:border-primary focus:ring-primary text-sm p-2.5" placeholder="10" type="number" min="0.01" step="0.01" required>
                <p class="text-xs text-slate-400">Percent: 1–100. Fixed: rupiah amount.</p>
                @error('discount_value')<p class="text-red-500 text-xs">{{ $message }}</p>@enderror
            </div>

            <div class="flex flex-col gap-2">
                <label class="text-sm font-semibold text-slate-700">Maximum discount (percent only)</label>
                <input name="max_discount" value="{{ old('max_discount', $promo->max_discount ?? '') }}" class="w-full rounded-lg border-slate-200 focus:border-primary focus:ring-primary text-sm p-2.5" placeholder="e.g. 200000" type="number" min="0" step="0.01">
                <p class="text-xs text-slate-400">Leave empty for no cap. Ignored for fixed discounts.</p>
                @error('max_discount')<p class="text-red-500 text-xs">{{ $message }}</p>@enderror
            </div>

            <div class="flex flex-col gap-2">
                <label class="text-sm font-semibold text-slate-700">Minimum transaction (Rp)</label>
                <input name="min_transaction" value="{{ old('min_transaction', $promo->min_transaction ?? 0) }}" class="w-full rounded-lg border-slate-200 focus:border-primary focus:ring-primary text-sm p-2.5" placeholder="0" type="number" min="0" step="0.01">
                @error('min_transaction')<p class="text-red-500 text-xs">{{ $message }}</p>@enderror
            </div>

            <div class="flex flex-col gap-2">
                <label class="text-sm font-semibold text-slate-700">Total usage limit</label>
                <input name="usage_limit" value="{{ old('usage_limit', $promo->usage_limit ?? '') }}" class="w-full rounded-lg border-slate-200 focus:border-primary focus:ring-primary text-sm p-2.5" placeholder="Empty = unlimited" type="number" min="1">
                @error('usage_limit')<p class="text-red-500 text-xs">{{ $message }}</p>@enderror
            </div>

            <div class="flex flex-col gap-2">
                <label class="text-sm font-semibold text-slate-700">Limit per user</label>
                <input name="per_user_limit" value="{{ old('per_user_limit', $promo->per_user_limit ?? 1) }}" class="w-full rounded-lg border-slate-200 focus:border-primary focus:ring-primary text-sm p-2.5" type="number" min="1" required>
                @error('per_user_limit')<p class="text-red-500 text-xs">{{ $message }}</p>@enderror
            </div>

            <div class="flex flex-col gap-2">
                <label class="text-sm font-semibold text-slate-700">Starts at</label>
                <input name="starts_at" value="{{ old('starts_at', $promo->starts_at?->format('Y-m-d\TH:i') ?? '') }}" class="w-full rounded-lg border-slate-200 focus:border-primary focus:ring-primary text-sm p-2.5" type="datetime-local">
                <p class="text-xs text-slate-400">Empty = active immediately.</p>
                @error('starts_at')<p class="text-red-500 text-xs">{{ $message }}</p>@enderror
            </div>

            <div class="flex flex-col gap-2">
                <label class="text-sm font-semibold text-slate-700">Expires at</label>
                <input name="expires_at" value="{{ old('expires_at', $promo->expires_at?->format('Y-m-d\TH:i') ?? '') }}" class="w-full rounded-lg border-slate-200 focus:border-primary focus:ring-primary text-sm p-2.5" type="datetime-local">
                <p class="text-xs text-slate-400">Empty = never expires.</p>
                @error('expires_at')<p class="text-red-500 text-xs">{{ $message }}</p>@enderror
            </div>

            <div class="flex flex-col gap-2">
                <label class="text-sm font-semibold text-slate-700">Restrict to tour package</label>
                <select name="tour_package_id" class="w-full rounded-lg border-slate-200 focus:border-primary focus:ring-primary text-sm p-2.5">
                    <option value="">All packages</option>
                    @foreach($packages as $package)
                        <option value="{{ $package->id }}" @selected((string) old('tour_package_id', $promo->tour_package_id ?? '') === (string) $package->id)>{{ $package->title }}</option>
                    @endforeach
                </select>
                @error('tour_package_id')<p class="text-red-500 text-xs">{{ $message }}</p>@enderror
            </div>

            <div class="flex flex-col gap-2">
                <label class="text-sm font-semibold text-slate-700">Restrict to destination</label>
                <select name="destination_id" class="w-full rounded-lg border-slate-200 focus:border-primary focus:ring-primary text-sm p-2.5">
                    <option value="">All destinations</option>
                    @foreach($destinations as $destination)
                        <option value="{{ $destination->id }}" @selected((string) old('destination_id', $promo->destination_id ?? '') === (string) $destination->id)>{{ $destination->name }}</option>
                    @endforeach
                </select>
                @error('destination_id')<p class="text-red-500 text-xs">{{ $message }}</p>@enderror
            </div>

            <div class="col-span-2 flex items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
                <input type="hidden" name="is_active" value="0">
                <input id="is_active" name="is_active" value="1" type="checkbox" class="rounded border-slate-300 text-primary focus:ring-primary h-4 w-4" @checked(old('is_active', $promo->is_active ?? true))>
                <label for="is_active" class="text-sm font-semibold text-slate-700">Active</label>
                <span class="text-xs text-slate-500">Inactive codes are rejected at checkout.</span>
            </div>
        </div>

        <div class="pt-6 flex items-center justify-end gap-3 border-t border-slate-100">
            <a href="{{ route('admin.promos.index') }}" class="px-6 py-2.5 rounded-lg text-sm font-bold text-slate-600 hover:bg-slate-100 transition-colors">Cancel</a>
            <button type="submit" class="px-8 py-2.5 rounded-lg text-sm font-bold text-white bg-primary shadow-lg shadow-primary/25 hover:bg-primary/90 transition-all flex items-center gap-2">
                <span class="material-symbols-outlined text-[20px]">check_circle</span>
                {{ $editing ? 'Save changes' : 'Create promo' }}
            </button>
        </div>
    </div>
</div>

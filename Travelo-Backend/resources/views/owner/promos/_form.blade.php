@php($editing = isset($promo) && $promo !== null)

<form method="POST" action="{{ $editing ? route('owner.promos.update', $promo) : route('owner.promos.store') }}" class="space-y-6">
    @csrf
    @if ($editing)
        @method('PATCH')
    @endif

    @if ($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-800 px-6 py-4 rounded-xl">Periksa kembali kolom yang ditandai.</div>
    @endif

    <div class="bg-white border border-slate-200 rounded-xl p-6 grid grid-cols-1 md:grid-cols-2 gap-5">
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1">Kode</label>
            <input name="code" value="{{ old('code', $promo->code ?? '') }}" required class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm uppercase" placeholder="SUMMER10" />
            @error('code')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1">Deskripsi</label>
            <input name="description" value="{{ old('description', $promo->description ?? '') }}" class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm" />
            @error('description')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1">Tipe diskon</label>
            <select name="discount_type" class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm" required>
                <option value="percent" @selected(old('discount_type', $promo->discount_type ?? 'percent') === 'percent')>Persentase (%)</option>
                <option value="fixed" @selected(old('discount_type', $promo->discount_type ?? 'percent') === 'fixed')>Nominal (Rp)</option>
            </select>
        </div>

        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1">Nilai diskon</label>
            <input name="discount_value" value="{{ old('discount_value', $promo->discount_value ?? '') }}" type="number" min="0.01" step="0.01" required class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm" />
            @error('discount_value')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1">Maksimal diskon (persen)</label>
            <input name="max_discount" value="{{ old('max_discount', $promo->max_discount ?? '') }}" type="number" min="0" step="0.01" class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm" />
        </div>

        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1">Minimum transaksi (Rp)</label>
            <input name="min_transaction" value="{{ old('min_transaction', $promo->min_transaction ?? 0) }}" type="number" min="0" step="0.01" class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm" />
        </div>

        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1">Batas total pemakaian</label>
            <input name="usage_limit" value="{{ old('usage_limit', $promo->usage_limit ?? '') }}" type="number" min="1" placeholder="Kosong = tanpa batas" class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm" />
        </div>

        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1">Batas per pengguna</label>
            <input name="per_user_limit" value="{{ old('per_user_limit', $promo->per_user_limit ?? 1) }}" type="number" min="1" required class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm" />
        </div>

        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1">Mulai</label>
            <input type="datetime-local" name="starts_at" value="{{ old('starts_at', $promo->starts_at?->format('Y-m-d\TH:i') ?? '') }}" class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm" />
        </div>

        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1">Kedaluwarsa</label>
            <input type="datetime-local" name="expires_at" value="{{ old('expires_at', $promo->expires_at?->format('Y-m-d\TH:i') ?? '') }}" class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm" />
        </div>

        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1">Destinasi</label>
            <select name="destination_id" class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm">
                <option value="">— Pilih destinasi —</option>
                @foreach ($destinations as $destination)
                    <option value="{{ $destination->id }}" @selected((int) old('destination_id', $promo->destination_id ?? 0) === (int) $destination->id)>{{ $destination->name }}</option>
                @endforeach
            </select>
            @error('destination_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1">Paket (opsional)</label>
            <select name="tour_package_id" class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm">
                <option value="">— Semua paket di destinasi —</option>
                @foreach ($packages as $package)
                    <option value="{{ $package->id }}" @selected((int) old('tour_package_id', $promo->tour_package_id ?? 0) === (int) $package->id)>{{ $package->title }}</option>
                @endforeach
            </select>
            @error('tour_package_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <div class="md:col-span-2">
            <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $promo->is_active ?? true)) />
                Aktif
            </label>
        </div>
    </div>

    <div class="flex items-center gap-3">
        <button type="submit" class="bg-primary hover:bg-primary/90 text-white px-5 py-2.5 rounded-lg font-semibold">
            {{ $editing ? 'Simpan perubahan' : 'Buat promo' }}
        </button>
        <a href="{{ route('owner.promos.index') }}" class="text-sm font-semibold text-slate-600 hover:text-primary">Batal</a>
    </div>
</form>

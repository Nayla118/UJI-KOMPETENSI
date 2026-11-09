@php($editing = isset($staff))

<form method="POST" action="{{ $editing ? route('owner.staff.update', $staff) : route('owner.staff.store') }}" class="space-y-6">
    @csrf
    @if ($editing)
        @method('PATCH')
    @endif

    @if ($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-800 px-6 py-4 rounded-xl">
            Periksa kembali kolom yang ditandai.
        </div>
    @endif

    <div class="bg-white border border-slate-200 rounded-xl p-6 grid grid-cols-1 md:grid-cols-2 gap-5">
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1">Nama</label>
            <input name="name" value="{{ old('name', $staff->name ?? '') }}" required class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm" />
            @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1">Email</label>
            <input type="email" name="email" value="{{ old('email', $staff->email ?? '') }}" required class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm" />
            @error('email')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1">Telepon (opsional)</label>
            <input name="phone" value="{{ old('phone', $staff->phone ?? '') }}" class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm" />
            @error('phone')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1">Role</label>
            <select name="role" class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm" required>
                @foreach ($roles as $value => $label)
                    <option value="{{ $value }}" @selected(old('role', $staff->role ?? 'manager') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('role')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <div class="md:col-span-2">
            <label class="block text-sm font-semibold text-slate-700 mb-2">Destinasi</label>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                @foreach ($destinations as $destination)
                    <label class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm">
                        <input type="checkbox" name="destinations[]" value="{{ $destination->id }}"
                            @checked(in_array($destination->id, old('destinations', $assigned ?? []))) />
                        <span>{{ $destination->name }}</span>
                    </label>
                @endforeach
            </div>
            @if ($destinations->isEmpty())
                <p class="text-sm text-red-600">Anda belum memiliki destinasi. Hubungi admin untuk penugasan.</p>
            @endif
            @error('destinations')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            @error('destinations.*')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        @if ($editing)
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Password baru (opsional)</label>
                <input type="password" name="password" class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm" />
                @error('password')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Konfirmasi password</label>
                <input type="password" name="password_confirmation" class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm" />
            </div>
        @endif
    </div>

    <div class="flex items-center gap-3">
        <button type="submit" class="bg-primary hover:bg-primary/90 text-white px-5 py-2.5 rounded-lg font-semibold">
            {{ $editing ? 'Simpan perubahan' : 'Buat staf' }}
        </button>
        <a href="{{ route('owner.staff.index') }}" class="text-sm font-semibold text-slate-600 hover:text-primary">Batal</a>
    </div>
</form>

@extends('layouts.owner')

@section('title', 'Staf Destinasi | Travelo')

@section('content')
<header class="bg-white sticky top-0 z-20 border-b border-slate-200 px-8 py-4">
    <div class="max-w-7xl mx-auto flex items-center justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold tracking-tight">Staf Destinasi</h2>
            <p class="text-sm text-slate-500">Kelola manager dan gate staff untuk destinasi Anda. Nonaktifkan, jangan hapus.</p>
        </div>
        <a href="{{ route('owner.staff.create') }}" class="bg-primary hover:bg-primary/90 text-white px-5 py-2.5 rounded-lg font-semibold flex items-center gap-2 transition-all shadow-sm">
            <span class="material-symbols-outlined text-[20px]">person_add</span>
            Tambah Staf
        </a>
    </div>
</header>

<div class="p-8 max-w-7xl mx-auto space-y-6">
    @if (session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 px-6 py-4 rounded-xl">{{ session('success') }}</div>
    @endif

    @if (session('temp_password'))
        <div class="bg-amber-50 border border-amber-200 text-amber-900 px-6 py-4 rounded-xl">
            <p class="font-semibold">Password sementara untuk {{ session('temp_password_staff') }}</p>
            <p class="mt-1 font-mono text-lg">{{ session('temp_password') }}</p>
            <p class="mt-1 text-sm">Tampilkan sekali ini saja dan minta staf menggantinya setelah masuk.</p>
        </div>
    @endif

    <form method="GET" action="{{ route('owner.staff.index') }}" class="flex flex-col sm:flex-row gap-3">
        <input name="search" value="{{ request('search') }}" placeholder="Cari nama atau email..." class="w-full sm:w-80 rounded-xl border border-slate-200 px-4 py-2.5 text-sm" />
        <select name="role" onchange="this.form.submit()" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm">
            <option value="">Semua role</option>
            <option value="manager" @selected(request('role') === 'manager')>Manager</option>
            <option value="gate_staff" @selected(request('role') === 'gate_staff')>Gate staff</option>
        </select>
        <button type="submit" class="rounded-xl bg-slate-800 px-5 py-2.5 text-sm font-semibold text-white">Cari</button>
    </form>

    <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-slate-500">Nama</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-500">Email</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-500">Role</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-500">Destinasi</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-500">Status</th>
                        <th class="px-4 py-3 text-right font-semibold text-slate-500">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($staff as $member)
                        <tr>
                            <td class="px-4 py-3 font-medium text-ink">{{ $member->name }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $member->email }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $member->isManager() ? 'Manager' : 'Gate staff' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $member->destinations->pluck('name')->join(', ') ?: '-' }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $member->isActive() ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700' }}">
                                    {{ $member->isActive() ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <a href="{{ route('owner.staff.edit', $member) }}" class="text-amber-700 hover:underline">Edit</a>
                                <form method="POST" action="{{ route('owner.staff.toggle-active', $member) }}" class="inline-block ml-3">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="{{ $member->isActive() ? 'text-red-600' : 'text-green-700' }} hover:underline">
                                        {{ $member->isActive() ? 'Nonaktifkan' : 'Aktifkan' }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-slate-500">Belum ada staf. Tambahkan manager atau gate staff untuk destinasi Anda.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{ $staff->links() }}
</div>
@endsection

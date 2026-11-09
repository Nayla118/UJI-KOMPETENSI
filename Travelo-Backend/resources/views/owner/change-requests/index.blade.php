@extends('layouts.owner')

@section('title', 'Pengajuan Pembatalan | Travelo Owner')

@section('content')
<header class="bg-white sticky top-0 z-20 border-b border-slate-200 px-8 py-4">
    <div class="max-w-7xl mx-auto">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold tracking-tight">Pengajuan Pembatalan</h2>
                <p class="text-sm text-slate-500">Setujui atau tolak permintaan pembatalan untuk destinasi Anda.</p>
            </div>
        </div>
    </div>
</header>

<div class="p-8 max-w-7xl mx-auto space-y-6">
    <form method="GET" action="{{ route('owner.change-requests.index') }}" class="flex flex-col md:flex-row gap-4 items-center justify-between mb-6">
        <select name="status" class="w-full md:w-48 pl-4 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none">
            <option value="">Semua Status</option>
            <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
            <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Disetujui</option>
            <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Ditolak</option>
            <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Dibatalkan</option>
        </select>
    </form>

    @if($requests->isEmpty())
        <div class="text-center py-12">
            <span class="material-symbols-outlined text-4xl text-slate-300 mb-3">pending_actions</span>
            <p class="text-lg text-slate-500">Tidak ada pengajuan pembatalan.</p>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm divide-y divide-slate-100">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-slate-500 font-semibold">Traveller</th>
                        <th class="px-4 py-3 text-left text-slate-500 font-semibold">Destinasi</th>
                        <th class="px-4 py-3 text-left text-slate-500 font-semibold">Tanggal Kunjungan</th>
                        <th class="px-4 py-3 text-left text-slate-500 font-semibold">Alasan</th>
                        <th class="px-4 py-3 text-left text-slate-500 font-semibold">Status</th>
                        <th class="px-4 py-3 text-right text-slate-500 font-semibold">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($requests as $req)
                        <tr>
                            <td class="px-4 py-3 text-ink font-medium">{{ optional($req->booking->user)->name ?? 'Traveller' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ optional($req->booking->tourPackage->destination)->name ?? '-' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ optional($req->booking->booking_date)->format('d M Y') ?? '-' }}</td>
                            <td class="px-4 py-3 text-slate-600">
                                {{ $req->reason ?? '-' }}
                                @if($req->status === 'approved')
                                    <p class="mt-2 text-xs uppercase tracking-wide text-slate-400">Refund</p>
                                    <p class="text-xs font-semibold
                                        @if($req->refund_status === 'refunded') text-green-700
                                        @elseif($req->refund_status === 'failed') text-red-700
                                        @else text-amber-700 @endif">
                                        {{ ucfirst(str_replace('_', ' ', $req->refund_status ?? 'not applicable')) }}
                                    </p>
                                    @if($req->refund_percentage !== null)
                                        <p class="text-xs text-slate-500">
                                            {{ $req->refund_percentage }}% · IDR {{ number_format((float) ($req->refund_amount ?? 0), 0, ',', '.') }}
                                        </p>
                                    @endif
                                    @if($req->refund_status === 'failed' && $req->refund_failure_reason)
                                        <p class="mt-1 text-xs text-red-600">{{ $req->refund_failure_reason }}</p>
                                    @endif
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold
                                    @if($req->status === 'approved') bg-green-50 text-green-700
                                    @elseif($req->status === 'pending') bg-amber-50 text-amber-700
                                    @elseif($req->status === 'rejected') bg-red-50 text-red-700
                                    @elseif($req->status === 'cancelled') bg-slate-50 text-slate-700
                                    @else bg-slate-50 text-slate-700 @endif">
                                    {{ ucfirst($req->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                @if($req->status === 'pending')
                                    <button type="button" class="text-xs bg-green-50 text-green-700 hover:bg-green-100 px-3 py-1.5 rounded-lg transition-colors" onclick="document.getElementById('approve-{{ $req->id }}').submit()">
                                        Setujui
                                    </button>
                                    <form id="approve-{{ $req->id }}" method="POST" action="{{ route('owner.change-requests.approve', $req) }}" class="hidden">
                                        @csrf @method('PATCH')
                                    </form>

                                    <button type="button" class="ml-2 text-xs bg-red-50 text-red-700 hover:bg-red-100 px-3 py-1.5 rounded-lg transition-colors" onclick="document.getElementById('reject-{{ $req->id }}').submit()">
                                        Tolak
                                    </button>
                                    <form id="reject-{{ $req->id }}" method="POST" action="{{ route('owner.change-requests.reject', $req) }}" class="hidden">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="admin_note" value="Ditolak oleh pemilik destinasi">
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $requests->links() }}
    @endif
</div>
@endsection

@extends('layouts.owner')

@section('title', 'Pembayaran | Travelo Owner')

@section('content')
<header class="bg-white sticky top-0 z-20 border-b border-slate-200 px-8 py-4">
    <div class="max-w-7xl mx-auto flex items-center justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold tracking-tight">Pembayaran</h2>
            <p class="text-sm text-slate-500">Riwayat pembayaran untuk destinasi yang Anda kelola.</p>
        </div>
    </div>
</header>

<div class="p-8 max-w-7xl mx-auto space-y-6">
    <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-slate-500">ID</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-500">Booking</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-500">Metode</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-500">Status</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-500">Tanggal</th>
                    <th class="px-4 py-3 text-right font-semibold text-slate-500">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($payments as $payment)
                <tr>
                    <td class="px-4 py-3 font-medium text-ink">{{ $payment->id }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ $payment->booking?->reference ?? '-' }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ $payment->payment_method ?? '-' }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ ucfirst($payment->payment_status ?? '-') }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ $payment->created_at?->format('d M Y H:i') ?? '-' }}</td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <a href="{{ route('owner.payments.show', $payment) }}" class="text-amber-700 hover:underline">Lihat</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-4 py-10 text-center text-slate-500">Belum ada data pembayaran.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $payments->links() }}
</div>
@endsection

@extends('layouts.owner')

@section('title', 'Scanner Tiket | Travelo Owner')

@section('content')
@php
    $configuredAppUrl = rtrim((string) config('app.url'), '/');
    $secureScannerUrl = str_starts_with($configuredAppUrl, 'https://')
        ? $configuredAppUrl.'/owner/scanner'
        : null;
@endphp

<div class="mx-auto max-w-5xl space-y-6 p-4 sm:p-6 lg:p-8">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <div class="inline-flex items-center gap-2 rounded-full bg-blush px-3 py-1 text-xs font-bold uppercase tracking-wider text-primary">
                <span class="material-symbols-outlined text-[16px]">qr_code_scanner</span>
                Check-in di Lokasi
            </div>
            <h1 class="mt-3 text-2xl font-black tracking-tight text-ink">Scan Tiket</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">
                Scan kode QR tertiket dari e-ticket Travelo pengunjung. Tiket ditebak tepat sekali dan hanya
                pada tanggal kunjungan; memindai ulang akan melaporkan telah digunakan.
            </p>
        </div>

        <div class="w-full rounded-xl border border-slate-200 bg-white p-4 lg:w-80">
            <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Cakupan destinasi</p>
            @if($destinations->isEmpty())
                <p class="mt-2 text-sm font-semibold text-danger">Tidak ada destinasi ditugaskan</p>
                <p class="mt-1 text-xs text-muted">Minta administrator untuk menugaskan akun ini ke destinasi sebelum memindai.</p>
            @else
                <p class="mt-2 text-sm font-semibold text-ink">{{ $destinations->pluck('name')->join(', ') }}</p>
                <p class="mt-1 text-xs text-muted">Hanya tiket untuk destinasi ini yang dapat dipindai.</p>
            @endif
            <a href="{{ route('owner.scanner.history') }}" class="mt-3 inline-flex items-center gap-1 text-sm font-semibold text-primary hover:underline">
                <span class="material-symbols-outlined text-[18px]">history</span>
                Riwayat scan saya
            </a>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-4">
        <section class="space-y-6 lg:col-span-3">
            <div
                id="ticket-scanner"
                class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6"
                data-redeem-url="{{ route('owner.tickets.redeem') }}"
                data-csrf-token="{{ csrf_token() }}"
                data-secure-scanner-url="{{ $secureScannerUrl }}"
                data-gate-staff="{{ $isGateStaff ? '1' : '0' }}"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-bold text-ink">Kamera Scanner</h2>
                        <p class="mt-1 text-sm text-slate-600">
                            Kamera dimulai otomatis. Taruh kode QR dalam pandang dan tiket dicek segera terdeteksi;
                            foto QR dan input token tetap tersedia sebagai cadangan.
                        </p>
                    </div>
                    <span class="material-symbols-outlined text-2xl text-primary" aria-hidden="true">photo_camera</span>
                </div>

                <div id="scanner-insecure-warning" class="mt-5 hidden rounded-xl border border-warning/30 bg-orange-50 p-4" role="alert">
                    <div class="flex items-start gap-3">
                        <span class="material-symbols-outlined mt-0.5 text-xl text-warning" aria-hidden="true">warning</span>
                        <div class="min-w-0">
                            <p class="text-sm font-bold text-ink">Kamera langsung diblokir pada alamat ini</p>
                            <p class="mt-1 text-xs text-slate-600">
                                Browser hanya mengizinkan akses kamera langsung pada HTTPS atau localhost. Anda tetap dapat memindai:
                                gunakan <span class="font-semibold text-ink">Ambil Foto QR</span> di bawah, atau buka
                                halaman ini melalui HTTPS.
                            </p>
                            <a id="scanner-secure-link" href="{{ $secureScannerUrl ?: '#' }}" class="mt-2 hidden text-xs font-bold text-primary underline underline-offset-2">
                                Buka scanner HTTPS yang aman
                            </a>
                        </div>
                    </div>
                </div>

                <div class="mt-5">
                    <div id="scanner-reader" class="hidden overflow-hidden rounded-xl bg-slate-900" aria-label="Pratinjau kamera"></div>

                    <div id="scanner-placeholder" class="flex min-h-48 flex-col items-center justify-center rounded-xl border-2 border-dashed border-slate-200 bg-cream px-6 py-8 text-center">
                        <div class="flex size-12 items-center justify-center rounded-full bg-blush text-primary">
                            <span class="material-symbols-outlined text-3xl" aria-hidden="true">qr_code_scanner</span>
                        </div>
                        <p class="mt-3 text-sm font-bold text-ink">Kamera dimulai otomatis</p>
                        <p class="mt-1 max-w-sm text-xs leading-5 text-slate-600">
                            Izinkan akses kamera ketika browser meminta. Jika kamera tetap mati, tekan
                            <span class="font-semibold">Mulai Kamera</span> atau gunakan
                            <span class="font-semibold">Ambil Foto QR</span>. Pada alamat
                            <span class="font-semibold">http://</span> browser memblokir akses kamera langsung —
                            gunakan <span class="font-semibold">Ambil Foto QR</span> sebagai gantinya.
                        </p>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-3">
                        <button type="button" id="scanner-start" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-bold text-white transition-colors hover:bg-primary-dark disabled:cursor-not-allowed disabled:opacity-60">
                            <span class="material-symbols-outlined text-[20px]" aria-hidden="true">videocam</span>
                            Mulai Kamera
                        </button>
                        <button type="button" id="scanner-stop" class="hidden min-h-11 items-center justify-center gap-2 rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-600 transition-colors hover:bg-blush">
                            <span class="material-symbols-outlined text-[20px]" aria-hidden="true">videocam_off</span>
                            Hentikan Kamera
                        </button>
                        <button type="button" id="scanner-next" class="hidden min-h-11 items-center justify-center gap-2 rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-600 transition-colors hover:bg-blush">
                            <span class="material-symbols-outlined text-[20px]" aria-hidden="true">restart_alt</span>
                            Scan Tiket Berikutnya
                        </button>
                    </div>

                    <div id="scanner-camera-picker" class="mt-3 hidden flex-wrap items-center gap-3 rounded-xl border border-slate-200 bg-cream px-3 py-3">
                        <label for="scanner-camera-select" class="text-xs font-bold uppercase tracking-wider text-slate-500">Kamera</label>
                        <select id="scanner-camera-select" class="min-h-11 min-w-48 flex-1 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"></select>
                        <p class="w-full text-xs leading-5 text-slate-600">Pilih kamera belakang jika pratinjau menunjukkan yang salah.</p>
                    </div>

                    <div class="mt-3 rounded-xl border border-slate-200 bg-cream px-3 py-3">
                        <div class="flex flex-wrap gap-3">
                            <button type="button" id="scanner-photo-button" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-primary/30 bg-white px-4 py-2.5 text-sm font-bold text-primary transition-colors hover:bg-blush disabled:cursor-not-allowed disabled:opacity-60">
                                <span class="material-symbols-outlined text-[20px]" aria-hidden="true">photo_camera</span>
                                Ambil Foto QR
                            </button>
                            <button type="button" id="scanner-file-button" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 transition-colors hover:bg-blush disabled:cursor-not-allowed disabled:opacity-60">
                                <span class="material-symbols-outlined text-[20px]" aria-hidden="true">image_search</span>
                                Scan Gambar QR
                            </button>
                            <input type="file" id="scanner-photo-input" accept="image/*" capture="environment" aria-label="Ambil foto kode QR e-ticket" class="sr-only">
                            <input type="file" id="scanner-file-input" accept="image/*" aria-label="Pilih screenshot kode QR e-ticket" class="sr-only">
                        </div>
                        <p class="mt-3 max-w-lg text-xs leading-5 text-slate-600">
                            <span class="font-semibold text-ink">Ambil Foto QR</span> membuka kamera ponsel langsung
                            dan bekerja bahkan ketika akses kamera langsung diblokir. <span class="font-semibold text-ink">Scan Gambar QR</span>
                            membaca screenshot yang tajam. Gambar didekode di browser dan hanya token QR yang dikirim ke server.
                        </p>
                    </div>

                    <div id="scanner-file-reader" class="hidden" aria-hidden="true"></div>

                    <p id="scanner-camera-status" class="mt-3 min-h-5 text-xs font-medium text-slate-600" role="status" aria-live="polite">
                        Memulai kamera otomatis...
                    </p>
                </div>

                <div class="mt-6 border-t border-slate-100 pt-5">
                    <h3 class="text-sm font-bold text-ink">Input Manual</h3>
                    <p class="mt-1 text-xs leading-5 text-slate-600">
                        Tempel token tiket jika kamera tidak tersedia. Token dikirim langsung ke server dan tidak disimpan di browser.
                    </p>
                    <form id="manual-ticket-form" method="POST" action="{{ route('owner.tickets.redeem') }}" class="mt-3 space-y-3">
                        @csrf
                        <label for="manual-ticket-token" class="block text-xs font-bold text-slate-600">Token Tiket</label>
                        <input
                            type="password"
                            id="manual-ticket-token"
                            name="token"
                            maxlength="2048"
                            autocomplete="off"
                            spellcheck="false"
                            required
                            class="min-h-11 w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-ink outline-none transition-colors placeholder:text-slate-400 focus:border-primary focus:ring-2 focus:ring-primary/20"
                            placeholder="Tempel token tiket yang ditandatangani"
                        >
                        <button type="submit" id="manual-ticket-submit" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 transition-colors hover:bg-blush disabled:cursor-not-allowed disabled:opacity-60">
                            <span class="material-symbols-outlined text-[20px]" aria-hidden="true">key</span>
                            Periksa Tiket
                        </button>
                    </form>
                </div>
            </div>
        </section>

        <aside class="space-y-4 lg:col-span-1">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-bold text-ink">Arti Setiap Hasil</h2>
                <ul class="mt-3 space-y-3 text-xs leading-5 text-slate-600">
                    <li class="flex gap-2">
                        <span class="material-symbols-outlined text-[18px] text-success" aria-hidden="true">check_circle</span>
                        <span><span class="font-bold text-ink">Terkonfirmasi</span> &mdash; tiket ditandai digunakan dan tidak dapat masuk lagi.</span>
                    </li>
                    <li class="flex gap-2">
                        <span class="material-symbols-outlined text-[18px] text-warning" aria-hidden="true">block</span>
                        <span><span class="font-bold text-ink">Sudah Digunakan</span> &mdash; tiket ini telah dipindai sebelumnya. Jangan memasukkan kedua kalinya.</span>
                    </li>
                    <li class="flex gap-2">
                        <span class="material-symbols-outlined text-[18px] text-warning" aria-hidden="true">event_upcoming</span>
                        <span><span class="font-bold text-ink">Belum Waktunya</span> &mdash; tanggal kunjungan masih mendatang. Tiket tidak ditebak dan pengunjung tidak dapat masuk.</span>
                    </li>
                    <li class="flex gap-2">
                        <span class="material-symbols-outlined text-[18px] text-danger" aria-hidden="true">event_busy</span>
                        <span><span class="font-bold text-ink">Kadaluwarsa</span> &mdash; tanggal kunjungan telah lewat.</span>
                    </li>
                    <li class="flex gap-2">
                        <span class="material-symbols-outlined text-[18px] text-danger" aria-hidden="true">payments</span>
                        <span><span class="font-bold text-ink">Pembayaran belum dikonfirmasi</span> &mdash; booking tidak layak masuk.</span>
                    </li>
                    <li class="flex gap-2">
                        <span class="material-symbols-outlined text-[18px] text-danger" aria-hidden="true">wrong_location</span>
                        <span><span class="font-bold text-ink">Destinasi Salah</span> &mdash; akun Anda tidak ditugaskan ke destinasi tiket ini.</span>
                    </li>
                </ul>
            </div>
        </aside>
    </div>

    <dialog id="scanner-result" aria-labelledby="scanner-result-title" aria-describedby="scanner-result-message">
        <div id="scanner-result-card" class="rounded-xl border border-slate-200 bg-white p-5 shadow-2xl sm:p-6">
            <div class="flex items-start gap-3">
                <div id="scanner-result-icon-wrap" class="flex size-11 shrink-0 items-center justify-center rounded-full bg-sage/30">
                    <span id="scanner-result-icon" class="material-symbols-outlined text-2xl text-success" aria-hidden="true">check_circle</span>
                </div>
                <div class="min-w-0 flex-1">
                    <p id="scanner-result-kicker" class="text-xs font-bold uppercase tracking-wider text-success">Check-in lengkap</p>
                    <h2 id="scanner-result-title" class="mt-1 text-lg font-black text-ink">Tiket diredeem</h2>
                </div>
                <button type="button" id="scanner-result-x" class="inline-flex size-9 shrink-0 items-center justify-center rounded-full text-xl leading-none text-slate-500 transition-colors hover:bg-blush hover:text-ink" aria-label="Tutup hasil scan">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <p id="scanner-result-message" class="mt-4 text-sm leading-6 text-slate-600"></p>

            <dl id="scanner-result-details" class="mt-4 hidden space-y-2 border-t border-slate-100 pt-4 text-sm"></dl>

            <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <button type="button" id="scanner-result-close" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-600 transition-colors hover:bg-blush">
                    Tutup
                </button>
                <button type="button" id="scanner-result-next" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-bold text-white transition-colors hover:bg-primary-dark">
                    <span class="material-symbols-outlined text-[20px]" aria-hidden="true">restart_alt</span>
                    Scan Tiket Berikutnya
                </button>
            </div>
        </div>
    </dialog>
</div>

@endsection

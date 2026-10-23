@extends('layouts.admin')

@section('title', 'Ticket scanner | Travelo Admin')

@push('styles')
<style>
    /* html5-qrcode injects its own video element, so it cannot use Tailwind. */
    #scanner-reader video {
        display: block;
        width: 100% !important;
        height: auto !important;
        border-radius: 1rem;
    }

    /* Native modal keeps the result in the browser's top layer, above the
       admin sidebar, and handles Escape/focus trapping for us. */
    #scanner-result {
        width: calc(100% - 2rem);
        max-width: 32rem;
        max-height: calc(100vh - 2rem);
        margin: auto;
        padding: 0;
        border: 0;
        background: transparent;
        overflow: auto;
    }

    #scanner-result::backdrop {
        background: rgba(15, 23, 42, 0.55);
        backdrop-filter: blur(2px);
    }
</style>
@endpush

@section('content')
@php
    $configuredAppUrl = rtrim((string) config('app.url'), '/');
    $secureScannerUrl = str_starts_with($configuredAppUrl, 'https://')
        ? $configuredAppUrl.'/admin/scanner'
        : null;
@endphp
<div class="mx-auto max-w-6xl space-y-6 p-4 sm:p-6 lg:p-8">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <div class="inline-flex items-center gap-2 rounded-full bg-blush px-3 py-1 text-xs font-bold uppercase tracking-wider text-primary">
                <span class="material-symbols-outlined text-[16px]">qr_code_scanner</span>
                Gate check-in
            </div>
            <h1 class="mt-3 text-3xl font-black tracking-tight text-ink">Scan ticket</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-muted">
                Scan the signed QR code from the traveller&rsquo;s Travelo e-ticket. A ticket is redeemed
                exactly once and only on its visit date; scanning it again will report that it has already
                been used.
            </p>
        </div>

        <div class="w-full rounded-2xl border border-slate-200 bg-white p-4 lg:w-72">
            <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Access scope</p>
            @if($isAdmin)
                <p class="mt-2 text-sm font-semibold text-ink">All destinations</p>
                <p class="mt-1 text-xs text-muted">Admin access can redeem any valid ticket.</p>
            @elseif($destinations->isEmpty())
                <p class="mt-2 text-sm font-semibold text-danger">No destinations assigned</p>
                <p class="mt-1 text-xs text-muted">Ask an administrator to assign this account to a destination before scanning.</p>
            @else
                <p class="mt-2 text-sm font-semibold text-ink">{{ $destinations->join(', ') }}</p>
                <p class="mt-1 text-xs text-muted">Only tickets for these destinations can be redeemed.</p>
            @endif
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-5">
        <section class="space-y-6 lg:col-span-3">
            <div
                id="ticket-scanner"
                class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6"
                data-redeem-url="{{ route('admin.tickets.redeem') }}"
                data-csrf-token="{{ csrf_token() }}"
                data-secure-scanner-url="{{ $secureScannerUrl }}"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-bold text-ink">Camera scanner</h2>
                        <p class="mt-1 text-sm text-muted">
                            The camera starts automatically. Hold the QR code in view and the ticket is checked
                            as soon as it is detected; a QR photo and manual token remain available as fallbacks.
                        </p>
                    </div>
                    <span class="material-symbols-outlined text-2xl text-primary" aria-hidden="true">photo_camera</span>
                </div>

                <div id="scanner-insecure-warning" class="mt-5 hidden rounded-2xl border border-warning/30 bg-orange-50 p-4" role="alert">
                    <div class="flex items-start gap-3">
                        <span class="material-symbols-outlined mt-0.5 text-xl text-warning" aria-hidden="true">warning</span>
                        <div class="min-w-0">
                            <p class="text-sm font-bold text-ink">Live camera is blocked on this address</p>
                            <p class="mt-1 text-xs leading-5 text-muted">
                                Browsers only allow live camera access on HTTPS or localhost. You can still scan:
                                use <span class="font-semibold text-ink">Take QR photo</span> below, or open this
                                page over HTTPS.
                            </p>
                            <a id="scanner-secure-link" href="{{ $secureScannerUrl ?: '#' }}" class="mt-2 hidden text-xs font-bold text-primary underline underline-offset-2">
                                Open the secure HTTPS scanner
                            </a>
                        </div>
                    </div>
                </div>

                <div class="mt-5">
                    <div id="scanner-reader" class="hidden overflow-hidden rounded-2xl bg-slate-900" aria-label="Camera preview"></div>

                    <div id="scanner-placeholder" class="flex min-h-56 flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-200 bg-cream px-6 py-10 text-center">
                        <div class="flex size-14 items-center justify-center rounded-full bg-blush text-primary">
                            <span class="material-symbols-outlined text-3xl" aria-hidden="true">qr_code_scanner</span>
                        </div>
                        <p class="mt-4 text-sm font-bold text-ink">Camera starts automatically</p>
                        <p class="mt-1 max-w-sm text-xs leading-5 text-muted">
                            Allow camera access when your browser asks. If the camera stays off, press
                            <span class="font-semibold">Start camera</span> or use
                            <span class="font-semibold">Take QR photo</span>. On an
                            <span class="font-semibold">http://</span> address the browser blocks live
                            camera access &mdash; use <span class="font-semibold">Take QR photo</span> instead.
                        </p>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-3">
                        <button type="button" id="scanner-start" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-bold text-white transition-colors hover:bg-primary-dark disabled:cursor-not-allowed disabled:opacity-60">
                            <span class="material-symbols-outlined text-[20px]" aria-hidden="true">videocam</span>
                            Start camera
                        </button>
                        <button type="button" id="scanner-stop" class="hidden min-h-11 items-center justify-center gap-2 rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-600 transition-colors hover:bg-blush">
                            <span class="material-symbols-outlined text-[20px]" aria-hidden="true">videocam_off</span>
                            Stop camera
                        </button>
                        <button type="button" id="scanner-next" class="hidden min-h-11 items-center justify-center gap-2 rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-600 transition-colors hover:bg-blush">
                            <span class="material-symbols-outlined text-[20px]" aria-hidden="true">restart_alt</span>
                            Scan next ticket
                        </button>
                    </div>

                    <div id="scanner-camera-picker" class="mt-3 hidden flex-wrap items-center gap-3 rounded-xl border border-slate-200 bg-cream px-3 py-3">
                        <label for="scanner-camera-select" class="text-xs font-bold uppercase tracking-wider text-slate-500">Camera</label>
                        <select id="scanner-camera-select" class="min-h-11 min-w-48 flex-1 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"></select>
                        <p class="w-full text-xs leading-5 text-muted">Choose the rear camera if the preview shows the wrong one.</p>
                    </div>

                    <div class="mt-3 rounded-xl border border-slate-200 bg-cream px-3 py-3">
                        <div class="flex flex-wrap gap-3">
                            <button type="button" id="scanner-photo-button" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-primary/30 bg-white px-4 py-2.5 text-sm font-bold text-primary transition-colors hover:bg-blush disabled:cursor-not-allowed disabled:opacity-60">
                                <span class="material-symbols-outlined text-[20px]" aria-hidden="true">photo_camera</span>
                                Take QR photo
                            </button>
                            <button type="button" id="scanner-file-button" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 transition-colors hover:bg-blush disabled:cursor-not-allowed disabled:opacity-60">
                                <span class="material-symbols-outlined text-[20px]" aria-hidden="true">image_search</span>
                                Scan QR image
                            </button>
                            <input type="file" id="scanner-photo-input" accept="image/*" capture="environment" aria-label="Take a photo of the e-ticket QR code" class="sr-only">
                            <input type="file" id="scanner-file-input" accept="image/*" aria-label="Choose an e-ticket QR screenshot" class="sr-only">
                        </div>
                        <p class="mt-3 max-w-lg text-xs leading-5 text-muted">
                            <span class="font-semibold text-ink">Take QR photo</span> opens the phone camera
                            directly and works even when live camera access is blocked. <span class="font-semibold text-ink">Scan QR image</span>
                            reads a sharp screenshot. The image is decoded in this browser and only the QR token is sent to the server.
                        </p>
                    </div>

                    <div id="scanner-file-reader" class="hidden" aria-hidden="true"></div>

                    <p id="scanner-camera-status" class="mt-3 min-h-5 text-xs font-medium text-muted" role="status" aria-live="polite">
                        Starting the camera automatically...
                    </p>
                </div>

                <div class="mt-6 border-t border-slate-100 pt-5">
                    <h3 class="text-sm font-bold text-ink">Manual fallback</h3>
                    <p class="mt-1 text-xs leading-5 text-muted">
                        Paste the ticket token if the camera is unavailable. The token is sent directly to the
                        server and is not stored in this browser.
                    </p>

                    <form id="manual-ticket-form" method="POST" action="{{ route('admin.tickets.redeem') }}" class="mt-3 space-y-3">
                        @csrf
                        <label for="manual-ticket-token" class="block text-xs font-bold text-slate-600">Ticket token</label>
                        <input
                            type="password"
                            id="manual-ticket-token"
                            name="token"
                            maxlength="2048"
                            autocomplete="off"
                            spellcheck="false"
                            required
                            class="min-h-11 w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-ink outline-none transition-colors placeholder:text-slate-400 focus:border-primary focus:ring-2 focus:ring-primary/20"
                            placeholder="Paste the signed ticket token"
                        >
                        <button type="submit" id="manual-ticket-submit" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 transition-colors hover:bg-blush disabled:cursor-not-allowed disabled:opacity-60">
                            <span class="material-symbols-outlined text-[20px]" aria-hidden="true">key</span>
                            Check ticket
                        </button>
                    </form>
                </div>
            </div>
        </section>

        <aside class="space-y-4 lg:col-span-2">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-bold text-ink">What each result means</h2>
                <ul class="mt-3 space-y-3 text-xs leading-5 text-muted">
                    <li class="flex gap-2">
                        <span class="material-symbols-outlined text-[18px] text-success" aria-hidden="true">check_circle</span>
                        <span><span class="font-bold text-ink">Redeemed</span> &mdash; the ticket is now marked used and cannot enter again.</span>
                    </li>
                    <li class="flex gap-2">
                        <span class="material-symbols-outlined text-[18px] text-warning" aria-hidden="true">block</span>
                        <span><span class="font-bold text-ink">Already used</span> &mdash; this exact ticket was scanned before. Do not admit a second time.</span>
                    </li>
                    <li class="flex gap-2">
                        <span class="material-symbols-outlined text-[18px] text-warning" aria-hidden="true">event_upcoming</span>
                        <span><span class="font-bold text-ink">Not today</span> &mdash; the visit date is in the future. The ticket is not redeemed and the traveller cannot enter yet.</span>
                    </li>
                    <li class="flex gap-2">
                        <span class="material-symbols-outlined text-[18px] text-danger" aria-hidden="true">event_busy</span>
                        <span><span class="font-bold text-ink">Expired</span> &mdash; the visit date has passed.</span>
                    </li>
                    <li class="flex gap-2">
                        <span class="material-symbols-outlined text-[18px] text-danger" aria-hidden="true">payments</span>
                        <span><span class="font-bold text-ink">Payment not confirmed</span> &mdash; the booking is not eligible for entry.</span>
                    </li>
                    <li class="flex gap-2">
                        <span class="material-symbols-outlined text-[18px] text-danger" aria-hidden="true">wrong_location</span>
                        <span><span class="font-bold text-ink">Wrong destination</span> &mdash; your staff account is not assigned to this ticket&rsquo;s destination.</span>
                    </li>
                </ul>
            </div>
        </aside>
    </div>

    <dialog id="scanner-result" aria-labelledby="scanner-result-title" aria-describedby="scanner-result-message">
        <div id="scanner-result-card" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl sm:p-6">
            <div class="flex items-start gap-3">
                <div id="scanner-result-icon-wrap" class="flex size-11 shrink-0 items-center justify-center rounded-full bg-sage/30">
                    <span id="scanner-result-icon" class="material-symbols-outlined text-2xl text-success" aria-hidden="true">check_circle</span>
                </div>
                <div class="min-w-0 flex-1">
                    <p id="scanner-result-kicker" class="text-xs font-bold uppercase tracking-wider text-success">Check-in complete</p>
                    <h2 id="scanner-result-title" class="mt-1 text-lg font-black text-ink">Ticket redeemed</h2>
                </div>
                <button type="button" id="scanner-result-x" class="inline-flex size-9 shrink-0 items-center justify-center rounded-full text-xl leading-none text-slate-500 transition-colors hover:bg-blush hover:text-ink" aria-label="Close scan result">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <p id="scanner-result-message" class="mt-4 text-sm leading-6 text-muted"></p>

            <dl id="scanner-result-details" class="mt-4 hidden space-y-2 border-t border-slate-100 pt-4 text-sm"></dl>

            <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <button type="button" id="scanner-result-close" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-600 transition-colors hover:bg-blush">
                    Close
                </button>
                <button type="button" id="scanner-result-next" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-bold text-white transition-colors hover:bg-primary-dark">
                    <span class="material-symbols-outlined text-[20px]" aria-hidden="true">restart_alt</span>
                    Scan next ticket
                </button>
            </div>
        </div>
    </dialog>
</div>
@endsection

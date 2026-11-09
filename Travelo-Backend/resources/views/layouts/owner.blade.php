<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Travelo Owner')</title>
    <link rel="icon" type="image/png" href="/images/logos/travelo-favicon.png">
    <link rel="apple-touch-icon" href="/images/logos/travelo-logo-192.png">

    <!-- Brand fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@600;700;800&family=Nunito+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">

    <!-- Compiled theme (Tailwind v4 + project tokens). No CDN. -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-cream font-sans text-ink min-h-screen" id="app-body">
<div class="min-h-screen">
    <!-- Mobile backdrop -->
    <div id="sidebar-backdrop" class="fixed inset-0 bg-ink/30 z-40 hidden lg:hidden"></div>

    <!-- Sidebar -->
    <aside id="owner-sidebar" class="fixed inset-y-0 left-0 z-50 w-72 bg-white border-r border-slate-200 flex flex-col -translate-x-full lg:translate-x-0 transition-transform duration-200">
        <div class="p-6 flex items-center justify-between gap-3 border-b border-slate-100">
            <a href="{{ route('owner.dashboard') }}" class="flex items-center gap-3 group">
                <img src="/images/logos/travelo-logo-primary.png" alt="Travelo" class="h-12 w-12" width="48" height="48">
            </a>
            <div class="flex items-center gap-2">
                <button type="button" id="sidebar-close" class="lg:hidden inline-flex items-center justify-center size-10 rounded-xl text-muted hover:bg-blush" aria-label="Close navigation">
                    <span class="material-symbols-outlined">close</span>
                </button>
                <button type="button" id="dark-mode-toggle" class="inline-flex items-center justify-center size-10 rounded-xl text-muted hover:bg-blush" aria-label="Toggle dark mode">
                    <span class="material-symbols-outlined">dark_mode</span>
                </button>
            </div>
        </div>

        <nav class="flex-1 px-4 py-4 space-y-1 overflow-y-auto" aria-label="Owner navigation">
            @php($portalUser = auth()->user())

            @unless ($portalUser?->isGateStaff())
                <a href="{{ route('owner.dashboard') }}" class="flex items-center gap-3 px-4 py-3 {{ request()->routeIs('owner.dashboard') ? 'bg-primary/10 text-primary font-semibold' : 'text-slate-600 hover:bg-blush' }} rounded-xl transition-colors duration-200">
                    <span class="material-symbols-outlined">dashboard</span>
                    <span class="font-medium text-sm">Dashboard</span>
                </a>
            @endunless

            @if ($portalUser?->hasPermission('destinations.view'))
                <a href="{{ route('owner.destinations.index') }}" class="flex items-center gap-3 px-4 py-3 {{ request()->routeIs('owner.destinations.*') ? 'bg-primary/10 text-primary font-semibold' : 'text-slate-600 hover:bg-blush' }} rounded-xl transition-colors duration-200">
                    <span class="material-symbols-outlined">location_on</span>
                    <span class="font-medium text-sm">Destinasi Saya</span>
                </a>
            @endif

            @if ($portalUser?->hasPermission('packages.view'))
                <a href="{{ route('owner.tour-packages.index') }}" class="flex items-center gap-3 px-4 py-3 {{ request()->routeIs('owner.tour-packages.*') ? 'bg-primary/10 text-primary font-semibold' : 'text-slate-600 hover:bg-blush' }} rounded-xl transition-colors duration-200">
                    <span class="material-symbols-outlined">tour</span>
                    <span class="font-medium text-sm">Paket Wisata</span>
                </a>
            @endif

            @if ($portalUser?->hasPermission('promos.manage'))
                <a href="{{ route('owner.promos.index') }}" class="flex items-center gap-3 px-4 py-3 {{ request()->routeIs('owner.promos.*') ? 'bg-primary/10 text-primary font-semibold' : 'text-slate-600 hover:bg-blush' }} rounded-xl transition-colors duration-200">
                    <span class="material-symbols-outlined">sell</span>
                    <span class="font-medium text-sm">Promo &amp; Voucher</span>
                </a>
            @endif

            @if ($portalUser?->hasPermission('bookings.view'))
                <a href="{{ route('owner.bookings.index') }}" class="flex items-center gap-3 px-4 py-3 {{ request()->routeIs('owner.bookings.*') ? 'bg-primary/10 text-primary font-semibold' : 'text-slate-600 hover:bg-blush' }} rounded-xl transition-colors duration-200">
                    <span class="material-symbols-outlined">event</span>
                    <span class="font-medium text-sm">Booking</span>
                </a>
            @endif

            @if ($portalUser?->hasPermission('change_requests.decide'))
                <a href="{{ route('owner.change-requests.index') }}" class="flex items-center gap-3 px-4 py-3 {{ request()->routeIs('owner.change-requests.*') ? 'bg-primary/10 text-primary font-semibold' : 'text-slate-600 hover:bg-blush' }} rounded-xl transition-colors duration-200">
                    <span class="material-symbols-outlined">pending_actions</span>
                    <span class="font-medium text-sm">Pengajuan Pembatalan</span>
                </a>
            @endif

            @if ($portalUser?->hasPermission('payments.view'))
                <a href="{{ route('owner.payments.index') }}" class="flex items-center gap-3 px-4 py-3 {{ request()->routeIs('owner.payments.*') ? 'bg-primary/10 text-primary font-semibold' : 'text-slate-600 hover:bg-blush' }} rounded-xl transition-colors duration-200">
                    <span class="material-symbols-outlined">payments</span>
                    <span class="font-medium text-sm">Pembayaran</span>
                </a>
            @endif

            @if ($portalUser?->hasPermission('reports.checkin') || $portalUser?->hasPermission('reports.revenue'))
                <a href="{{ route('owner.laporan.index') }}" class="flex items-center gap-3 px-4 py-3 {{ request()->routeIs('owner.laporan.*') ? 'bg-primary/10 text-primary font-semibold' : 'text-slate-600 hover:bg-blush' }} rounded-xl transition-colors duration-200">
                    <span class="material-symbols-outlined">monitoring</span>
                    <span class="font-medium text-sm">Laporan</span>
                </a>
            @endif

            @if ($portalUser?->hasPermission('tickets.redeem'))
                <a href="{{ route('owner.scanner') }}" class="flex items-center gap-3 px-4 py-3 {{ request()->routeIs('owner.scanner') || request()->routeIs('owner.tickets.*') ? 'bg-primary/10 text-primary font-semibold' : 'text-slate-600 hover:bg-blush' }} rounded-xl transition-colors duration-200">
                    <span class="material-symbols-outlined">qr_code_scanner</span>
                    <span class="font-medium text-sm">Scanner Tiket</span>
                </a>
            @endif

            @if ($portalUser?->hasPermission('scanner.history'))
                <a href="{{ route('owner.scanner.history') }}" class="flex items-center gap-3 px-4 py-3 {{ request()->routeIs('owner.scanner.history') ? 'bg-primary/10 text-primary font-semibold' : 'text-slate-600 hover:bg-blush' }} rounded-xl transition-colors duration-200">
                    <span class="material-symbols-outlined">history</span>
                    <span class="font-medium text-sm">Riwayat Scan Saya</span>
                </a>
            @endif

            @if ($portalUser?->hasPermission('staff.manage'))
                <a href="{{ route('owner.staff.index') }}" class="flex items-center gap-3 px-4 py-3 {{ request()->routeIs('owner.staff.*') ? 'bg-primary/10 text-primary font-semibold' : 'text-slate-600 hover:bg-blush' }} rounded-xl transition-colors duration-200">
                    <span class="material-symbols-outlined">badge</span>
                    <span class="font-medium text-sm">Staf</span>
                </a>
            @endif
        </nav>

        <div class="p-6 border-t border-slate-100">
            <div class="flex items-center gap-3">
                <div class="flex-shrink-0 size-10 rounded-full bg-blush overflow-hidden">
                    <img src="{{ auth()->user()->photo ?? '/images/logos/travelo-logo-192.png' }}" alt="Profile" class="size-10">
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-ink truncate">{{ auth()->user()->name }}</p>
                    <p class="text-xs text-slate-500 truncate">{{ ucfirst(str_replace('_', ' ', auth()->user()->role)) }}</p>
                </div>
            </div>
            <form method="POST" action="{{ route('admin.logout') }}" class="mt-4">
                @csrf
                <button type="submit" class="w-full flex items-center gap-3 px-4 py-2.5 rounded-lg text-red-700 hover:bg-red-50 transition-colors">
                    <span class="material-symbols-outlined text-[18px]">logout</span>
                    <span class="font-medium text-sm">Keluar</span>
                </button>
            </form>
        </div>
    </aside>

    <!-- Main content -->
    <main class="flex-1 p-8 max-w-7xl mx-auto space-y-6">
        @if (session('success'))
            <div class="bg-green-50 border border-green-200 text-green-800 px-6 py-4 rounded-xl">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="bg-red-50 border border-red-200 text-red-800 px-6 py-4 rounded-xl">
                {{ session('error') }}
            </div>
        @endif

        @yield('content')
    </main>
</div>

<script>
    (function () {
        const sidebar = document.getElementById('owner-sidebar');
        const backdrop = document.getElementById('sidebar-backdrop');
        const closeButton = document.getElementById('sidebar-close');
        const body = document.getElementById('app-body');
        const darkToggle = document.getElementById('dark-mode-toggle');

        function setOpen(open) {
            if (!sidebar || !backdrop || !closeButton) return;
            sidebar.classList.toggle('-translate-x-full', !open);
            backdrop.classList.toggle('hidden', !open);
            closeButton.setAttribute('aria-expanded', open ? 'true' : 'false');
            document.body.classList.toggle('overflow-hidden', open);
        }

        closeButton?.addEventListener('click', function () { setOpen(false); });
        backdrop?.addEventListener('click', function () { setOpen(false); });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') setOpen(false);
        });

        function applyDarkMode(enabled) {
            if (enabled) {
                body.classList.add('dark');
                localStorage.setItem('travelo-dark-mode', '1');
            } else {
                body.classList.remove('dark');
                localStorage.setItem('travelo-dark-mode', '0');
            }
        }

        const saved = localStorage.getItem('travelo-dark-mode');
        if (saved !== null) {
            applyDarkMode(saved === '1');
        } else if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
            applyDarkMode(true);
        }

        darkToggle?.addEventListener('click', function () {
            applyDarkMode(!body.classList.contains('dark'));
        });
    })();
</script>

@stack('scripts')
</body>
</html>

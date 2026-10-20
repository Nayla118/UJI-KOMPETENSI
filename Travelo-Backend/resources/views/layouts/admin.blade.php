<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Travelo Admin')</title>

    <!-- Brand fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@600;700;800&family=Nunito+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">

    <!-- Compiled theme (Tailwind v4 + project tokens). No CDN. -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('styles')
</head>
<body class="bg-cream font-sans text-ink min-h-screen">
<div class="min-h-screen">
    <!-- Mobile backdrop -->
    <div id="sidebar-backdrop" class="fixed inset-0 bg-ink/30 z-40 hidden lg:hidden"></div>

    <!-- Sidebar -->
    <aside id="admin-sidebar" class="fixed inset-y-0 left-0 z-50 w-72 bg-white border-r border-slate-200 flex flex-col -translate-x-full lg:translate-x-0 transition-transform duration-200">
        <div class="p-6 flex items-center justify-between gap-3 border-b border-slate-100">
            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 group">
                <img src="/images/logos/travelo-logo-primary.svg" alt="Travelo" class="h-10 w-auto" width="150" height="40">
            </a>
            <button type="button" id="sidebar-close" class="lg:hidden inline-flex items-center justify-center size-10 rounded-xl text-muted hover:bg-blush" aria-label="Close navigation">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <nav class="flex-1 px-4 py-4 space-y-1 overflow-y-auto" aria-label="Admin navigation">
            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 px-4 py-3 {{ request()->routeIs('admin.dashboard') ? 'bg-primary/10 text-primary font-semibold' : 'text-slate-600 hover:bg-blush' }} rounded-xl transition-colors duration-200">
                <span class="material-symbols-outlined">dashboard</span>
                <span class="font-medium text-sm">Dashboard</span>
            </a>
            <a href="{{ route('admin.destinations.index') }}" class="flex items-center gap-3 px-4 py-3 {{ request()->routeIs('admin.destinations.*') ? 'bg-primary/10 text-primary font-semibold' : 'text-slate-600 hover:bg-blush' }} rounded-xl transition-colors duration-200">
                <span class="material-symbols-outlined">map</span>
                <span class="font-medium text-sm">Destinations</span>
            </a>
            <a href="{{ route('admin.tour-packages.index') }}" class="flex items-center gap-3 px-4 py-3 {{ request()->routeIs('admin.tour-packages.*') ? 'bg-primary/10 text-primary font-semibold' : 'text-slate-600 hover:bg-blush' }} rounded-xl transition-colors duration-200">
                <span class="material-symbols-outlined">inventory_2</span>
                <span class="font-medium text-sm">Tour Packages</span>
            </a>
            <a href="{{ route('admin.import.index') }}" class="flex items-center gap-3 px-4 py-3 {{ request()->routeIs('admin.import.*') ? 'bg-primary/10 text-primary font-semibold' : 'text-slate-600 hover:bg-blush' }} rounded-xl transition-colors duration-200">
                <span class="material-symbols-outlined">upload_file</span>
                <span class="font-medium text-sm">Import Data</span>
            </a>
            <a href="{{ route('admin.bookings.index') }}" class="flex items-center gap-3 px-4 py-3 {{ request()->routeIs('admin.bookings.*') ? 'bg-primary/10 text-primary font-semibold' : 'text-slate-600 hover:bg-blush' }} rounded-xl transition-colors duration-200">
                <span class="material-symbols-outlined">calendar_month</span>
                <span class="font-medium text-sm">Bookings</span>
            </a>
            <a href="{{ route('admin.payments.index') }}" class="flex items-center gap-3 px-4 py-3 {{ request()->routeIs('admin.payments.*') ? 'bg-primary/10 text-primary font-semibold' : 'text-slate-600 hover:bg-blush' }} rounded-xl transition-colors duration-200">
                <span class="material-symbols-outlined">payments</span>
                <span class="font-medium text-sm">Payments</span>
            </a>
            <a href="{{ route('admin.users.index') }}" class="flex items-center gap-3 px-4 py-3 {{ request()->routeIs('admin.users.*') ? 'bg-primary/10 text-primary font-semibold' : 'text-slate-600 hover:bg-blush' }} rounded-xl transition-colors duration-200">
                <span class="material-symbols-outlined">group</span>
                <span class="font-medium text-sm">Customers</span>
            </a>
            <div class="pt-4 mt-4 border-t border-slate-100">
                <a href="{{ route('admin.activity-logs.index') }}" class="flex items-center gap-3 px-4 py-3 {{ request()->routeIs('admin.activity-logs.*') ? 'bg-primary/10 text-primary font-semibold' : 'text-slate-600 hover:bg-blush' }} rounded-xl transition-colors duration-200">
                    <span class="material-symbols-outlined">history</span>
                    <span class="font-medium text-sm">Activity Logs</span>
                </a>
            </div>
        </nav>

        <div class="p-4 border-t border-slate-100 bg-cream">
            <div class="flex items-center gap-3 px-2 py-2">
                <div class="size-10 rounded-full bg-blush text-primary flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined">person</span>
                </div>
                <div class="flex-1 overflow-hidden">
                    <p class="text-sm font-semibold text-slate-900 truncate">{{ auth()->user()->name ?? 'Admin' }}</p>
                    <p class="text-xs text-slate-500 truncate">{{ auth()->user()->email ?? '' }}</p>
                </div>
            </div>
            <form method="POST" action="{{ route('admin.logout') }}" class="mt-2">
                @csrf
                <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl border border-slate-200 text-sm font-semibold text-slate-600 hover:bg-blush transition-colors">
                    <span class="material-symbols-outlined text-[18px]">logout</span>
                    Log out
                </button>
            </form>
        </div>
    </aside>

    <!-- Main Content -->
    <div class="lg:ml-72 min-h-screen flex flex-col">
        <header class="lg:hidden sticky top-0 z-30 flex items-center gap-3 bg-white border-b border-slate-200 px-4 py-3">
            <button type="button" id="sidebar-open" class="inline-flex items-center justify-center size-10 rounded-xl text-ink hover:bg-blush" aria-label="Open navigation" aria-controls="admin-sidebar" aria-expanded="false">
                <span class="material-symbols-outlined">menu</span>
            </button>
            <span class="font-display font-bold text-ink">Travelo Admin</span>
        </header>

        <main class="flex-1">
            @yield('content')
        </main>
    </div>
</div>

<script>
    (function () {
        const sidebar = document.getElementById('admin-sidebar');
        const backdrop = document.getElementById('sidebar-backdrop');
        const openButton = document.getElementById('sidebar-open');
        const closeButton = document.getElementById('sidebar-close');

        function setOpen(open) {
            if (!sidebar || !backdrop || !openButton) return;
            sidebar.classList.toggle('-translate-x-full', !open);
            backdrop.classList.toggle('hidden', !open);
            openButton.setAttribute('aria-expanded', open ? 'true' : 'false');
            document.body.classList.toggle('overflow-hidden', open);
        }

        openButton?.addEventListener('click', function () { setOpen(true); });
        closeButton?.addEventListener('click', function () { setOpen(false); });
        backdrop?.addEventListener('click', function () { setOpen(false); });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') setOpen(false);
        });
    })();
</script>

@stack('scripts')
</body>
</html>

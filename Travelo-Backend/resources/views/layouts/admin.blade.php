<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'TravelAdmin Dashboard')</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800,900&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>

    <!-- Tailwind CSS via CDN (Development) -->
    <!-- Note: For production, run: npm install && npm run build -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#2563eb',
                        'primary-dark': '#1d4ed8',
                        'primary-light': '#3b82f6',
                        'background-light': '#f8fafc',
                        success: '#10b981',
                        warning: '#f59e0b',
                        danger: '#ef4444',
                    },
                    fontFamily: {
                        sans: ['Inter', 'system-ui', 'sans-serif'],
                    },
                    borderRadius: {
                        sm: '0.375rem',
                        md: '0.5rem',
                        lg: '0.75rem',
                        xl: '1rem',
                        '2xl': '1.5rem',
                        full: '9999px',
                    },
                    boxShadow: {
                        sm: '0 1px 2px 0 rgb(0 0 0 / 0.05)',
                        md: '0 4px 6px -1px rgb(0 0 0 / 0.1)',
                        lg: '0 10px 15px -3px rgb(0 0 0 / 0.1)',
                        xl: '0 20px 25px -5px rgb(0 0 0 / 0.1)',
                    }
                },
            },
        }
    </script>

    <style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }
    </style>

    @stack('styles')
</head>
<body class="bg-background-light font-sans text-slate-900 min-h-screen">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <aside class="w-72 bg-white border-r border-slate-200 flex flex-col fixed h-full z-50">
            <div class="p-6 flex items-center gap-3 border-b border-slate-100">
                <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 group">
                    <img src="/images/logos/travelo-logo-primary.svg" alt="Travelo" class="h-10 w-auto" width="150" height="40">
                </a>
            </div>
            <nav class="flex-1 px-4 py-4 space-y-1 overflow-y-auto">
                <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 px-4 py-3 {{ request()->routeIs('admin.dashboard') ? 'bg-primary/10 text-primary font-semibold' : 'text-slate-600 hover:bg-slate-50' }} rounded-xl transition-all duration-200">
                    <span class="material-symbols-outlined">dashboard</span>
                    <span class="font-medium text-sm">Dashboard</span>
                </a>
                <a href="{{ route('admin.destinations.index') }}" class="flex items-center gap-3 px-4 py-3 {{ request()->routeIs('admin.destinations.*') ? 'bg-primary/10 text-primary font-semibold' : 'text-slate-600 hover:bg-slate-50' }} rounded-xl transition-all duration-200">
                    <span class="material-symbols-outlined">map</span>
                    <span class="font-medium text-sm">Destinations</span>
                </a>
                <a href="{{ route('admin.tour-packages.index') }}" class="flex items-center gap-3 px-4 py-3 {{ request()->routeIs('admin.tour-packages.*') ? 'bg-primary/10 text-primary font-semibold' : 'text-slate-600 hover:bg-slate-50' }} rounded-xl transition-all duration-200">
                    <span class="material-symbols-outlined">inventory_2</span>
                    <span class="font-medium text-sm">Tour Packages</span>
                </a>
                <a href="{{ route('admin.import.index') }}" class="flex items-center gap-3 px-4 py-3 {{ request()->routeIs('admin.import.*') ? 'bg-primary/10 text-primary font-semibold' : 'text-slate-600 hover:bg-slate-50' }} rounded-xl transition-all duration-200">
                    <span class="material-symbols-outlined">upload_file</span>
                    <span class="font-medium text-sm">Import Data</span>
                </a>
                <a href="{{ route('admin.bookings.index') }}" class="flex items-center gap-3 px-4 py-3 {{ request()->routeIs('admin.bookings.*') ? 'bg-primary/10 text-primary font-semibold' : 'text-slate-600 hover:bg-slate-50' }} rounded-xl transition-all duration-200">
                    <span class="material-symbols-outlined">calendar_month</span>
                    <span class="font-medium text-sm">Bookings</span>
                </a>
                <a href="{{ route('admin.payments.index') }}" class="flex items-center gap-3 px-4 py-3 {{ request()->routeIs('admin.payments.*') ? 'bg-primary/10 text-primary font-semibold' : 'text-slate-600 hover:bg-slate-50' }} rounded-xl transition-all duration-200">
                    <span class="material-symbols-outlined">payments</span>
                    <span class="font-medium text-sm">Payments</span>
                </a>
                <a href="{{ route('admin.users.index') }}" class="flex items-center gap-3 px-4 py-3 {{ request()->routeIs('admin.users.*') ? 'bg-primary/10 text-primary font-semibold' : 'text-slate-600 hover:bg-slate-50' }} rounded-xl transition-all duration-200">
                    <span class="material-symbols-outlined">group</span>
                    <span class="font-medium text-sm">Customers</span>
                </a>
                <div class="pt-4 mt-4 border-t border-slate-100">
                    <a href="{{ route('admin.activity-logs.index') }}" class="flex items-center gap-3 px-4 py-3 {{ request()->routeIs('admin.activity-logs.*') ? 'bg-primary/10 text-primary font-semibold' : 'text-slate-600 hover:bg-slate-50' }} rounded-xl transition-all duration-200">
                        <span class="material-symbols-outlined">history</span>
                        <span class="font-medium text-sm">Activity Logs</span>
                    </a>
                </div>
            </nav>
            <div class="p-4 border-t border-slate-100 bg-slate-50">
                <div class="flex items-center gap-3 px-2 py-2">
                    <div class="size-10 rounded-full bg-linear-to-br from-primary to-primary-dark flex items-center justify-center text-white shadow-md shrink-0">
                        <span class="material-symbols-outlined">person</span>
                    </div>
                    <div class="flex-1 overflow-hidden">
                        <p class="text-sm font-semibold text-slate-900">Admin</p>
                        <p class="text-xs text-slate-500">admin@travelo.com</p>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 ml-72 transition-all duration-300">
            @yield('content')
        </main>
    </div>

    @stack('scripts')
</body>
</html>

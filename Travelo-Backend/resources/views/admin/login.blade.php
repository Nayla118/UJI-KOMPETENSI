<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin sign in | Travelo</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@600;700;800&family=Nunito+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Compiled theme (Tailwind v4 + project tokens). No CDN. -->
    @vite('resources/css/app.css')
</head>
<body class="min-h-screen bg-cream font-sans text-ink flex items-center justify-center px-4 py-10">
    <div class="w-full max-w-md">
        <div class="text-center mb-8">
            <img src="/images/logos/travelo-logo-primary.svg" alt="Travelo" class="h-12 w-auto mx-auto" width="192" height="48">
            <p class="text-sm font-medium text-muted mt-3">Admin console</p>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 sm:p-8">
            <h1 class="text-xl font-bold text-ink">Sign in</h1>
            <p class="text-sm text-muted mt-1 mb-6">Use your Travelo admin account.</p>

            @if ($errors->any())
                <div role="alert" aria-live="polite" class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl mb-5 text-sm">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('admin.login') }}" class="space-y-5">
                @csrf

                <div>
                    <label for="email" class="block text-sm font-semibold text-slate-700 mb-1.5">Email</label>
                    <input
                        type="email"
                        name="email"
                        id="email"
                        value="{{ old('email') }}"
                        class="w-full min-h-[48px] px-4 py-3 bg-white border border-slate-200 rounded-xl text-ink placeholder:text-slate-400 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors outline-none"
                        placeholder="admin@travelo.com"
                        autocomplete="username"
                        inputmode="email"
                        required
                        autofocus
                    >
                </div>

                <div>
                    <label for="password" class="block text-sm font-semibold text-slate-700 mb-1.5">Password</label>
                    <input
                        type="password"
                        name="password"
                        id="password"
                        class="w-full min-h-[48px] px-4 py-3 bg-white border border-slate-200 rounded-xl text-ink placeholder:text-slate-400 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors outline-none"
                        placeholder="Enter your password"
                        autocomplete="current-password"
                        required
                    >
                </div>

                <label class="flex items-center gap-3 min-h-[48px] cursor-pointer">
                    <input
                        type="checkbox"
                        name="remember"
                        class="h-5 w-5 rounded border-slate-300 text-primary focus:ring-primary"
                    >
                    <span class="text-sm text-slate-600">Remember me on this device</span>
                </label>

                <button
                    type="submit"
                    class="w-full min-h-[48px] bg-primary hover:bg-primary-dark text-white py-3 rounded-xl font-bold transition-colors shadow-sm"
                >
                    Sign in
                </button>
            </form>
        </div>

        <p class="text-center text-xs text-slate-500 mt-8">&copy; {{ date('Y') }} Travelo. All rights reserved.</p>
    </div>
</body>
</html>

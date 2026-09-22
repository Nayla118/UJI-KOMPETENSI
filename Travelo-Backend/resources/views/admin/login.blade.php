<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Travel Admin</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800,900&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
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
                    },
                    fontFamily: {
                        sans: ['Inter', 'system-ui', 'sans-serif'],
                    },
                },
            },
        }
    </script>
</head>
<body class="bg-background-light font-sans text-slate-900 min-h-screen flex items-center justify-center">
    <div class="w-full max-w-md px-4">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-slate-900">Travelo</h1>
            <p class="text-slate-500 mt-2">Admin Dashboard</p>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-8">
            <h2 class="text-xl font-semibold mb-6">Sign In</h2>

            @if ($errors->any())
                <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl mb-4 text-sm">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('admin.login') }}" class="space-y-5">
                @csrf
                <div>
                    <label for="email" class="block text-sm font-medium text-slate-700 mb-1.5">Email</label>
                    <input type="email" name="email" id="email" value="{{ old('email') }}"
                        class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none"
                        placeholder="admin@travelo.com" required autofocus>
                </div>
                <div>
                    <label for="password" class="block text-sm font-medium text-slate-700 mb-1.5">Password</label>
                    <input type="password" name="password" id="password"
                        class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none"
                        placeholder="••••••••" required>
                </div>
                <div class="flex items-center justify-between">
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="remember" class="rounded border-slate-300 text-primary focus:ring-primary h-4 w-4">
                        <span class="text-sm text-slate-600">Remember me</span>
                    </label>
                </div>
                <button type="submit"
                    class="w-full bg-primary hover:bg-primary-dark text-white py-2.5 rounded-xl font-semibold transition-all shadow-sm">
                    Sign In
                </button>
            </form>
        </div>

        <p class="text-center text-xs text-slate-400 mt-8">&copy; {{ date('Y') }} Travelo. All rights reserved.</p>
    </div>
</body>
</html>

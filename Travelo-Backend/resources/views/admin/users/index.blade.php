@extends('layouts.admin')

@section('title', 'User Management - TravelAdmin')

<!-- Header -->
@section('content')
<!-- Top Header -->
<header class="h-16 border-b border-slate-200 bg-white flex items-center justify-between px-8 sticky top-0 z-10">
    <h2 class="text-lg font-bold text-slate-900">User Management</h2>
    <div class="flex items-center gap-4">
        <button class="size-10 flex items-center justify-center rounded-lg bg-slate-100 text-slate-600 hover:text-primary">
            <span class="material-symbols-outlined">notifications</span>
        </button>
    </div>
</header>

<!-- Body -->
<div class="p-8 space-y-6 max-w-7xl mx-auto w-full">
    <!-- Summary Header -->
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div class="space-y-1">
            <h1 class="text-3xl font-black tracking-tight text-slate-900">Registered Travelers</h1>
            <p class="text-slate-500">View and manage all user accounts across your travel platform.</p>
        </div>
    </div>

    <!-- Controls & Filters -->
    <div class="bg-white p-4 rounded-xl border border-slate-200 flex flex-wrap gap-4 items-center">
        <form method="GET" action="{{ route('admin.users.index') }}" class="flex-1 min-w-[300px] relative">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">search</span>
            <input
                name="search"
                value="{{ request('search') }}"
                class="w-full pl-10 pr-4 py-2.5 bg-slate-100 border-none rounded-lg focus:ring-2 focus:ring-primary text-sm text-slate-900"
                placeholder="Search by name, email, or UID..."
                type="text"
            />
        </form>
    </div>

    <!-- Data Table -->
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200">
                        <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500">Traveler</th>
                        <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500">Firebase UID</th>
                        <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500">Status</th>
                        <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500">Join Date</th>
                        <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($users as $user)
                    <tr class="hover:bg-slate-50/50 transition-colors">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="size-10 rounded-full overflow-hidden bg-slate-200 border-2 border-white">
                                    @if($user->photo)
                                    <img alt="{{ $user->name }}" class="w-full h-full object-cover" src="{{ $user->photo }}"/>
                                    @else
                                    <div class="w-full h-full flex items-center justify-center text-sm font-bold text-slate-500">
                                        {{ $user->name ? strtoupper(substr($user->name, 0, 2)) : 'U' }}
                                    </div>
                                    @endif
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-slate-900">{{ $user->name }}</p>
                                    <p class="text-xs text-slate-500">{{ $user->email }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <code class="text-xs font-mono bg-slate-100 px-2 py-1 rounded text-slate-600">
                                {{ $user->firebase_uid }}
                            </code>
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700">
                                <span class="size-1.5 rounded-full bg-emerald-500"></span> Active
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-slate-600">
                            {{ $user->created_at->format('M d, Y') }}
                        </td>
                        <td class="px-6 py-4 text-right">
                            <a href="{{ route('admin.users.show', $user->id) }}" class="text-slate-400 hover:text-primary transition-colors inline-block">
                                <span class="material-symbols-outlined">more_vert</span>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-slate-400">
                            No users found
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <!-- Pagination -->
        <div class="px-6 py-4 bg-slate-50 flex items-center justify-between">
            <p class="text-xs text-slate-500">Showing {{ $users->firstItem() ?? 0 }} to {{ $users->lastItem() ?? 0 }} of {{ $users->total() }} users</p>
            <div class="flex gap-2">
                @if($users->previousPageUrl())
                <a href="{{ $users->previousPageUrl() }}" class="px-3 py-1 bg-white border border-slate-200 rounded text-sm text-slate-600 hover:bg-slate-50 transition-colors">
                    Previous
                </a>
                @else
                <button class="px-3 py-1 bg-white border border-slate-200 rounded text-sm text-slate-600 opacity-50" disabled>
                    Previous
                </button>
                @endif

                @if($users->nextPageUrl())
                <a href="{{ $users->nextPageUrl() }}" class="px-3 py-1 bg-white border border-slate-200 rounded text-sm text-slate-600 hover:bg-slate-50 transition-colors">
                    Next
                </a>
                @else
                <button class="px-3 py-1 bg-white border border-slate-200 rounded text-sm text-slate-600 opacity-50" disabled>
                    Next
                </button>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

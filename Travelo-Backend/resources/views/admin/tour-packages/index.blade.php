@extends('layouts.admin')

@section('title', 'Tour Packages Management')

<!-- Header -->
@section('content')
<!-- Header -->
<header class="h-16 bg-white border-b border-slate-200 flex items-center justify-between px-8">
    <div class="flex items-center gap-4 text-slate-500">
        <span class="material-symbols-outlined">search</span>
        <input class="bg-transparent border-none focus:ring-0 text-sm w-64" placeholder="Search for something..." type="text"/>
    </div>
    <div class="flex items-center gap-4">
        <button class="relative p-2 text-slate-500 hover:bg-slate-100 rounded-full transition-colors">
            <span class="material-symbols-outlined">notifications</span>
            <span class="absolute top-2 right-2 w-2 h-2 bg-red-500 rounded-full"></span>
        </button>
        <button class="p-2 text-slate-500 hover:bg-slate-100 rounded-full transition-colors">
            <span class="material-symbols-outlined">settings</span>
        </button>
    </div>
</header>

<!-- Page Body -->
<div class="flex-1 overflow-y-auto p-8">
    <div class="max-w-7xl mx-auto space-y-6">
        <!-- Title & Actions -->
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

    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h2 class="text-3xl font-black text-slate-900 tracking-tight">Tour Packages</h2>
                <p class="text-slate-500 mt-1">Manage your tour offerings and pricing</p>
            </div>
            <a href="{{ route('admin.tour-packages.create') }}" class="inline-flex items-center gap-2 bg-primary hover:bg-primary/90 text-white px-5 py-2.5 rounded-lg font-bold text-sm transition-all shadow-lg shadow-primary/20">
                <span class="material-symbols-outlined text-base">add</span>
                Add New Package
            </a>
        </div>

        <!-- Filters -->
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex flex-col md:flex-row gap-4">
            <form method="GET" action="{{ route('admin.tour-packages.index') }}" class="flex-1 relative">
                @if(request('destination'))
                    <input type="hidden" name="destination" value="{{ request('destination') }}">
                @endif
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">search</span>
                <input
                    name="search"
                    value="{{ request('search') }}"
                    class="w-full pl-10 pr-4 py-2 bg-slate-50 border-slate-200 rounded-lg text-sm focus:ring-primary focus:border-primary transition-all"
                    placeholder="Search packages by title or destination..."
                    type="text"
                />
            </form>
            <form method="GET" action="{{ route('admin.tour-packages.index') }}" class="w-full md:w-64 relative">
                @if(request('search'))
                    <input type="hidden" name="search" value="{{ request('search') }}">
                @endif
                <select
                    name="destination"
                    onchange="this.form.submit()"
                    class="w-full pl-3 pr-10 py-2 bg-slate-50 border-slate-200 rounded-lg text-sm appearance-none focus:ring-primary focus:border-primary transition-all"
                >
                    <option value="">Filter by Destination</option>
                    @foreach($destinations as $destination)
                    <option value="{{ $destination->id }}" {{ request('destination') == $destination->id ? 'selected' : '' }}>
                        {{ $destination->city }}, {{ $destination->country }}
                    </option>
                    @endforeach
                </select>
                <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">expand_more</span>
            </form>
        </div>

        <!-- Data Table -->
<form action="{{ route('admin.tour-packages.destroy-all') }}" method="POST" id="bulk-delete-form">
            @csrf
            @method('DELETE')
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-6 bg-slate-50 border-b border-slate-200 flex justify-between items-center">
                    <span class="text-sm font-medium text-slate-600">Bulk actions</span>
                    <button type="submit" class="bg-red-500 hover:bg-red-600 text-white px-4 py-1.5 rounded-lg text-sm font-medium transition-all disabled:opacity-50 disabled:cursor-not-allowed shadow-sm inline-flex items-center gap-1" id="bulk-delete-btn" disabled>
                        <span class="material-symbols-outlined text-sm">delete</span>
                        <span>Hapus Terpilih</span>
                    </button>
                </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
    <tr class="bg-slate-50 border-b border-slate-200">
                            <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider w-12">
                                <input type="checkbox" id="select-all" class="rounded border-slate-300 text-primary focus:ring-primary h-4 w-4">
                            </th>
                            <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Package</th>
                            <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Destination</th>
                            <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Price</th>
                            <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Duration</th>
                            <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($tourPackages as $package)
                        <tr class="hover:bg-slate-50 transition-colors group">
                            <td class="px-6 py-4">
                                <input type="checkbox" class="row-checkbox rounded border-slate-300 text-primary focus:ring-primary h-4 w-4" name="ids[]" value="{{ $package->id }}">
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-4">
                                    <x-image-with-fallback
                                        :src="$package->image"
                                        :alt="$package->title"
                                        type="tour-package"
                                        size="medium"
                                    />
                                    <div>
                                        <a href="{{ route('admin.tour-packages.show', $package->id) }}" class="font-bold text-slate-900 hover:text-primary transition-colors">{{ $package->title }}</a>
                                        <p class="text-xs text-slate-500">{{ $package->max_people }} people max</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <span class="material-symbols-outlined text-slate-400 text-base">location_on</span>
                                    <span class="text-sm font-medium">{{ $package->destination->city }}, {{ $package->destination->country }}</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm font-bold text-primary">${{ number_format($package->price, 2) }}</td>
                            <td class="px-6 py-4">
                                <span class="px-2.5 py-1 rounded-full bg-slate-100 text-xs font-semibold text-slate-600">{{ $package->duration_days }} Days</span>
                            </td>
                            <td class="px-6 py-4 text-right space-x-2">
                                <a href="{{ route('admin.tour-packages.edit', $package->id) }}" class="p-2 hover:bg-primary/10 hover:text-primary rounded-lg transition-colors text-slate-400 inline-block">
                                    <span class="material-symbols-outlined text-xl">edit</span>
                                </a>
                                <form action="{{ route('admin.tour-packages.destroy', $package->id) }}" method="POST" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-2 hover:bg-red-50 hover:text-red-500 rounded-lg transition-colors text-slate-400" onclick="return confirm('Are you sure you want to delete this package?')">
                                        <span class="material-symbols-outlined text-xl">delete</span>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-slate-400">
                                No tour packages found
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        </div>
        </form>

            <!-- Pagination -->
            <div class="px-6 py-4 bg-slate-50 border-t border-slate-200 flex items-center justify-between">
                <p class="text-sm text-slate-500">Showing <span class="font-semibold text-slate-900">{{ $tourPackages->firstItem() ?? 0 }}</span> to <span class="font-semibold text-slate-900">{{ $tourPackages->lastItem() ?? 0 }}</span> of <span class="font-semibold text-slate-900">{{ $tourPackages->total() }}</span> packages</p>
                <div class="flex items-center gap-2">
                    @if($tourPackages->previousPageUrl())
                    <a href="{{ $tourPackages->previousPageUrl() }}" class="p-2 rounded-lg border border-slate-200 hover:bg-white transition-all text-slate-400">
                        <span class="material-symbols-outlined text-lg">chevron_left</span>
                    </a>
                    @else
                    <button class="p-2 rounded-lg border border-slate-200 hover:bg-white transition-all text-slate-400 disabled:opacity-50" disabled>
                        <span class="material-symbols-outlined text-lg">chevron_left</span>
                    </button>
                    @endif

                    @foreach($tourPackages->getUrlRange(1, $tourPackages->lastPage()) as $page => $url)
                        @if($page == $tourPackages->currentPage())
                        <button class="w-8 h-8 rounded-lg bg-primary text-white text-sm font-bold flex items-center justify-center shadow-sm">{{ $page }}</button>
                        @else
                        <a href="{{ $url }}" class="w-8 h-8 rounded-lg border border-slate-200 hover:bg-white text-sm font-medium flex items-center justify-center transition-all">{{ $page }}</a>
                        @endif
                    @endforeach

                    @if($tourPackages->nextPageUrl())
                    <a href="{{ $tourPackages->nextPageUrl() }}" class="p-2 rounded-lg border border-slate-200 hover:bg-white transition-all text-slate-600">
                        <span class="material-symbols-outlined text-lg">chevron_right</span>
                    </a>
                    @else
                    <button class="p-2 rounded-lg border border-slate-200 hover:bg-white transition-all text-slate-600 disabled:opacity-50" disabled>
                        <span class="material-symbols-outlined text-lg">chevron_right</span>
                    </button>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const selectAllCheckbox = document.getElementById('select-all');
            const rowCheckboxes = document.querySelectorAll('.row-checkbox');
            const bulkDeleteBtn = document.getElementById('bulk-delete-btn');
            const bulkDeleteForm = document.getElementById('bulk-delete-form');

            // Select all
            selectAllCheckbox.addEventListener('change', function() {
                rowCheckboxes.forEach(checkbox => checkbox.checked = this.checked);
                updateButton();
            });

            // Individual checkboxes
            rowCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    updateSelectAll();
                    updateButton();
                });
            });

            function updateSelectAll() {
                const checked = Array.from(rowCheckboxes).filter(cb => cb.checked).length;
                const total = rowCheckboxes.length;
                selectAllCheckbox.indeterminate = checked > 0 && checked < total;
                selectAllCheckbox.checked = checked === total;
            }

            function updateButton() {
                const checkedCount = Array.from(rowCheckboxes).filter(cb => cb.checked).length;
                bulkDeleteBtn.disabled = checkedCount === 0;
                if (checkedCount > 0) {
                    bulkDeleteBtn.querySelector('span:last-child').textContent = `Hapus Terpilih (${checkedCount})`;
                } else {
                    bulkDeleteBtn.querySelector('span:last-child').textContent = 'Hapus Terpilih';
                }
            }

            // Form submission confirmation
            bulkDeleteForm.addEventListener('submit', function(e) {
                const checkedCount = Array.from(rowCheckboxes).filter(cb => cb.checked).length;
                if (checkedCount === 0) {
                    e.preventDefault();
                    alert('Pilih minimal satu package untuk dihapus.');
                    return false;
                }
                if (!confirm(`Yakin hapus ${checkedCount} package terpilih?`)) {
                    e.preventDefault();
                    return false;
                }
            });
        });
    </script>
@endsection


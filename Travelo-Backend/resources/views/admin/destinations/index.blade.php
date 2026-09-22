@extends('layouts.admin')

@section('title', 'Destinations Management | Travel Admin')

<!-- Header -->
@section('content')
<header class="bg-white/80 backdrop-blur-md sticky top-0 z-20 border-b border-slate-200 px-8 py-4">
    <div class="flex items-center justify-between max-w-7xl mx-auto">
        <div>
            <h2 class="text-2xl font-bold tracking-tight">Destinations</h2>
            <p class="text-sm text-slate-500">Manage and update global travel spots</p>
        </div>
        <a href="{{ route('admin.destinations.create') }}" class="bg-primary hover:bg-primary/90 text-white px-5 py-2.5 rounded-lg font-semibold flex items-center gap-2 transition-all shadow-sm">
            <span class="material-symbols-outlined text-[20px]">add</span>
            Add New
        </a>
    </div>
</header>

<div class="p-8 max-w-7xl mx-auto space-y-6">
    <!-- Filters & Search -->
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

    <div class="flex flex-col md:flex-row gap-4 items-center justify-between">
        <form method="GET" action="{{ route('admin.destinations.index') }}" class="relative w-full md:w-96">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">search</span>
            <input
                name="search"
                value="{{ request('search') }}"
                class="w-full pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none"
                placeholder="Search destinations, cities, or countries..."
                type="text"
            />
        </form>
        <div class="flex items-center gap-3 w-full md:w-auto">
            <form method="GET" action="{{ route('admin.destinations.index') }}" class="flex-1 md:w-48">
                @if(request('search'))
                    <input type="hidden" name="search" value="{{ request('search') }}">
                @endif
                <select
                    name="country"
                    onchange="this.form.submit()"
                    class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none text-slate-600"
                >
                    <option value="">Filter by Country</option>
                    @foreach($countries as $country)
                    <option value="{{ $country }}" {{ request('country') == $country ? 'selected' : '' }}>{{ $country }}</option>
                    @endforeach
                </select>
            </form>
        </div>
    </div>

    <!-- Data Table -->
    <form action="{{ route('admin.destinations.destroy-all') }}" method="POST" id="bulk-delete-form">
        @csrf
        @method('DELETE')
        <div class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm">
            <!-- Bulk Actions Header -->
            <div class="p-4 bg-slate-50 border-b border-slate-200 flex justify-between items-center">
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
                            <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500 w-12">
                                <input type="checkbox" id="select-all" class="rounded border-slate-300 text-primary focus:ring-primary h-4 w-4">
                            </th>
                            <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500">Image</th>
                            <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500">Destination Name</th>
                            <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500">City</th>
                            <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500">Country</th>
                            <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500">Rating</th>
                            <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($destinations as $destination)
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-6 py-4">
                                <input type="checkbox" class="row-checkbox rounded border-slate-300 text-primary focus:ring-primary h-4 w-4" name="ids[]" value="{{ $destination->id }}">
                            </td>
                            <td class="px-6 py-4">
                                <x-image-with-fallback
                                    :src="$destination->image"
                                    :alt="$destination->name"
                                    type="destination"
                                    size="small"
                                />
                            </td>
                            <td class="px-6 py-4">
                                <a href="{{ route('admin.destinations.show', $destination->id) }}" class="font-semibold text-slate-800 hover:text-primary transition-colors">{{ $destination->name }}</a>
                            </td>
                            <td class="px-6 py-4 text-slate-600">{{ $destination->city }}</td>
                            <td class="px-6 py-4 text-slate-600">{{ $destination->country }}</td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-amber-400 text-[18px] fill-current">star</span>
                                    <span class="text-sm font-medium">{{ number_format($destination->rating, 1) }}</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('admin.destinations.edit', $destination->id) }}" class="p-2 text-slate-400 hover:text-primary transition-colors">
                                        <span class="material-symbols-outlined">edit</span>
                                    </a>
                                    <form action="{{ route('admin.destinations.destroy', $destination->id) }}" method="POST" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="p-2 text-slate-400 hover:text-red-500 transition-colors" onclick="return confirm('Are you sure you want to delete this destination?')">
                                            <span class="material-symbols-outlined">delete</span>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="px-6 py-8 text-center text-slate-400">
                                No destinations found
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <!-- Pagination -->
            <div class="px-6 py-4 bg-slate-50 border-t border-slate-200 flex items-center justify-between">
                <div class="text-sm text-slate-500">
                    Showing <span class="font-semibold">{{ $destinations->firstItem() ?? 0 }}</span> to <span class="font-semibold">{{ $destinations->lastItem() ?? 0 }}</span> of <span class="font-semibold">{{ $destinations->total() }}</span> results
                </div>
                <div class="flex items-center gap-2">
                    @if($destinations->previousPageUrl())
                    <a href="{{ $destinations->previousPageUrl() }}" class="px-3 py-1.5 border border-slate-200 rounded-lg text-sm font-medium hover:bg-white transition-all">
                        Previous
                    </a>
                    @else
                    <button class="px-3 py-1.5 border border-slate-200 rounded-lg text-sm font-medium opacity-50 cursor-not-allowed" disabled>
                        Previous
                    </button>
                    @endif

                    <div class="flex items-center gap-1">
                        @foreach($destinations->getUrlRange(1, $destinations->lastPage()) as $page => $url)
                            @if($page == $destinations->currentPage())
                            <button class="size-8 flex items-center justify-center bg-primary text-white rounded-lg text-sm font-bold">{{ $page }}</button>
                            @else
                            <a href="{{ $url }}" class="size-8 flex items-center justify-center hover:bg-slate-200 rounded-lg text-sm font-medium transition-colors">{{ $page }}</a>
                            @endif
                        @endforeach
                    </div>

                    @if($destinations->nextPageUrl())
                    <a href="{{ $destinations->nextPageUrl() }}" class="px-3 py-1.5 border border-slate-200 rounded-lg text-sm font-medium hover:bg-white transition-all">
                        Next
                    </a>
                    @else
                    <button class="px-3 py-1.5 border border-slate-200 rounded-lg text-sm font-medium opacity-50 cursor-not-allowed" disabled>
                        Next
                    </button>
                    @endif
                </div>
            </div>
        </div>
    </form>
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
                alert('Pilih minimal satu destination untuk dihapus.');
                return false;
            }
            if (!confirm(`Yakin hapus ${checkedCount} destination terpilih?`)) {
                e.preventDefault();
                return false;
            }
        });
    });
</script>
@endsection

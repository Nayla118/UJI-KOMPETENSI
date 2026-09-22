@extends('layouts.admin')

@section('title', 'Preview Tour Packages Import - Travelo Admin')
@section('header', 'Preview Tour Packages Import')

@section('content')
<div class="space-y-6">
    <!-- Back Link -->
    <a href="{{ route('admin.import.tour-packages') }}" class="inline-flex items-center gap-2 text-primary hover:text-blue-700">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
        Back to Upload
    </a>

    <!-- Results Summary -->
    @if($results['success'])
    <div class="bg-green-50 border border-green-100 rounded-xl p-6">
        <h3 class="text-lg font-semibold text-green-800 mb-2">Preview Results</h3>
        <p class="text-green-700">Total rows found: {{ $results['total_rows'] }}</p>
    </div>
    @else
    <div class="bg-red-50 border border-red-100 rounded-xl p-6">
        <h3 class="text-lg font-semibold text-red-800 mb-2">Validation Errors</h3>
        <ul class="list-disc list-inside text-sm text-red-700 space-y-1">
            @foreach($results['errors'] as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <!-- Preview Data -->
    @if($results['success'] && count($results['preview_data']) > 0)
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Preview Data (First 10 rows)</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50">
                        <th class="px-4 py-2 text-left font-semibold">#</th>
                        @foreach(array_keys($results['preview_data'][0]) as $header)
                        <th class="px-4 py-2 text-left font-semibold">{{ $header }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($results['preview_data'] as $index => $row)
                    <tr class="border-b border-gray-50">
                        <td class="px-4 py-2 text-gray-500">{{ $index + 1 }}</td>
                        @foreach($row as $value)
                        <td class="px-4 py-2">{{ $value ?? '-' }}</td>
                        @endforeach
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Import Form -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Confirm Import</h3>
        <p class="text-gray-600 mb-4">Ready to import {{ $results['total_rows'] }} tour packages. Click the button below to proceed with the import.</p>

        <form action="{{ route('admin.import.tour-packages.process') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="temp_file_path" value="{{ $temp_file_path }}">

            <div class="flex gap-3">
                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-6 py-2.5 rounded-lg font-semibold transition-colors">
                    Confirm & Import
                </button>
                <a href="{{ route('admin.import.tour-packages') }}" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-2.5 rounded-lg font-semibold transition-colors">
                    Cancel
                </a>
            </div>
        </form>
    </div>
    @endif
</div>
@endsection

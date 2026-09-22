@extends('layouts.admin')

@section('title', 'Import Tour Packages - Travelo Admin')
@section('header', 'Import Tour Packages')

@section('content')
<div class="space-y-6">
    <!-- Back Link -->
    <a href="{{ route('admin.import.index') }}" class="inline-flex items-center gap-2 text-primary hover:text-blue-700">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
        Back to Import
    </a>

    <!-- Instructions -->
    <div class="bg-green-50 border border-green-100 rounded-xl p-6">
        <h3 class="text-lg font-semibold text-green-800 mb-2">CSV Format Requirements</h3>
        <ul class="list-disc list-inside text-sm text-green-700 space-y-1">
            <li><strong>destination_id</strong> (required) - ID of the destination (must exist in database)</li>
            <li><strong>title</strong> (required) - Package title</li>
            <li><strong>price</strong> (required) - Package price (numeric)</li>
            <li><strong>duration_days</strong> (required) - Duration in days (integer)</li>
            <li><strong>max_people</strong> (required) - Maximum number of people (integer)</li>
            <li><strong>description</strong> (optional) - Package description</li>
            <li><strong>image</strong> (optional) - Image URL or path</li>
            <li><strong>rating</strong> (optional) - Rating between 0-5</li>
        </ul>
    </div>

    <!-- Available Destinations -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Available Destination IDs</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100">
                        <th class="px-4 py-2 text-left font-semibold">ID</th>
                        <th class="px-4 py-2 text-left font-semibold">Name</th>
                        <th class="px-4 py-2 text-left font-semibold">City</th>
                        <th class="px-4 py-2 text-left font-semibold">Country</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($template['available_destinations'] as $dest)
                    <tr class="border-b border-gray-50">
                        <td class="px-4 py-2">{{ $dest['id'] }}</td>
                        <td class="px-4 py-2">{{ $dest['name'] }}</td>
                        <td class="px-4 py-2">{{ $dest['city'] }}</td>
                        <td class="px-4 py-2">{{ $dest['country'] }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="px-4 py-4 text-center text-gray-500">
                            No destinations available. Please create destinations first.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Upload Form -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Upload CSV File</h3>

        <form action="{{ route('admin.import.preview-tour-packages') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
            @csrf

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">CSV File</label>
                <input type="file" name="file" accept=".csv,.txt" required
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary"/>
                <p class="text-sm text-gray-500 mt-1">Maximum file size: 5MB</p>
            </div>

            <div class="flex gap-3">
                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-6 py-2.5 rounded-lg font-semibold transition-colors">
                    Preview Import
                </button>
                <a href="{{ route('admin.tour-packages.index') }}" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-2.5 rounded-lg font-semibold transition-colors">
                    Cancel
                </a>
            </div>
        </form>
    </div>

    <!-- Sample Template -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Sample CSV Template</h3>
        <div class="bg-gray-50 rounded-lg p-4 overflow-x-auto">
            <code class="text-sm text-gray-700">
{{ implode(',', $template['headers']) }}<br/>
{{ implode(',', array_values($template['sample_data'][0])) }}
            </code>
        </div>
        <p class="text-sm text-gray-500 mt-3">
            Copy the above to create your CSV file. Use the destination IDs from the table above.
        </p>
        <div class="mt-4">
            <a href="{{ route('admin.import.download-template', 'tour-packages') }}" class="inline-flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-semibold transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                Download Template
            </a>
        </div>
    </div>
</div>
@endsection

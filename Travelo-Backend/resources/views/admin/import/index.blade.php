@extends('layouts.admin')

@section('title', 'Import Data - Travelo Admin')
@section('header', 'Import Data')

@section('content')
<div class="space-y-6">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Destinations Import Card -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <div class="flex items-center gap-4 mb-4">
                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-800">Destinations</h3>
                    <p class="text-sm text-gray-500">Import destinations from CSV file</p>
                </div>
            </div>
            <p class="text-gray-600 text-sm mb-4">
                Import multiple destinations at once using a CSV file. The file should contain columns: name, city, country (required), description, image, rating, is_popular.
            </p>
            <a href="{{ route('admin.import.destinations') }}" class="inline-flex items-center gap-2 bg-primary hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                </svg>
                Import Destinations
            </a>
        </div>

        <!-- Tour Packages Import Card -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <div class="flex items-center gap-4 mb-4">
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-800">Tour Packages</h3>
                    <p class="text-sm text-gray-500">Import tour packages from CSV file</p>
                </div>
            </div>
            <p class="text-gray-600 text-sm mb-4">
                Import multiple tour packages at once using a CSV file. The file should contain columns: destination_id, title, price, duration_days, max_people (required).
            </p>
            <a href="{{ route('admin.import.tour-packages') }}" class="inline-flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                </svg>
                Import Tour Packages
            </a>
        </div>
    </div>

    <!-- Instructions -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">CSV Import Instructions</h3>
        <div class="space-y-4">
            <div class="flex items-start gap-3">
                <div class="w-6 h-6 bg-primary text-white rounded-full flex items-center justify-center text-sm font-bold flex-shrink-0">1</div>
                <div>
                    <h4 class="font-medium text-gray-800">Prepare your CSV file</h4>
                    <p class="text-sm text-gray-500">Create a CSV file with the required columns. Use the template provided in each import section.</p>
                </div>
            </div>
            <div class="flex items-start gap-3">
                <div class="w-6 h-6 bg-primary text-white rounded-full flex items-center justify-center text-sm font-bold flex-shrink-0">2</div>
                <div>
                    <h4 class="font-medium text-gray-800">Preview before importing</h4>
                    <p class="text-sm text-gray-500">Use the preview feature to validate your data before the actual import. This helps identify errors early.</p>
                </div>
            </div>
            <div class="flex items-start gap-3">
                <div class="w-6 h-6 bg-primary text-white rounded-full flex items-center justify-center text-sm font-bold flex-shrink-0">3</div>
                <div>
                    <h4 class="font-medium text-gray-800">Import process</h4>
                    <p class="text-sm text-gray-500">The system will detect duplicates based on unique identifiers (name+city+country for destinations, title+destination_id for packages) and update existing records.</p>
                </div>
            </div>
            <div class="flex items-start gap-3">
                <div class="w-6 h-6 bg-primary text-white rounded-full flex items-center justify-center text-sm font-bold flex-shrink-0">4</div>
                <div>
                    <h4 class="font-medium text-gray-800">Error handling</h4>
                    <p class="text-sm text-gray-500">Any rows with invalid data will be skipped and listed in the error messages. Valid rows will still be imported.</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

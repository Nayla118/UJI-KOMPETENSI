@extends('layouts.admin')

@section('title', 'Activity Logs - Travelo Admin')
@section('header', 'Activity Logs')

@section('content')
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <!-- Filters -->
    <div class="p-4 border-b border-gray-100">
        <form method="GET" action="{{ route('admin.activity-logs.index') }}" class="flex flex-wrap gap-2">
            <input
                type="text"
                name="search"
                value="{{ $search }}"
                placeholder="Search activities..."
                class="flex-1 min-w-48 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
            >
            <select name="log_name" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                <option value="">All Types</option>
                @foreach($logNames as $name)
                    <option value="{{ $name }}" {{ $logName == $name ? 'selected' : '' }}>{{ $name }}</option>
                @endforeach
            </select>
            <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700">
                Filter
            </button>
            @if($search || $logName)
                <a href="{{ route('admin.activity-logs.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                    Clear
                </a>
            @endif
        </form>
    </div>

    <table class="w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Time</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Subject</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse($logs as $log)
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    {{ $log->created_at->format('d M Y H:i:s') }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    @if($log->log_name)
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                            {{ $log->log_name }}
                        </span>
                    @else
                        <span class="text-gray-400">-</span>
                    @endif
                </td>
                <td class="px-6 py-4 text-sm text-gray-600">
                    {{ $log->description }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    @if($log->subject_type)
                        {{ class_basename($log->subject_type) }} #{{ $log->subject_id }}
                    @else
                        -
                    @endif
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">No activity logs found</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="flex justify-center mt-4">
    {{ $logs->links() }}
</div>
@endsection

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    /**
     * Display activity logs.
     */
    public function index(Request $request)
    {
        $search = $request->input('search', '');
        $logName = $request->input('log_name', '');

        $logs = ActivityLog::when($search, function ($query) use ($search) {
            $query->where('description', 'like', "%{$search}%");
        })
            ->when($logName, function ($query) use ($logName) {
                $query->where('log_name', $logName);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        // Get unique log names for filter
        $logNames = ActivityLog::distinct()->pluck('log_name')->filter()->values();

        return view('admin.activity-logs.index', compact('logs', 'search', 'logName', 'logNames'));
    }
}

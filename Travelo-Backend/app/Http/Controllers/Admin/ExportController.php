<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Destination;
use App\Models\TourPackage;
use App\Models\User;
use App\Services\ExportService;
use Illuminate\Http\Request;

class ExportController extends Controller
{
    protected ExportService $exportService;

    public function __construct(ExportService $exportService)
    {
        $this->exportService = $exportService;
    }

    /**
     * Export bookings to CSV/Excel/PDF
     */
    public function exportBookings(Request $request)
    {
        $format = $request->input('format', 'csv');

        $bookings = Booking::with(['user', 'tourPackage', 'tourPackage.destination'])
            ->orderBy('created_at', 'desc')
            ->get();

        $data = $this->exportService->bookingsToArray($bookings);

        return $this->download($data, 'bookings', $format, 'Bookings Report');
    }

    /**
     * Export users to CSV/Excel/PDF
     */
    public function exportUsers(Request $request)
    {
        $format = $request->input('format', 'csv');

        $users = User::with('bookings')->get();

        $data = $this->exportService->usersToArray($users);

        return $this->download($data, 'users', $format, 'Users Report');
    }

    /**
     * Export destinations to CSV/Excel/PDF
     */
    public function exportDestinations(Request $request)
    {
        $format = $request->input('format', 'csv');

        $destinations = Destination::with('tourPackages')->get();

        $data = $this->exportService->destinationsToArray($destinations);

        return $this->download($data, 'destinations', $format, 'Destinations Report');
    }

    /**
     * Export tour packages to CSV/Excel/PDF
     */
    public function exportTourPackages(Request $request)
    {
        $format = $request->input('format', 'csv');

        $tourPackages = TourPackage::with(['destination', 'bookings'])->get();

        $data = $this->exportService->tourPackagesToArray($tourPackages);

        return $this->download($data, 'tour-packages', $format, 'Tour Packages Report');
    }

    /**
     * Download the exported file
     */
    protected function download(array $data, string $filename, string $format, string $title)
    {
        if (empty($data)) {
            return back()->with('error', 'No data to export');
        }

        $filename = $filename . '_' . now()->format('Y-m-d_His');

        switch ($format) {
            case 'csv':
                $content = $this->exportService->toCsv($data, $filename);
                $mimeType = 'text/csv';
                $extension = 'csv';
                break;

            case 'json':
                $content = json_encode($data, JSON_PRETTY_PRINT);
                $mimeType = 'application/json';
                $extension = 'json';
                break;

            case 'pdf':
                // For PDF, we'll return HTML that can be printed to PDF
                $html = $this->exportService->toHtml($data, $title);
                return response($html)
                    ->header('Content-Type', 'text/html');

            default:
                $content = $this->exportService->toCsv($data, $filename);
                $mimeType = 'text/csv';
                $extension = 'csv';
        }

        return response($content)
            ->header('Content-Type', $mimeType)
            ->header('Content-Disposition', "attachment; filename=\"{$filename}.{$extension}\"");
    }
}

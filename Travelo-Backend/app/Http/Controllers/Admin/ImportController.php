<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ImportController extends Controller
{
    protected ImportService $importService;

    public function __construct(ImportService $importService)
    {
        $this->importService = $importService;
    }

    /**
     * Show import page
     */
    public function index(): View
    {
        return view('admin.import.index');
    }

    /**
     * Show destinations import page
     */
    public function destinations(): View
    {
        $template = $this->importService->getDestinationTemplate();
        return view('admin.import.destinations', compact('template'));
    }

    /**
     * Show tour packages import page
     */
    public function tourPackages(): View
    {
        $template = $this->importService->getTourPackageTemplate();
        return view('admin.import.tour-packages', compact('template'));
    }

    /**
     * Preview destinations import
     */
    public function previewDestinations(Request $request): View|RedirectResponse
    {
        // Redirect GET requests back to the upload page
        if ($request->isMethod('get')) {
            return redirect()->route('admin.import.destinations');
        }

        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:5120',
        ]);

        $file = $request->file('file');

        // Store the file temporarily so it persists between requests
        $tempPath = $file->store('temp/imports', 'public');
        if (!$tempPath) {
            return redirect()->route('admin.import.destinations')
                ->with('error', 'Failed to upload file. Please try again.');
        }

        $results = $this->importService->importDestinations($file, true);

        return view('admin.import.destinations-preview', [
            'results' => $results,
            'temp_file_path' => $tempPath,
        ]);
    }

    /**
     * Preview tour packages import
     */
    public function previewTourPackages(Request $request): View|RedirectResponse
    {
        // Redirect GET requests back to the upload page
        if ($request->isMethod('get')) {
            return redirect()->route('admin.import.tour-packages');
        }

        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:5120',
        ]);

        $file = $request->file('file');

        // Store the file temporarily so it persists between requests
        $tempPath = $file->store('temp/imports', 'public');
        if (!$tempPath) {
            return redirect()->route('admin.import.tour-packages')
                ->with('error', 'Failed to upload file. Please try again.');
        }

        $results = $this->importService->importTourPackages($file, true);

        return view('admin.import.tour-packages-preview', [
            'results' => $results,
            'temp_file_path' => $tempPath,
        ]);
    }

    /**
     * Process destinations import
     */
    public function importDestinations(Request $request): RedirectResponse
    {
        $tempPath = $request->input('temp_file_path');
        if (!$tempPath || !\Storage::disk('public')->exists($tempPath)) {
            return redirect()->route('admin.import.destinations')
                ->with('error', 'File not found. Please upload the file again.');
        }

        // Create a temporary UploadedFile instance from the stored file
        $fullPath = \Storage::disk('public')->path($tempPath);
        $file = new \Illuminate\Http\UploadedFile($fullPath, 'import.csv', 'text/csv', null, true);

        $results = $this->importService->importDestinations($file, false);

        // Clean up temporary file
        \Storage::disk('public')->delete($tempPath);

        if ($results['success']) {
            return redirect()->route('admin.destinations.index')
                ->with('success', "Import completed! {$results['imported']} new destinations created, {$results['updated']} updated, {$results['skipped']} skipped.");
        }

        return redirect()->route('admin.import.destinations')
            ->with('error', 'Import failed. Please check the errors and try again.')
            ->with('import_errors', $results['errors']);
    }

    /**
     * Process tour packages import
     */
    public function importTourPackages(Request $request): RedirectResponse
    {
        $tempPath = $request->input('temp_file_path');
        if (!$tempPath || !\Storage::disk('public')->exists($tempPath)) {
            return redirect()->route('admin.import.tour-packages')
                ->with('error', 'File not found. Please upload the file again.');
        }

        // Create a temporary UploadedFile instance from the stored file
        $fullPath = \Storage::disk('public')->path($tempPath);
        $file = new \Illuminate\Http\UploadedFile($fullPath, 'import.csv', 'text/csv', null, true);

        $results = $this->importService->importTourPackages($file, false);

        // Clean up temporary file
        \Storage::disk('public')->delete($tempPath);

        if ($results['success']) {
            return redirect()->route('admin.tour-packages.index')
                ->with('success', "Import completed! {$results['imported']} new packages created, {$results['updated']} updated, {$results['skipped']} skipped.");
        }

        return redirect()->route('admin.import.tour-packages')
            ->with('error', 'Import failed. Please check the errors and try again.')
            ->with('import_errors', $results['errors']);
    }

    /**
     * Download CSV template
     */
    public function downloadTemplate(string $type)
    {
        if ($type === 'destinations') {
            $template = $this->importService->getDestinationTemplate();
            $filename = 'destinations_template.csv';
        } elseif ($type === 'tour-packages') {
            $template = $this->importService->getTourPackageTemplate();
            $filename = 'tour_packages_template.csv';
        } else {
            abort(404);
        }

        $headers = $template['headers'];
        $sampleData = $template['sample_data'];

        $csvContent = implode(',', $headers) . "\n";
        foreach ($sampleData as $row) {
            $csvContent .= implode(',', array_map(function ($value) {
                // Cast to string to ensure str_contains works properly
                $stringValue = (string) $value;
                // Escape double quotes and wrap in quotes if contains comma or quotes
                if (str_contains($stringValue, ',') || str_contains($stringValue, '"')) {
                    return '"' . str_replace('"', '""', $stringValue) . '"';
                }
                return $stringValue;
            }, array_values($row))) . "\n";
        }

        return response($csvContent)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }
}

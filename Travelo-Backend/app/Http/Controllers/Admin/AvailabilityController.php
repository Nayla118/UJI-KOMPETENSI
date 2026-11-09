<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TourPackage;
use App\Models\TourPackageDateAvailability;
use App\Services\AvailabilityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AvailabilityController extends Controller
{
    public function __construct(protected AvailabilityService $availability) {}

    public function index(Request $request, TourPackage $tourPackage): View
    {
        $month = (string) $request->query('month', now()->format('Y-m'));

        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            $month = now()->format('Y-m');
        }

        $tourPackage->load(['destination', 'dateAvailabilities']);
        $data = $this->availability->forMonth($tourPackage, $month);

        $selectedDate = (string) $request->query('date', now()->toDateString());

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
            $selectedDate = now()->toDateString();
        }

        return view('admin.tour-packages.availability', [
            'tourPackage' => $tourPackage,
            'month' => $month,
            'days' => $data['days'],
            'defaultCapacity' => $data['default_capacity'],
            'selectedDate' => $selectedDate,
        ]);
    }

    /**
     * Create or update the override for one date.
     */
    public function store(Request $request, TourPackage $tourPackage): RedirectResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'is_closed' => ['sometimes', 'boolean'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $isClosed = $request->boolean('is_closed');

        TourPackageDateAvailability::updateOrCreate(
            [
                'tour_package_id' => $tourPackage->id,
                'date' => $validated['date'],
            ],
            [
                'capacity' => $validated['capacity'] ?? null,
                'is_closed' => $isClosed,
                'note' => $validated['note'] ?? null,
            ]
        );

        $month = substr($validated['date'], 0, 7);

        return redirect()
            ->route('admin.tour-packages.availability', ['tourPackage' => $tourPackage, 'month' => $month])
            ->with('success', $isClosed
                ? 'Date closed for bookings.'
                : 'Date capacity saved.');
    }

    public function destroy(TourPackage $tourPackage, TourPackageDateAvailability $availability): RedirectResponse
    {
        abort_unless((int) $availability->tour_package_id === (int) $tourPackage->id, 404);

        $month = $availability->date?->format('Y-m') ?? now()->format('Y-m');
        $availability->delete();

        return redirect()
            ->route('admin.tour-packages.availability', ['tourPackage' => $tourPackage, 'month' => $month])
            ->with('success', 'Date override removed. The package default applies again.');
    }
}

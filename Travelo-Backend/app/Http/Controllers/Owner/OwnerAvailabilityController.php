<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\TourPackage;
use App\Models\TourPackageDateAvailability;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OwnerAvailabilityController extends Controller
{
    public function index(TourPackage $tourPackage): View
    {
        $this->authorizeOwner($tourPackage);

        $availabilities = $tourPackage->dateAvailabilities()->latest()->paginate(15);

        return view('owner.availability.index', compact('tourPackage', 'availabilities'));
    }

    public function store(Request $request, TourPackage $tourPackage): RedirectResponse
    {
        $this->authorizeOwner($tourPackage);

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'is_closed' => ['boolean'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        TourPackageDateAvailability::updateOrCreate(
            ['tour_package_id' => $tourPackage->id, 'date' => $validated['date']],
            [
                'capacity' => $validated['capacity'] ?? null,
                'is_closed' => $validated['is_closed'] ?? false,
                'note' => $validated['note'] ?? null,
            ]
        );

        return redirect()->route('owner.tour-packages.availability', $tourPackage)
            ->with('success', 'Ketersediaan tanggal berhasil disimpan.');
    }

    public function destroy(TourPackage $tourPackage, TourPackageDateAvailability $availability): RedirectResponse
    {
        $this->authorizeOwner($tourPackage);

        $availability->delete();

        return redirect()->route('owner.tour-packages.availability', $tourPackage)
            ->with('success', 'Override ketersediaan tanggal dihapus.');
    }

    private function authorizeOwner(TourPackage $tourPackage): void
    {
        $user = auth()->user();
        if (! $user->isAdmin() && ! $user->destinations()->whereKey($tourPackage->destination_id)->exists()) {
            abort(403, 'Anda tidak memiliki akses ke paket wisata ini.');
        }
    }
}

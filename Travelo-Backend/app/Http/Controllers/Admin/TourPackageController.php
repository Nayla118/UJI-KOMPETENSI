<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Destination;
use App\Models\TourPackage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class TourPackageController extends Controller
{
    /**
     * Display tour packages list
     */
    public function index(Request $request): View
    {
        $query = TourPackage::with('destination');

        // Search functionality
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhereHas('destination', function ($dq) use ($search) {
                        $dq->where('name', 'like', "%{$search}%")
                            ->orWhere('city', 'like', "%{$search}%")
                            ->orWhere('country', 'like', "%{$search}%");
                    });
            });
        }

        // Filter by destination
        if ($request->has('destination') && $request->destination) {
            $query->where('destination_id', $request->destination);
        }

        // Get destinations for filter dropdown
        $destinations = Destination::all();

        $tourPackages = $query->latest()->paginate(10)->appends($request->query());

        return view('admin.tour-packages.index', compact('tourPackages', 'destinations'));
    }

    /**
     * Show create form
     */
    public function create(): View
    {
        $destinations = Destination::all();
        return view('admin.tour-packages.create', compact('destinations'));
    }

    /**
     * Store new tour package
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'destination_id' => 'required|exists:destinations,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'duration_days' => 'required|integer|min:1',
            'max_people' => 'required|integer|min:1',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('tour-packages', 'public');
        }

        TourPackage::create($validated);

        return redirect()->route('admin.tour-packages.index')
            ->with('success', 'Tour package created successfully.');
    }

    /**
     * Show edit form
     */
    public function edit(TourPackage $tourPackage): View
    {
        $destinations = Destination::all();
        return view('admin.tour-packages.edit', compact('tourPackage', 'destinations'));
    }

    /**
     * Update tour package
     */
    public function update(Request $request, TourPackage $tourPackage): RedirectResponse
    {
        $validated = $request->validate([
            'destination_id' => 'required|exists:destinations,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'duration_days' => 'required|integer|min:1',
            'max_people' => 'required|integer|min:1',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        if ($request->hasFile('image')) {
            if ($tourPackage->image) {
                Storage::disk('public')->delete($tourPackage->image);
            }
            $validated['image'] = $request->file('image')->store('tour-packages', 'public');
            $tourPackage->update($validated);
        } else {
            // Only update non-image fields if no new image
            $tourPackage->update($validated);
        }

        return redirect()->route('admin.tour-packages.index')
            ->with('success', 'Tour package updated successfully.');
    }

    /**
     * Show tour package details
     */
    public function show(TourPackage $tourPackage): View
    {
        $tourPackage->load(['destination', 'bookings']);
        return view('admin.tour-packages.show', compact('tourPackage'));
    }

    /**
     * Delete tour package
     */
    public function destroy(TourPackage $tourPackage): RedirectResponse
    {
        if ($tourPackage->image) {
            Storage::disk('public')->delete($tourPackage->image);
        }

        $tourPackage->delete();

        return redirect()->route('admin.tour-packages.index')
            ->with('success', 'Tour package deleted successfully.');
    }

    /**
     * Bulk delete selected tour packages
     */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:tour_packages,id'
        ]);

        $ids = $request->input('ids', []);
        $deletedCount = 0;

        foreach ($ids as $id) {
            $package = TourPackage::findOrFail($id);
            if ($package->image) {
                Storage::disk('public')->delete($package->image);
            }
            $package->delete();
            $deletedCount++;
        }

        return redirect()->route('admin.tour-packages.index')
            ->with('success', "{$deletedCount} tour package(s) deleted successfully.");
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Destination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class DestinationController extends Controller
{
    /**
     * Display destinations list
     */
    public function index(Request $request): View
    {
        $query = Destination::query();

        // Search functionality
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('country', 'like', "%{$search}%");
            });
        }

        // Filter by country
        if ($request->has('country') && $request->country) {
            $query->where('country', $request->country);
        }

        // Get unique countries for filter dropdown
        $countries = Destination::distinct()->pluck('country')->sort()->toArray();

        $destinations = $query->latest()->paginate(10)->appends($request->query());

        return view('admin.destinations.index', compact('destinations', 'countries'));
    }

    /**
     * Show create form
     */
    public function create(): View
    {
        return view('admin.destinations.create');
    }

    /**
     * Store new destination
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'country' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'rating' => 'nullable|numeric|min:0|max:5',
        ]);

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('destinations', 'public');
        }

        Destination::create($validated);

        return redirect()->route('admin.destinations.index')
            ->with('success', 'Destination created successfully.');
    }

    /**
     * Show edit form
     */
    public function edit(Destination $destination): View
    {
        return view('admin.destinations.edit', compact('destination'));
    }

    /**
     * Update destination
     */
    public function update(Request $request, Destination $destination): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'country' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'rating' => 'nullable|numeric|min:0|max:5',
        ]);

        if ($request->hasFile('image')) {
            // Delete old image
            if ($destination->image) {
                Storage::disk('public')->delete($destination->image);
            }
            $validated['image'] = $request->file('image')->store('destinations', 'public');
            $destination->update($validated);
        } else {
            // Only update non-image fields if no new image
            $destination->update($validated);
        }

        return redirect()->route('admin.destinations.index')
            ->with('success', 'Destination updated successfully.');
    }

    /**
     * Show destination details
     */
    public function show(Destination $destination): View
    {
        $destination->load(['tourPackages']);
        return view('admin.destinations.show', compact('destination'));
    }

    /**
     * Delete destination
     */
    public function destroy(Destination $destination): RedirectResponse
    {
        if ($destination->image) {
            Storage::disk('public')->delete($destination->image);
        }

        $destination->delete();

        return redirect()->route('admin.destinations.index')
            ->with('success', 'Destination deleted successfully.');
    }

    /**
     * Bulk delete selected destinations
     */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:destinations,id'
        ]);

        $ids = $request->input('ids', []);
        $deletedCount = 0;

        foreach ($ids as $id) {
            $destination = Destination::findOrFail($id);
            if ($destination->image) {
                Storage::disk('public')->delete($destination->image);
            }
            $destination->delete();
            $deletedCount++;
        }

        return redirect()->route('admin.destinations.index')
            ->with('success', "{$deletedCount} destination(s) deleted successfully.");
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ManagesTourPackageContent;
use App\Http\Controllers\Controller;
use App\Models\Destination;
use App\Models\TourPackage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TourPackageController extends Controller
{
    use ManagesTourPackageContent;

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
        $validated = $request->validate($this->rules());

        $validated['inclusions'] = $this->parseList($validated['inclusions'] ?? []);
        $validated['exclusions'] = $this->parseList($validated['exclusions'] ?? []);

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('tour-packages', 'public');
        }

        $itinerary = $validated['itinerary'] ?? [];
        $gallery = $request->file('images', []);
        unset($validated['itinerary'], $validated['images'], $validated['remove_images'], $validated['remove_cover']);

        $tourPackage = TourPackage::create($validated);

        $this->syncItinerary($tourPackage, $itinerary);
        $this->storeGalleryImages($tourPackage, $gallery);

        return redirect()->route('admin.tour-packages.index')
            ->with('success', 'Tour package created successfully.');
    }

    /**
     * Show edit form
     */
    public function edit(TourPackage $tourPackage): View
    {
        $tourPackage->load([
            'destination' => fn ($query) => $query
                ->withAvg('publishedReviews as reviews_avg_rating', 'rating')
                ->withCount('publishedReviews as reviews_count'),
            'itineraryDays',
            'images',
        ]);
        $destinations = Destination::all();

        return view('admin.tour-packages.edit', compact('tourPackage', 'destinations'));
    }

    /**
     * Update tour package
     */
    public function update(Request $request, TourPackage $tourPackage): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        $validated['inclusions'] = $this->parseList($validated['inclusions'] ?? []);
        $validated['exclusions'] = $this->parseList($validated['exclusions'] ?? []);

        if ($request->hasFile('image')) {
            $this->deletePublicFile($tourPackage->image);
            $validated['image'] = $request->file('image')->store('tour-packages', 'public');
        } elseif ($request->boolean('remove_cover')) {
            $this->deletePublicFile($tourPackage->image);
            $validated['image'] = null;
        } else {
            unset($validated['image']);
        }

        $itinerary = $validated['itinerary'] ?? [];
        $gallery = $request->file('images', []);
        $removeImages = $validated['remove_images'] ?? [];
        unset($validated['itinerary'], $validated['images'], $validated['remove_images'], $validated['remove_cover']);

        $tourPackage->update($validated);

        $this->syncItinerary($tourPackage, $itinerary);
        $this->deleteGalleryImages($tourPackage, $removeImages);
        $this->storeGalleryImages($tourPackage, $gallery);

        return redirect()->route('admin.tour-packages.index')
            ->with('success', 'Tour package updated successfully.');
    }

    /**
     * Show tour package details
     */
    public function show(TourPackage $tourPackage): View
    {
        $tourPackage->load([
            'destination' => fn ($query) => $query
                ->withAvg('publishedReviews as reviews_avg_rating', 'rating')
                ->withCount('publishedReviews as reviews_count'),
            'bookings',
            'itineraryDays',
            'images',
        ]);

        return view('admin.tour-packages.show', compact('tourPackage'));
    }

    /**
     * Delete tour package
     */
    public function destroy(TourPackage $tourPackage): RedirectResponse
    {
        $this->deletePublicFile($tourPackage->image);
        $this->deleteAllGalleryImages($tourPackage);

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
            'ids.*' => 'exists:tour_packages,id',
        ]);

        $ids = $request->input('ids', []);
        $deletedCount = 0;

        foreach ($ids as $id) {
            $package = TourPackage::findOrFail($id);
            $this->deletePublicFile($package->image);
            $this->deleteAllGalleryImages($package);
            $package->delete();
            $deletedCount++;
        }

        return redirect()->route('admin.tour-packages.index')
            ->with('success', "{$deletedCount} tour package(s) deleted successfully.");
    }

    /**
     * Rules shared by create and update.
     *
     * `image`/`images.*` use Laravel's `image` rule (content sniffing via
     * getimagesize) plus an explicit MIME allow-list, so renaming a non-image
     * file cannot pass validation.
     *
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'destination_id' => 'required|exists:destinations,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'duration_days' => 'required|integer|min:1',
            'max_people' => 'required|integer|min:1',
            'image' => 'nullable|image|mimetypes:image/jpeg,image/png,image/webp,image/gif|max:4096',
            'inclusions' => 'nullable',
            'exclusions' => 'nullable',
            'meeting_point' => 'nullable|string|max:255',
            'meeting_latitude' => 'nullable|numeric|between:-90,90|required_with:meeting_longitude',
            'meeting_longitude' => 'nullable|numeric|between:-180,180|required_with:meeting_latitude',
            'terms' => 'nullable|string',
            'itinerary' => 'nullable|array',
            'itinerary.*.day_number' => 'nullable|integer|min:1',
            'itinerary.*.title' => 'nullable|string|max:255',
            'itinerary.*.description' => 'nullable|string',
            'images' => 'nullable|array|max:10',
            'images.*' => 'image|mimetypes:image/jpeg,image/png,image/webp,image/gif|max:4096',
            'remove_images' => 'nullable|array',
            'remove_images.*' => 'integer|exists:tour_package_images,id',
            'remove_cover' => 'nullable|boolean',
        ];
    }
}

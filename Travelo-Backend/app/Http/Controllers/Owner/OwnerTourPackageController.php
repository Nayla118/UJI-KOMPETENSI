<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Concerns\ManagesTourPackageContent;
use App\Http\Controllers\Controller;
use App\Models\TourPackage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OwnerTourPackageController extends Controller
{
    use ManagesTourPackageContent;

    public function index(Request $request): View
    {
        $user = auth()->user();
        $destinationIds = $user->destinations->pluck('id');

        $query = TourPackage::whereIn('destination_id', $destinationIds)
            ->with('destination');

        if ($request->has('search') && $request->search) {
            $query->where('title', 'like', "%{$request->search}%");
        }

        $packages = $query->latest()->paginate(10)->appends($request->query());

        return view('owner.tour-packages.index', compact('packages'));
    }

    public function create(): View
    {
        $destinations = auth()->user()->destinations()->orderBy('name')->get();

        return view('owner.tour-packages.create', compact('destinations'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = auth()->user();

        $validated = $request->validate($this->rules($user));

        $validated['inclusions'] = $this->parseList($validated['inclusions'] ?? []);
        $validated['exclusions'] = $this->parseList($validated['exclusions'] ?? []);

        if ($request->hasFile('cover_image')) {
            $validated['image'] = $request->file('cover_image')->store('tour-packages', 'public');
        }

        $itinerary = $validated['itinerary'] ?? [];
        $gallery = $request->file('images', []);
        unset(
            $validated['itinerary'],
            $validated['images'],
            $validated['remove_images'],
            $validated['remove_cover'],
            $validated['cover_image'],
        );

        $tourPackage = TourPackage::create($validated);

        $this->syncItinerary($tourPackage, $itinerary);
        $this->storeGalleryImages($tourPackage, $gallery);

        return redirect()->route('owner.tour-packages.index')
            ->with('success', 'Paket wisata berhasil ditambahkan.');
    }

    public function show(TourPackage $tourPackage): View
    {
        $this->authorizePackage($tourPackage);

        $tourPackage->load(['destination', 'itineraryDays', 'images']);

        return view('owner.tour-packages.show', compact('tourPackage'));
    }

    public function edit(TourPackage $tourPackage): View
    {
        $this->authorizePackage($tourPackage);

        $tourPackage->load(['itineraryDays', 'images']);
        $destinations = auth()->user()->destinations()->orderBy('name')->get();

        return view('owner.tour-packages.edit', compact('tourPackage', 'destinations'));
    }

    public function update(Request $request, TourPackage $tourPackage): RedirectResponse
    {
        $this->authorizePackage($tourPackage);

        $user = auth()->user();

        $validated = $request->validate($this->rules($user));

        $validated['inclusions'] = $this->parseList($validated['inclusions'] ?? []);
        $validated['exclusions'] = $this->parseList($validated['exclusions'] ?? []);

        if ($request->hasFile('cover_image')) {
            $this->deletePublicFile($tourPackage->image);
            $validated['image'] = $request->file('cover_image')->store('tour-packages', 'public');
        } elseif ($request->boolean('remove_cover')) {
            $this->deletePublicFile($tourPackage->image);
            $validated['image'] = null;
        } elseif (empty($validated['image'] ?? null)) {
            unset($validated['image']);
        }

        $itinerary = $validated['itinerary'] ?? [];
        $gallery = $request->file('images', []);
        $removeImages = $validated['remove_images'] ?? [];
        unset(
            $validated['itinerary'],
            $validated['images'],
            $validated['remove_images'],
            $validated['remove_cover'],
            $validated['cover_image'],
        );

        $tourPackage->update($validated);

        $this->syncItinerary($tourPackage, $itinerary);
        $this->deleteGalleryImages($tourPackage, $removeImages);
        $this->storeGalleryImages($tourPackage, $gallery);

        return redirect()->route('owner.tour-packages.index')
            ->with('success', 'Paket wisata berhasil diperbarui.');
    }

    public function destroy(TourPackage $tourPackage): RedirectResponse
    {
        $this->authorizePackage($tourPackage);

        $this->deletePublicFile($tourPackage->image);
        $this->deleteAllGalleryImages($tourPackage);

        $tourPackage->delete();

        return redirect()->route('owner.tour-packages.index')
            ->with('success', 'Paket wisata berhasil dihapus.');
    }

    private function authorizePackage(TourPackage $tourPackage): void
    {
        $user = auth()->user();
        if (! $user->isAdmin() && ! $user->destinations()->whereKey($tourPackage->destination_id)->exists()) {
            abort(403, 'Anda tidak memiliki akses ke paket wisata ini.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function rules($user): array
    {
        return [
            'destination_id' => ['required', 'exists:destinations,id', function ($attribute, $value, $fail) use ($user) {
                if (! $user->destinations()->whereKey($value)->exists()) {
                    $fail('Anda tidak memiliki destinasi ini.');
                }
            }],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'duration_days' => ['required', 'integer', 'min:1'],
            'max_people' => ['required', 'integer', 'min:1'],
            // Legacy URL cover plus a real file upload. Upload wins when both
            // are present, matching the admin form.
            'image' => ['nullable', 'url', 'max:2048'],
            'cover_image' => ['nullable', 'image', 'mimetypes:image/jpeg,image/png,image/webp,image/gif', 'max:4096'],
            'inclusions' => ['nullable'],
            'exclusions' => ['nullable'],
            'meeting_point' => ['nullable', 'string', 'max:255'],
            'meeting_latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:meeting_longitude'],
            'meeting_longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:meeting_latitude'],
            'terms' => ['nullable', 'string'],
            'itinerary' => ['nullable', 'array'],
            'itinerary.*.day_number' => ['nullable', 'integer', 'min:1'],
            'itinerary.*.title' => ['nullable', 'string', 'max:255'],
            'itinerary.*.description' => ['nullable', 'string'],
            'images' => ['nullable', 'array', 'max:10'],
            'images.*' => ['image', 'mimetypes:image/jpeg,image/png,image/webp,image/gif', 'max:4096'],
            'remove_images' => ['nullable', 'array'],
            'remove_images.*' => ['integer', 'exists:tour_package_images,id'],
            'remove_cover' => ['nullable', 'boolean'],
        ];
    }
}

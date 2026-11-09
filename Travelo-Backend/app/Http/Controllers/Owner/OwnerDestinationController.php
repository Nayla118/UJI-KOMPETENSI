<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Destination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OwnerDestinationController extends Controller
{
    public function index(Request $request): View
    {
        $user = auth()->user();

        $query = $user->destinations()
            ->withAvg('publishedReviews as reviews_avg_rating', 'rating')
            ->withCount('publishedReviews as reviews_count')
            ->withCount('tourPackages as packages_count');

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('country', 'like', "%{$search}%");
            });
        }

        $destinations = $query->latest()->paginate(10)->appends($request->query());

        return view('owner.destinations.index', compact('destinations'));
    }

    public function create(): View
    {
        return view('owner.destinations.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'country' => ['required', 'string', 'max:255'],
            'category' => ['required', 'in:pantai,gunung,alam,budaya,kota,kuliner'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'url'],
            'rating' => ['nullable', 'numeric', 'between:0,5'],
            'is_popular' => ['boolean'],
        ]);

        $destination = Destination::create($validated);

        // Auto-assign the creating owner to this destination via pivot.
        auth()->user()->destinations()->attach($destination->id);

        return redirect()->route('owner.destinations.index')
            ->with('success', 'Destinasi berhasil ditambahkan.');
    }

    public function show(Destination $destination): View
    {
        $this->authorizeDestination($destination);

        $destination->load(['tourPackages', 'publishedReviews']);

        return view('owner.destinations.show', compact('destination'));
    }

    public function edit(Destination $destination): View
    {
        $this->authorizeDestination($destination);

        return view('owner.destinations.edit', compact('destination'));
    }

    public function update(Request $request, Destination $destination): RedirectResponse
    {
        $this->authorizeDestination($destination);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'country' => ['required', 'string', 'max:255'],
            'category' => ['required', 'in:pantai,gunung,alam,budaya,kota,kuliner'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'url'],
            'rating' => ['nullable', 'numeric', 'between:0,5'],
            'is_popular' => ['boolean'],
        ]);

        $destination->update($validated);

        return redirect()->route('owner.destinations.index')
            ->with('success', 'Destinasi berhasil diperbarui.');
    }

    public function destroy(Destination $destination): RedirectResponse
    {
        $this->authorizeDestination($destination);

        $destination->delete();

        return redirect()->route('owner.destinations.index')
            ->with('success', 'Destinasi berhasil dihapus.');
    }

    private function authorizeDestination(Destination $destination): void
    {
        $user = auth()->user();
        if (! $user->isAdmin() && ! $user->destinations()->whereKey($destination->id)->exists()) {
            abort(403, 'Anda tidak memiliki akses ke destinasi ini.');
        }
    }
}

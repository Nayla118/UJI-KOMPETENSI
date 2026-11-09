<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Destination;
use App\Models\PromoCode;
use App\Models\TourPackage;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Owner promo management, strictly scoped to the owner's own destinations and
 * packages. Owners may not create global promo codes; at least one scope is
 * required. Admin keeps using the unrestricted admin promo controller.
 */
class OwnerPromoCodeController extends Controller
{
    public function index(Request $request): View
    {
        $actor = $request->user();
        $scopes = $this->scopes($actor);

        $query = PromoCode::query()
            ->with(['tourPackage:id,title', 'destination:id,name'])
            ->where(function ($sub) use ($scopes) {
                $sub->whereIn('destination_id', $scopes['destinations'])
                    ->orWhereIn('tour_package_id', $scopes['packages']);
            });

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $query->where(function ($sub) use ($search) {
                $sub->where('code', 'like', '%'.strtoupper($search).'%')
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $promos = $query->latest('id')->paginate(15)->appends($request->query());

        return view('owner.promos.index', compact('promos'));
    }

    public function create(Request $request): View
    {
        return view('owner.promos.create', $this->formData($request->user()));
    }

    public function store(Request $request): RedirectResponse
    {
        $actor = $request->user();
        $validated = $this->validated($request, $actor);

        $promo = PromoCode::create($validated);
        $this->authorizePromo($actor, $promo);

        ActivityLog::log(
            'Owner promo code created',
            PromoCode::class,
            $promo->id,
            User::class,
            $actor->id,
            ['code' => $promo->code, 'destination_id' => $promo->destination_id, 'tour_package_id' => $promo->tour_package_id]
        );

        return redirect()->route('owner.promos.index')
            ->with('success', 'Promo berhasil dibuat.');
    }

    public function edit(Request $request, PromoCode $promo): View
    {
        $this->authorizePromo($request->user(), $promo);

        return view('owner.promos.edit', array_merge(
            ['promo' => $promo],
            $this->formData($request->user())
        ));
    }

    public function update(Request $request, PromoCode $promo): RedirectResponse
    {
        $actor = $request->user();
        $this->authorizePromo($actor, $promo);

        $validated = $this->validated($request, $actor, $promo);
        $promo->update($validated);
        $this->authorizePromo($actor, $promo->refresh());

        ActivityLog::log(
            'Owner promo code updated',
            PromoCode::class,
            $promo->id,
            User::class,
            $actor->id,
            ['code' => $promo->code, 'changes' => array_keys($promo->getChanges())]
        );

        return redirect()->route('owner.promos.index')
            ->with('success', 'Promo diperbarui.');
    }

    public function toggle(Request $request, PromoCode $promo): RedirectResponse
    {
        $actor = $request->user();
        $this->authorizePromo($actor, $promo);

        $promo->update(['is_active' => ! $promo->is_active]);

        ActivityLog::log(
            $promo->is_active ? 'Owner promo code activated' : 'Owner promo code deactivated',
            PromoCode::class,
            $promo->id,
            User::class,
            $actor->id,
            ['code' => $promo->code]
        );

        return back()->with('success', $promo->is_active ? 'Promo diaktifkan.' : 'Promo dinonaktifkan.');
    }

    public function destroy(Request $request, PromoCode $promo): RedirectResponse
    {
        $actor = $request->user();
        $this->authorizePromo($actor, $promo);

        ActivityLog::log(
            'Owner promo code deleted',
            PromoCode::class,
            $promo->id,
            User::class,
            $actor->id,
            ['code' => $promo->code]
        );

        $promo->delete();

        return redirect()->route('owner.promos.index')
            ->with('success', 'Promo dihapus.');
    }

    /**
     * @return array{destinations: array<int, int>, packages: array<int, int>}
     */
    private function scopes(User $actor): array
    {
        if ($actor->isAdmin()) {
            return [
                'destinations' => Destination::query()->pluck('id')->map(fn ($id) => (int) $id)->all(),
                'packages' => TourPackage::query()->pluck('id')->map(fn ($id) => (int) $id)->all(),
            ];
        }

        $destinationIds = $actor->destinations()->pluck('destinations.id')->map(fn ($id) => (int) $id)->all();

        return [
            'destinations' => $destinationIds,
            'packages' => TourPackage::query()
                ->whereIn('destination_id', $destinationIds)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all(),
        ];
    }

    private function authorizePromo(User $actor, PromoCode $promo): void
    {
        if ($actor->isAdmin()) {
            return;
        }

        $scopes = $this->scopes($actor);

        $owned = ($promo->destination_id !== null && in_array((int) $promo->destination_id, $scopes['destinations'], true))
            || ($promo->tour_package_id !== null && in_array((int) $promo->tour_package_id, $scopes['packages'], true));

        abort_unless($owned, 403);
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(User $actor): array
    {
        $scopes = $this->scopes($actor);

        return [
            'destinations' => Destination::query()
                ->whereIn('id', $scopes['destinations'])
                ->orderBy('name')
                ->get(['id', 'name']),
            'packages' => TourPackage::query()
                ->whereIn('id', $scopes['packages'])
                ->orderBy('title')
                ->get(['id', 'title']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, User $actor, ?PromoCode $promo = null): array
    {
        $scopes = $this->scopes($actor);

        $request->merge(['code' => PromoCode::normalizeCode($request->input('code'))]);

        $data = $request->validate([
            'code' => [
                'required',
                'string',
                'max:64',
                'regex:/^[A-Z0-9][A-Z0-9_-]*$/',
                Rule::unique('promo_codes', 'code')->ignore($promo?->id),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'discount_type' => ['required', Rule::in(PromoCode::TYPES)],
            'discount_value' => ['required', 'numeric', 'min:0.01', 'max:999999999'],
            'max_discount' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'min_transaction' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'usage_limit' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'per_user_limit' => ['required', 'integer', 'min:1', 'max:1000'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'tour_package_id' => ['nullable', 'integer', Rule::in($scopes['packages'])],
            'destination_id' => ['nullable', 'integer', Rule::in($scopes['destinations'])],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['min_transaction'] = $data['min_transaction'] ?? 0;
        $data['per_user_limit'] = $data['per_user_limit'] ?? 1;
        $data['max_discount'] = $data['discount_type'] === PromoCode::TYPE_PERCENT
            ? ($data['max_discount'] ?? null)
            : null;

        // Owners cannot create a code that applies to every destination.
        if (empty($data['destination_id']) && empty($data['tour_package_id'])) {
            throw ValidationException::withMessages([
                'destination_id' => 'Pilih minimal satu destinasi atau paket untuk promo ini.',
            ]);
        }

        if ($data['discount_type'] === PromoCode::TYPE_PERCENT && (float) $data['discount_value'] > 100) {
            throw ValidationException::withMessages([
                'discount_value' => 'Diskon persentase tidak boleh lebih dari 100%.',
            ]);
        }

        if (! empty($data['starts_at']) && ! empty($data['expires_at'])
            && strtotime((string) $data['expires_at']) < strtotime((string) $data['starts_at'])) {
            throw ValidationException::withMessages([
                'expires_at' => 'Tanggal kedaluwarsa harus setelah tanggal mulai.',
            ]);
        }

        if ($promo !== null && $data['usage_limit'] !== null && $data['usage_limit'] < $promo->used_count) {
            throw ValidationException::withMessages([
                'usage_limit' => 'Batas pemakaian tidak boleh lebih kecil dari jumlah pemakaian saat ini.',
            ]);
        }

        return $data;
    }
}

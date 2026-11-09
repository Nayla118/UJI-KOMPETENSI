<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Destination;
use App\Models\PromoCode;
use App\Models\PromoRedemption;
use App\Models\TourPackage;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PromoCodeController extends Controller
{
    public function index(Request $request): View
    {
        $query = PromoCode::query()
            ->with(['tourPackage:id,title', 'destination:id,name'])
            ->withCount([
                'redemptions as redemptions_count',
                'activeRedemptions as active_redemptions_count',
            ]);

        $search = trim((string) $request->query('search', ''));

        if ($search !== '') {
            $query->where(function ($sub) use ($search) {
                $sub->where('code', 'like', '%'.strtoupper($search).'%')
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $status = (string) $request->query('status', '');

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        } elseif ($status === 'expired') {
            $query->whereNotNull('expires_at')->where('expires_at', '<', now());
        }

        $promos = $query->latest('id')->paginate(10)->appends($request->query());

        $stats = [
            'total' => PromoCode::query()->count(),
            'active' => PromoCode::query()->where('is_active', true)->count(),
            'redemptions' => PromoRedemption::query()
                ->where('status', PromoRedemption::STATUS_APPLIED)
                ->count(),
        ];

        return view('admin.promos.index', compact('promos', 'stats', 'status', 'search'));
    }

    public function create(): View
    {
        return view('admin.promos.create', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        $promo = PromoCode::create($validated);

        ActivityLog::log(
            'Promo code created',
            PromoCode::class,
            $promo->id,
            User::class,
            $request->user()->id,
            ['code' => $promo->code, 'discount_type' => $promo->discount_type]
        );

        return redirect()->route('admin.promos.index')
            ->with('success', 'Promo code created successfully.');
    }

    public function show(PromoCode $promo): View
    {
        $promo->load(['tourPackage:id,title', 'destination:id,name']);

        $usage = $promo->redemptions()
            ->with([
                'user:id,name,email',
                'booking:id,booking_date,people_count,total_price,discount_amount,booking_status,payment_status',
            ])
            ->latest('id')
            ->paginate(20);

        return view('admin.promos.show', compact('promo', 'usage'));
    }

    public function edit(PromoCode $promo): View
    {
        return view('admin.promos.edit', array_merge(
            ['promo' => $promo],
            $this->formData()
        ));
    }

    public function update(Request $request, PromoCode $promo): RedirectResponse
    {
        $validated = $this->validated($request, $promo);

        $promo->update($validated);

        ActivityLog::log(
            'Promo code updated',
            PromoCode::class,
            $promo->id,
            User::class,
            $request->user()->id,
            ['code' => $promo->code, 'changes' => array_keys($promo->getChanges())]
        );

        return redirect()->route('admin.promos.index')
            ->with('success', 'Promo code updated successfully.');
    }

    public function toggle(Request $request, PromoCode $promo): RedirectResponse
    {
        $promo->update(['is_active' => ! $promo->is_active]);

        ActivityLog::log(
            $promo->is_active ? 'Promo code activated' : 'Promo code deactivated',
            PromoCode::class,
            $promo->id,
            User::class,
            $request->user()->id,
            ['code' => $promo->code]
        );

        return back()->with(
            'success',
            $promo->is_active
                ? 'Promo code activated.'
                : 'Promo code deactivated.'
        );
    }

    public function destroy(Request $request, PromoCode $promo): RedirectResponse
    {
        ActivityLog::log(
            'Promo code deleted',
            PromoCode::class,
            $promo->id,
            User::class,
            $request->user()->id,
            ['code' => $promo->code]
        );

        $promo->delete();

        return redirect()->route('admin.promos.index')
            ->with('success', 'Promo code deleted successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [
            'packages' => TourPackage::query()->orderBy('title')->get(['id', 'title']),
            'destinations' => Destination::query()->orderBy('name')->get(['id', 'name']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?PromoCode $promo = null): array
    {
        // Normalise before the unique rule so "summer" and "SUMMER" collide.
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
            'tour_package_id' => ['nullable', 'integer', 'exists:tour_packages,id'],
            'destination_id' => ['nullable', 'integer', 'exists:destinations,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['min_transaction'] = $data['min_transaction'] ?? 0;
        $data['per_user_limit'] = $data['per_user_limit'] ?? 1;
        $data['usage_limit'] = $data['usage_limit'] ?? null;
        $data['max_discount'] = $data['discount_type'] === PromoCode::TYPE_PERCENT
            ? ($data['max_discount'] ?? null)
            : null;

        if ($data['discount_type'] === PromoCode::TYPE_PERCENT && (float) $data['discount_value'] > 100) {
            throw ValidationException::withMessages([
                'discount_value' => 'A percentage discount cannot exceed 100%.',
            ]);
        }

        if (! empty($data['starts_at']) && ! empty($data['expires_at'])
            && strtotime((string) $data['expires_at']) < strtotime((string) $data['starts_at'])) {
            throw ValidationException::withMessages([
                'expires_at' => 'The expiry date must be after the start date.',
            ]);
        }

        if ($promo !== null && $data['usage_limit'] !== null && $data['usage_limit'] < $promo->used_count) {
            throw ValidationException::withMessages([
                'usage_limit' => 'The usage limit cannot be lower than the number of times this code has already been used ('.$promo->used_count.').',
            ]);
        }

        return $data;
    }
}

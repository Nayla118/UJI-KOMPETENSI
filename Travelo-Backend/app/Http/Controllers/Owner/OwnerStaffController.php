<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Destination;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Owner-managed destination staff (manager + gate_staff only).
 *
 * Scope rule: the staff member must have been created by this owner, or (for
 * legacy/admin-created rows) every destination assigned to the staff member
 * must be inside the owner's own destination pivot. Owners can never create
 * admin/owner accounts and can never assign a destination they do not own.
 * Staff are deactivated, never deleted, so the audit trail survives.
 */
class OwnerStaffController extends Controller
{
    public function index(Request $request): View
    {
        $actor = $request->user();
        $destinationIds = $this->ownedDestinationIds($actor);

        $query = User::query()
            ->whereIn('role', User::OWNER_MANAGED_ROLES)
            ->with('destinations');

        if (! $actor->isAdmin()) {
            $query->where(function ($sub) use ($actor, $destinationIds) {
                $sub->where('created_by', $actor->id)
                    ->orWhere(function ($legacy) use ($destinationIds) {
                        $legacy->whereNull('created_by')
                            ->whereHas('destinations', fn ($destinations) => $destinations
                                ->whereIn('destinations.id', $destinationIds));
                    });
            });
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $query->where(function ($sub) use ($search) {
                $sub->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role')) {
            $query->where('role', $request->query('role'));
        }

        $staff = $query->latest()->paginate(15)->appends($request->query());

        return view('owner.staff.index', compact('staff'));
    }

    public function create(Request $request): View
    {
        return view('owner.staff.create', [
            'destinations' => $this->availableDestinations($request->user()),
            'roles' => $this->availableRoles(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $actor = $request->user();
        $destinationIds = $this->ownedDestinationIds($actor);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', Rule::in(User::OWNER_MANAGED_ROLES)],
            'destinations' => ['required', 'array', 'min:1'],
            'destinations.*' => ['integer', Rule::in($destinationIds)],
        ]);

        $tempPassword = Str::random(16);

        $staff = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($tempPassword),
            'role' => $validated['role'],
            'is_active' => true,
            'created_by' => $actor->id,
            'email_verified_at' => now(),
        ]);

        $staff->destinations()->sync($validated['destinations']);

        ActivityLog::log(
            'Destination staff created',
            User::class,
            $staff->id,
            User::class,
            $actor->id,
            [
                'staff_id' => $staff->id,
                'role' => $staff->role,
                'destinations' => $validated['destinations'],
                'created_by' => $actor->id,
                'temp_password_shown' => true,
            ]
        );

        return redirect()->route('owner.staff.index')
            ->with('success', 'Staf berhasil dibuat. Password sementara ditampilkan sekali.')
            ->with('temp_password', $tempPassword)
            ->with('temp_password_staff', $staff->name);
    }

    public function edit(Request $request, User $staff): View
    {
        $this->authorizeStaff($request->user(), $staff);

        return view('owner.staff.edit', [
            'staff' => $staff->load('destinations'),
            'destinations' => $this->availableDestinations($request->user()),
            'roles' => $this->availableRoles(),
            'assigned' => $staff->destinations->pluck('id')->all(),
        ]);
    }

    public function update(Request $request, User $staff): RedirectResponse
    {
        $actor = $request->user();
        $this->authorizeStaff($actor, $staff);

        $destinationIds = $this->ownedDestinationIds($actor);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($staff->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', Rule::in(User::OWNER_MANAGED_ROLES)],
            'destinations' => ['required', 'array', 'min:1'],
            'destinations.*' => ['integer', Rule::in($destinationIds)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $before = [
            'name' => $staff->name,
            'email' => $staff->email,
            'role' => $staff->role,
            'destinations' => $staff->destinations()->pluck('destinations.id')->all(),
        ];

        $staff->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'role' => $validated['role'],
        ]);

        if (! empty($validated['password'])) {
            $staff->password = Hash::make($validated['password']);
        }

        $staff->save();
        $staff->destinations()->sync($validated['destinations']);

        ActivityLog::log(
            'Destination staff updated',
            User::class,
            $staff->id,
            User::class,
            $actor->id,
            [
                'staff_id' => $staff->id,
                'before' => $before,
                'after' => [
                    'name' => $staff->name,
                    'email' => $staff->email,
                    'role' => $staff->role,
                    'destinations' => $validated['destinations'],
                ],
                'password_reset' => ! empty($validated['password']),
            ]
        );

        return redirect()->route('owner.staff.index')
            ->with('success', 'Data staf diperbarui.');
    }

    public function toggleActive(Request $request, User $staff): RedirectResponse
    {
        $actor = $request->user();
        $this->authorizeStaff($actor, $staff);

        // Deactivate, never delete: bookings and activity logs keep pointing
        // at the same account. User's model event revokes Sanctum tokens.
        $staff->update(['is_active' => ! $staff->isActive()]);

        ActivityLog::log(
            $staff->isActive() ? 'Destination staff activated' : 'Destination staff deactivated',
            User::class,
            $staff->id,
            User::class,
            $actor->id,
            [
                'staff_id' => $staff->id,
                'role' => $staff->role,
                'is_active' => $staff->isActive(),
            ]
        );

        return back()->with('success', $staff->isActive()
            ? 'Staf diaktifkan kembali.'
            : 'Staf dinonaktifkan. Riwayat audit tetap tersimpan.');
    }

    private function authorizeStaff(User $actor, User $staff): void
    {
        abort_unless(in_array($staff->role, User::OWNER_MANAGED_ROLES, true), 403);

        if ($actor->isAdmin()) {
            return;
        }

        if ((int) $staff->created_by === (int) $actor->id) {
            return;
        }

        // Legacy/admin-created row: allow only when every destination is
        // inside this owner's pivot, so another owner's staff can never leak.
        if ($staff->created_by === null) {
            $owned = $this->ownedDestinationIds($actor);
            $assigned = $staff->destinations()->pluck('destinations.id')->all();

            abort_unless(
                $assigned !== [] && array_diff($assigned, $owned) === [],
                403
            );

            return;
        }

        abort(403);
    }

    /**
     * @return array<int, int>
     */
    private function ownedDestinationIds(User $actor): array
    {
        if ($actor->isAdmin()) {
            return Destination::query()->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        return $actor->destinations()->pluck('destinations.id')->map(fn ($id) => (int) $id)->all();
    }

    private function availableDestinations(User $actor)
    {
        if ($actor->isAdmin()) {
            return Destination::orderBy('name')->get();
        }

        return $actor->destinations()->orderBy('name')->get();
    }

    /**
     * @return array<string, string>
     */
    private function availableRoles(): array
    {
        return [
            User::ROLE_MANAGER => 'Manager',
            User::ROLE_GATE_STAFF => 'Gate staff',
        ];
    }
}

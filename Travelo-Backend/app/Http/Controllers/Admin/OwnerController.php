<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Destination;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Admin management of owner accounts: CRUD, activate/deactivate, assign
 * destinations, reset password (temporary), activity logging.
 */
class OwnerController extends Controller
{
    public function index(Request $request): View
    {
        $query = User::where('role', User::ROLE_OWNER)
            ->with('destinations')
            ->withCount(['destinations', 'createdStaff']);

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $owners = $query->latest()->paginate(15)->appends($request->query());

        return view('admin.owners.index', compact('owners'));
    }

    public function create(): View
    {
        $destinations = Destination::orderBy('name')->get();

        return view('admin.owners.create', compact('destinations'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:255'],
            'destinations' => ['nullable', 'array'],
            'destinations.*' => ['exists:destinations,id'],
        ]);

        // Generate a temporary password shown ONCE to the admin.
        $tempPassword = Str::random(16);

        $owner = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($tempPassword),
            'role' => User::ROLE_OWNER,
            'is_active' => true,
            'created_by' => $request->user()?->id,
            'phone' => $validated['phone'] ?? null,
            'email_verified_at' => now(),
        ]);

        if (! empty($validated['destinations'])) {
            $owner->destinations()->sync($validated['destinations']);
        }

        ActivityLog::log(
            'Owner account created',
            User::class,
            $owner->id,
            null,
            null,
            [
                'owner_id' => $owner->id,
                'destinations' => $validated['destinations'] ?? [],
                'temp_password_shown' => true,
            ]
        );

        return redirect()->route('admin.owners.index')
            ->with('temp_password', $tempPassword)
            ->with('temp_password_owner', $owner->name)
            ->with('success', 'Akun owner berhasil dibuat. Password sementara ditampilkan di bawah.');
    }

    public function edit(User $owner): View
    {
        abort_unless($owner->role === User::ROLE_OWNER, 403);

        $destinations = Destination::orderBy('name')->get();
        $assigned = $owner->destinations->pluck('id')->toArray();

        return view('admin.owners.edit', compact('owner', 'destinations', 'assigned'));
    }

    public function update(Request $request, User $owner): RedirectResponse
    {
        abort_unless($owner->role === User::ROLE_OWNER, 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email,'.$owner->id],
            'phone' => ['nullable', 'string', 'max:255'],
            'destinations' => ['nullable', 'array'],
            'destinations.*' => ['exists:destinations,id'],
        ]);

        $owner->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
        ]);

        $owner->destinations()->sync($validated['destinations'] ?? []);

        ActivityLog::log(
            'Owner account updated',
            User::class,
            $owner->id,
            null,
            null,
            [
                'owner_id' => $owner->id,
                'destinations' => $validated['destinations'] ?? [],
            ]
        );

        return redirect()->route('admin.owners.index')
            ->with('success', 'Akun owner diperbarui.');
    }

    public function toggleActive(User $owner): RedirectResponse
    {
        abort_unless($owner->role === User::ROLE_OWNER, 403);

        $owner->update(['is_active' => ! $owner->is_active]);

        ActivityLog::log(
            $owner->is_active ? 'Owner account activated' : 'Owner account deactivated',
            User::class,
            $owner->id,
            null,
            null,
            ['owner_id' => $owner->id, 'is_active' => $owner->is_active]
        );

        return back()->with('success', 'Status akun owner diperbarui.');
    }

    public function resetPassword(User $owner): RedirectResponse
    {
        abort_unless($owner->role === User::ROLE_OWNER, 403);

        $tempPassword = Str::random(16);
        $owner->update(['password' => Hash::make($tempPassword)]);

        ActivityLog::log(
            'Owner password reset',
            User::class,
            $owner->id,
            null,
            null,
            ['owner_id' => $owner->id, 'temp_password_shown' => true]
        );

        return back()->with('temp_password', $tempPassword)
            ->with('temp_password_owner', $owner->name)
            ->with('success', 'Password diganti. Password sementara ditampilkan di bawah.');
    }

    public function destroy(User $owner): RedirectResponse
    {
        abort_unless($owner->role === User::ROLE_OWNER, 403);

        $owner->destinations()->detach();
        $owner->delete();

        ActivityLog::log(
            'Owner account deleted',
            User::class,
            $owner->id,
            null,
            null,
            ['owner_id' => $owner->id]
        );

        return redirect()->route('admin.owners.index')
            ->with('success', 'Akun owner dihapus.');
    }
}

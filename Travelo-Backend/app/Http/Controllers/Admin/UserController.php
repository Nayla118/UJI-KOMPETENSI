<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Display a listing of users.
     */
    public function index(Request $request)
    {
        $search = $request->input('search', '');

        $users = User::when($search, function ($query) use ($search) {
            $query->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%");
        })
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('admin.users.index', compact('users', 'search'));
    }

    /**
     * Display the specified user.
     */
    public function show(User $user)
    {
        $user->load(['bookings.tourPackage', 'bookings.payment']);

        return view('admin.users.show', compact('user'));
    }

    /**
     * Show the form for editing the specified user.
     */
    public function edit(User $user)
    {
        return view('admin.users.edit', compact('user'));
    }

    /**
     * Update the specified user.
     */
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $user->id,
        ]);

        $user->update($validated);

        return redirect()->route('admin.users.index')
            ->with('success', 'User updated successfully');
    }

    /**
     * Remove the specified user.
     */
    public function destroy(User $user)
    {
        // Check if user has bookings
        if ($user->bookings()->count() > 0) {
            return redirect()->route('admin.users.index')
                ->with('error', 'Cannot delete user with existing bookings');
        }

        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('success', 'User deleted successfully');
    }
}

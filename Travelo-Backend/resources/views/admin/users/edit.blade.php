@extends('layouts.admin')

@section('title', 'Edit User - Travelo Admin')
@section('header', 'Edit User')

@section('content')
<div class="space-y-6">
    <div class="flex justify-between items-center">
        <a href="{{ route('admin.users.index') }}" class="text-primary hover:text-blue-700">
            <i class="fas fa-arrow-left mr-2"></i>Back to Users
        </a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <form action="{{ route('admin.users.update', $user->id) }}" method="POST">
            @csrf
            @method('PATCH')

            <div class="space-y-4">
                <!-- Name -->
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                    <input
                        type="text"
                        name="name"
                        id="name"
                        value="{{ old('name', $user->name) }}"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                        required
                    >
                    @error('name')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Email -->
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input
                        type="email"
                        name="email"
                        id="email"
                        value="{{ old('email', $user->email) }}"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                        required
                    >
                    @error('email')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Firebase UID (Read only) -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Firebase UID</label>
                    <input
                        type="text"
                        value="{{ $user->firebase_uid }}"
                        class="w-full px-4 py-2 border border-gray-200 rounded-lg bg-gray-50 text-gray-500"
                        readonly
                    >
                    <p class="mt-1 text-xs text-gray-500">Firebase UID cannot be changed</p>
                </div>

                <!-- Submit -->
                <div class="flex gap-2 pt-4">
                    <button type="submit" class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-blue-700">
                        Update User
                    </button>
                    <a href="{{ route('admin.users.show', $user->id) }}" class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                        Cancel
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Delete User -->
    <div class="bg-white rounded-xl shadow-sm border border-red-100 p-6">
        <h3 class="text-lg font-semibold text-red-600 mb-4">Danger Zone</h3>
        <p class="text-sm text-gray-600 mb-4">
            @if($user->bookings->count() > 0)
                Cannot delete this user because they have {{ $user->bookings->count() }} bookings.
            @else
                Delete this user from the system. This action cannot be undone.
            @endif
        </p>
        @if($user->bookings->count() == 0)
            <form action="{{ route('admin.users.destroy', $user->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this user?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                    Delete User
                </button>
            </form>
        @endif
    </div>
</div>
@endsection

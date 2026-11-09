@extends('layouts.admin')

@section('title', 'Edit Promo Code - Travelo Admin')

@section('content')
<header class="bg-white sticky top-0 z-20 border-b border-slate-200 px-8 py-4">
    <div class="flex items-center justify-between max-w-5xl mx-auto">
        <div>
            <h2 class="text-2xl font-bold tracking-tight">Edit {{ $promo->code }}</h2>
            <p class="text-sm text-slate-500">Changes apply to future checkouts; existing bookings keep their discount.</p>
        </div>
        <a href="{{ route('admin.promos.show', $promo) }}" class="text-sm font-semibold text-slate-600 hover:text-primary">View usage</a>
    </div>
</header>

<div class="p-8 max-w-5xl mx-auto">
    @if ($errors->any())
        <div class="mb-6 bg-red-50 border border-red-200 text-red-800 px-6 py-4 rounded-xl">
            Please fix the highlighted fields.
        </div>
    @endif

    <form action="{{ route('admin.promos.update', $promo) }}" method="POST">
        @csrf
        @method('PUT')
        @include('admin.promos._form')
    </form>
</div>
@endsection

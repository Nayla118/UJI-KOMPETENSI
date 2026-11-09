@extends('layouts.owner')

@section('title', 'Edit Promo | Travelo')

@section('content')
<header class="bg-white sticky top-0 z-20 border-b border-slate-200 px-8 py-4">
    <div class="max-w-5xl mx-auto">
        <h2 class="text-2xl font-bold tracking-tight">Edit {{ $promo->code }}</h2>
        <p class="text-sm text-slate-500">Perubahan berlaku untuk checkout berikutnya.</p>
    </div>
</header>

<div class="p-8 max-w-5xl mx-auto">
    @include('owner.promos._form')
</div>
@endsection

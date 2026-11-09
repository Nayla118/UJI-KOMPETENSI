@extends('layouts.owner')

@section('title', 'Tambah Staf | Travelo')

@section('content')
<header class="bg-white sticky top-0 z-20 border-b border-slate-200 px-8 py-4">
    <div class="max-w-4xl mx-auto">
        <h2 class="text-2xl font-bold tracking-tight">Tambah Staf</h2>
        <p class="text-sm text-slate-500">Buat akun manager atau gate staff untuk destinasi Anda. Password sementara dibuat otomatis.</p>
    </div>
</header>

<div class="p-8 max-w-4xl mx-auto">
    @include('owner.staff._form')
</div>
@endsection

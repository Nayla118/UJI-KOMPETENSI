@props(['variant' => 'default', 'size' => 'small'])

@php
    $variantClasses = match ($variant) {
        'success' => 'bg-green-50 text-green-700 border border-green-200',
        'warning' => 'bg-amber-50 text-amber-700 border border-amber-200',
        'danger' => 'bg-red-50 text-red-700 border border-red-200',
        'info' => 'bg-blue-50 text-blue-700 border border-blue-200',
        'primary' => 'bg-primary/10 text-primary border border-primary/20',
        'default' => 'bg-slate-50 text-slate-600 border border-slate-200',
        default => 'bg-slate-50 text-slate-600 border border-slate-200',
    };

    $sizeClasses = match ($size) {
        'small' => 'px-2.5 py-0.5 text-xs',
        'medium' => 'px-3 py-1 text-sm',
        'large' => 'px-4 py-1.5 text-base',
        default => 'px-2.5 py-0.5 text-xs',
    };
@endphp

<span
    {{ $attributes->merge([
        'class' => 'inline-flex items-center rounded-full border font-semibold ' . $variantClasses . ' ' . $sizeClasses . ' ' . $attributes->whereStartsWith('class')->first(),
    ]) }}
>
    {{ $slot }}
</span>

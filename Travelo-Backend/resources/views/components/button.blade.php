@props(['type' => 'button', 'variant' => 'primary', 'size' => 'medium', 'loading' => false, 'disabled' => false])

@php
    $variantClasses = match ($variant) {
        'primary' => 'bg-primary hover:bg-primary-dark text-white shadow-sm',
        'secondary' => 'bg-white hover:bg-blush border border-slate-200 text-ink shadow-sm',
        'danger' => 'bg-red-50 hover:bg-red-100 text-red-700 border border-red-200 shadow-sm',
        'ghost' => 'hover:bg-blush text-slate-600 shadow-sm',
        default => 'bg-primary hover:bg-primary-dark text-white shadow-sm',
    };

    $sizeClasses = match ($size) {
        'small' => 'min-h-[36px] px-3 text-sm',
        'medium' => 'min-h-[48px] px-4 py-2.5 text-sm',
        'large' => 'min-h-[56px] px-6 py-3 text-base',
        default => 'min-h-[48px] px-4 py-2.5 text-sm',
    };
@endphp

<button
    {{ $attributes->merge([
        'type' => $type,
        'disabled' => $disabled || $loading,
        'class' => 'inline-flex items-center justify-center gap-2 rounded-xl font-semibold ' . $variantClasses . ' ' . $sizeClasses . ' transition-colors disabled:opacity-50 disabled:cursor-not-allowed ' . $attributes->whereStartsWith('class')->first(),
    ]) }}
>
    @if($loading)
        <span class="material-symbols-outlined animate-spin">refresh</span>
    @endif
    {{ $slot }}
</button>

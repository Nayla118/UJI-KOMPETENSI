@props(['title' => null, 'subtitle' => null, 'padding' => 'p-6', 'bordered' => true])

<div
    {{ $attributes->merge([
        'class' => 'rounded-xl bg-white shadow-sm ' . ($bordered ? 'border border-slate-200' : '') . ' ' . $attributes->whereStartsWith('class')->first(),
    ]) }}
>
    @if($title || $subtitle)
        <div class="{{ $padding }} {{ $padding === 'p-6' ? 'pb-4' : 'pb-3' }} border-b border-slate-100">
            @if($title)
                <h3 class="text-lg font-bold text-ink">{{ $title }}</h3>
            @endif
            @if($subtitle)
                <p class="mt-1 text-sm text-slate-500">{{ $subtitle }}</p>
            @endif
        </div>
    @endif

    <div class="{{ $padding }}">
        {{ $slot }}
    </div>
</div>

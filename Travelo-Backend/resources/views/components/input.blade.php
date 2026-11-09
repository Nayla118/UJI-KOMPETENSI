@props(['type' => 'text', 'label' => null, 'helper' => null, 'error' => null, 'placeholder' => null])

<div class="space-y-2">
    @if($label)
        <label for="{{ $attributes->whereStartsWith('id')->first() ?? $attributes->get('name') }}" class="block text-sm font-semibold text-slate-700">{{ $label }}</label>
    @endif

    <input
        {{ $attributes->merge([
            'type' => $type,
            'placeholder' => $placeholder,
            'class' => 'w-full min-h-[48px] px-4 py-3 bg-white border rounded-xl text-ink placeholder:text-slate-400 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors outline-none ' .
                ($error ? 'border-red-500 focus:ring-red-500/20 focus:border-red-500' : 'border-slate-200') . ' ' .
                $attributes->whereStartsWith('class')->first(),
        ]) }}
    >

    @if($error)
        <p class="text-xs text-red-600">{{ $error }}</p>
    @elseif($helper)
        <p class="text-xs text-slate-400">{{ $helper }}</p>
    @endif
</div>

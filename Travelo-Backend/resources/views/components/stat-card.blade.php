@props(['title', 'value', 'icon' => null, 'subtitle' => null, 'variant' => 'default', 'trend' => null])

@php
    $variantClasses = match ($variant) {
        'primary' => 'border-primary bg-primary/10',
        'success' => 'border-green-500 bg-green-50',
        'warning' => 'border-amber-500 bg-amber-50',
        'danger' => 'border-red-500 bg-red-50',
        'default' => 'border-slate-200 bg-white',
        default => 'border-slate-200 bg-white',
    };

    $trendIcon = match ($trend?['direction'] ?? null) {
        'up' => 'trending_up',
        'down' => 'trending_down',
        default => null,
    };

    $trendColor = match ($trend?['direction'] ?? null) {
        'up' => 'text-green-600',
        'down' => 'text-red-600',
        default => 'text-slate-400',
    };
@endphp

<div class="rounded-xl {{ $variantClasses }} p-5 shadow-sm">
    <div class="flex items-start justify-between mb-4">
        <div>
            @if($icon)
                <div class="flex size-8 items-center justify-center rounded-lg bg-ink/5 mb-2">
                    <span class="material-symbols-outlined text-[20px] text-ink">{{ $icon }}</span>
                </div>
            @endif
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">{{ $title }}</p>
            <p class="text-2xl font-bold text-ink mt-1">{{ $value }}</p>
            @if($subtitle)
                <p class="text-xs text-slate-400 mt-1">{{ $subtitle }}</p>
            @endif
        </div>
        @if($trendIcon)
            <div class="flex items-center gap-1 px-2 py-1 rounded-lg {{ $trendColor }} bg-{{ str_replace('text-', '', $trendColor) }}/10">
                <span class="material-symbols-outlined text-[14px]">{{ $trendIcon }}</span>
                <span class="text-xs font-semibold">{{ $trend['value'] ?? '' }}</span>
            </div>
        @endif
    </div>
</div>

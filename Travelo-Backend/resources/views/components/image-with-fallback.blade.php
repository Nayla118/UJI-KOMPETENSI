@props([
    'src' => null,
    'alt' => 'Image',
    'type' => 'destination', // 'destination' or 'tour-package'
    'class' => '',
    'containerClass' => '',
    'size' => 'medium' // 'small', 'medium', 'large'
])

@php
    $sizeClasses = [
        'small' => 'w-12 h-12',
        'medium' => 'w-14 h-14',
        'large' => 'w-32 h-32'
    ];
    $containerClasses = $sizeClasses[$size] ?? $sizeClasses['medium'];
    
    // Determine placeholder image based on type
    $placeholder = $type === 'tour-package' 
        ? asset('images/placeholders/tour-package-placeholder.svg')
        : asset('images/placeholders/destination-placeholder.svg');
    
    // Build image URL
    $imageUrl = null;
    if ($src) {
        // Check if it's already a full URL
        if (str_starts_with($src, 'http')) {
            $imageUrl = $src;
        } else {
            $imageUrl = asset('storage/' . $src);
        }
    }
@endphp

<div class="{{ $containerClasses }} rounded-lg bg-slate-100 overflow-hidden flex-shrink-0 {{ $containerClass }}">
    @if($imageUrl)
        <img 
            src="{{ $imageUrl }}" 
            alt="{{ $alt }}"
            class="w-full h-full object-cover"
            onerror="this.onerror=null; this.src='{{ $placeholder }}';"
        >
    @else
        <img 
            src="{{ $placeholder }}" 
            alt="{{ $alt }} Placeholder"
            class="w-full h-full object-cover"
        >
    @endif
</div>

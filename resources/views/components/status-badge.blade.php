@props(['type' => 'payment', 'value' => ''])

@php
    $value = $value ?? '';
    
    $classes = match($type) {
        'payment' => match(strtolower($value)) {
            'paid' => 'bg-gray-100 text-black',
            'pending' => 'bg-gray-100 text-gray-700',
            'refunded', 'cancelled' => 'bg-gray-200 text-black',
            default => 'bg-gray-100 text-gray-700',
        },
        'fulfillment' => match(strtolower($value)) {
            'fulfilled' => 'bg-gray-100 text-black',
            'partial' => 'bg-gray-100 text-gray-700',
            'unfulfilled' => 'bg-gray-200 text-black',
            default => 'bg-gray-100 text-gray-700',
        },
        default => 'bg-gray-100 text-gray-700',
    };

    $label = $value ?: '—';
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {$classes}"]) }}>
    {{ $label }}
</span>

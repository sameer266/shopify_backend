@props(['icon' => 'fas fa-inbox', 'title' => 'No data', 'description' => ''])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center py-12 px-4 text-center']) }}>
    <!-- Icon -->
    <i class="{{ $icon }} text-4xl text-black mb-3"></i>

    <!-- Title -->
    <p class="font-medium text-black text-lg">{{ $title }}</p>

    <!-- Optional description -->
    @if($description)
        <p class="text-sm mt-1 text-gray-600">{{ $description }}</p>
    @endif

    <!-- Slot for extra content -->
    {{ $slot ?? '' }}
</div>

@props(['title', 'value', 'change'])

<div class="bg-white border border-gray-200 p-6 flex flex-col justify-between h-32 rounded-lg">
    <div>
        <p class="text-xs uppercase tracking-wider text-gray-500 mb-1 italic">{{ $title }}</p>
        <p class="text-2xl font-semibold text-black leading-tight">{{ $value }}</p>
    </div>
    
    @if(!is_null($change))
        <div class="flex items-center text-xs font-medium">
            @if($change > 0)
                <span class="text-green-600 flex items-center">
                    <i class="fas fa-arrow-up mr-1 text-[10px]"></i> {{ number_format(abs($change), 1) }}%
                </span>
            @elseif($change < 0)
                <span class="text-red-500 flex items-center">
                    <i class="fas fa-arrow-down mr-1 text-[10px]"></i> {{ number_format(abs($change), 1) }}%
                </span>
            @else
                <span class="text-gray-400">0.0%</span>
            @endif
            <span class="text-gray-400 ml-1 italic">vs Last Year</span>
        </div>
    @endif
</div>

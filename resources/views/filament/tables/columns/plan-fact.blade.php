@php
    $state = $getState();
    // Проверка, чтобы не упало, если state null
    $fact = $state['fact'] ?? 0;
    $plan = $state['plan'] ?? 0;
    $percent = $state['percent'] ?? 0;
    
    $colorClass = $percent >= 100 ? 'bg-green-500' : ($percent >= 50 ? 'bg-yellow-500' : 'bg-red-500');
@endphp

<div class="px-4 py-2 w-full">
    <div class="flex justify-between text-xs mb-1">
        <span class="font-bold text-gray-700 dark:text-gray-200">{{ number_format($fact, 0, '', ' ') }} шт.</span>
        <span class="text-gray-500">из {{ number_format($plan, 0, '', ' ') }}</span>
    </div>
    
    <div class="w-full bg-gray-200 rounded-full h-2.5 dark:bg-gray-700 overflow-hidden">
        <div class="{{ $colorClass }} h-2.5 rounded-full" style="width: {{ min($percent, 100) }}%"></div>
    </div>
    
    <div class="text-[10px] text-right mt-0.5 text-gray-500">
        {{ $percent }}%
    </div>
</div>
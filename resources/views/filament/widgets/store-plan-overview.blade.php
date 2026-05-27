<x-filament-widgets::widget>
    <x-filament::section class="!p-6">
        {{-- Заголовок --}}
        <div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
            <div>
                <h2 class="text-2xl font-bold tracking-tight text-gray-950 dark:text-white">
                    План продаж: {{ mb_convert_case($monthName, MB_CASE_TITLE, 'UTF-8') }}
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Общие показатели по магазину</p>
            </div>
            
            <div class="flex items-center gap-3 px-4 py-2 rounded-xl bg-gray-50 dark:bg-gray-800 border border-gray-100 dark:border-gray-700">
                <span class="text-sm font-medium text-gray-600 dark:text-gray-300">Выполнение:</span>
                <span class="{{ $overall_percent >= 100 ? 'text-green-600' : ($overall_percent >= 70 ? 'text-yellow-600' : 'text-red-600') }} font-black text-xl">
                    {{ $overall_percent }}%
                </span>
            </div>
        </div>

        {{-- Карточки метрик --}}
        <div class="grid grid-cols-1 gap-8 md:grid-cols-3">
            @foreach($metrics as $metric)
                @php
                    $percent = min($metric['percent'], 100);
                    $colorClass = $percent >= 100 ? 'bg-green-500' : ($percent >= 50 ? 'bg-amber-500' : 'bg-danger-500');
                    $bgColor = $percent >= 100 ? 'bg-green-50' : ($percent >= 50 ? 'bg-amber-50' : 'bg-danger-50');
                @endphp
                
                <div class="group p-6 rounded-3xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-sm transition-all hover:shadow-xl hover:border-primary-200 dark:hover:border-primary-800">
                    <div class="flex justify-between items-start mb-6">
                        <span class="text-sm font-semibold text-gray-400 uppercase tracking-wider">{{ $metric['label'] }}</span>
                        <div class="text-xs font-bold px-2 py-0.5 rounded-md bg-gray-100 dark:bg-gray-800 text-gray-600">
                            {{ $metric['percent'] }}%
                        </div>
                    </div>
                    
                    <div class="flex items-baseline gap-1 mb-6">
                        <span class="text-4xl font-extrabold text-gray-950 dark:text-white">
                            {{ number_format($metric['fact'], 0, '', ' ') }}
                        </span>
                        <span class="text-lg font-medium text-gray-400">{{ $metric['unit'] }}</span>
                    </div>

                    {{-- Прогресс бар --}}
                    <div class="relative w-full bg-gray-100 dark:bg-gray-800 rounded-full h-3">
                        <div class="{{ $colorClass }} h-3 rounded-full transition-all duration-1000 ease-out" 
                             style="width: {{ $percent }}%">
                        </div>
                    </div>
                    
                    <div class="mt-3 text-xs text-gray-400 text-right font-medium">
                        План: {{ number_format($metric['plan'], 0, '', ' ') }} {{ $metric['unit'] }}
                    </div>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
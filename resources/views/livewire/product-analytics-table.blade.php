<div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
    
    <header class="flex flex-col gap-4 px-6 py-4 border-b border-gray-200 dark:border-white/10 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h3 class="text-base font-semibold leading-6 text-gray-950 dark:text-white">
                Динамика показателей
            </h3>
        </div>
        <div class="flex items-center gap-3">
            <div class="flex items-center gap-2">
                <input wire:model.live="dateFrom" type="date" class="block rounded-lg border-gray-300 bg-white py-1.5 text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm dark:border-white/10 dark:bg-white/5 dark:text-white">
                <span class="text-gray-400 text-sm">–</span>
                <input wire:model.live="dateTo" type="date" class="block rounded-lg border-gray-300 bg-white py-1.5 text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm dark:border-white/10 dark:bg-white/5 dark:text-white">
            </div>
        </div>
    </header>

    <div class="px-6 py-3 border-b border-gray-200 dark:border-white/10 bg-gray-50/50 dark:bg-white/[0.02] overflow-x-auto">
        <div class="flex flex-wrap gap-x-6 gap-y-2">
            @foreach(['showOpenCard'=>'Переходы','showAddToCart'=>'В корзину','showOrders'=>'Заказы','showBuyouts'=>'Выкупы','showCrCart'=>'CR в корзину','showCrOrder'=>'CR в заказ'] as $key=>$label)
                <label class="inline-flex items-center gap-2 cursor-pointer group select-none">
                    <input wire:model.live="{{ $key }}" type="checkbox" class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:text-primary-500 dark:checked:bg-primary-500 transition">
                    <span class="text-xs font-medium text-gray-600 dark:text-gray-400 group-hover:text-gray-900 dark:group-hover:text-white transition">{{ $label }}</span>
                </label>
            @endforeach
        </div>
    </div>

    <div class="relative overflow-x-auto rounded-b-xl">
        <table class="w-full text-sm text-left rtl:text-right divide-y divide-gray-200 dark:divide-white/5 border-collapse">
            <thead class="bg-gray-50 dark:bg-white/5 text-gray-950 dark:text-white">
                <tr>
                    <th class="sticky left-0 z-20 px-4 py-3 font-semibold bg-gray-50 dark:bg-gray-900 border-r border-gray-200 dark:border-white/5 min-w-[150px] shadow-lg">Метрика</th>
                    <th class="sticky left-[150px] z-20 px-4 py-3 font-bold bg-gray-50 dark:bg-gray-900 border-r border-gray-200 dark:border-white/5 min-w-[100px] text-center shadow-lg text-primary-600 dark:text-primary-400">ИТОГО</th>
                    @foreach($dates as $date)
                        <th class="px-2 py-3 font-medium text-center min-w-[90px] border-r border-gray-200/50 dark:border-white/5">
                            <div class="flex flex-col leading-tight">
                                <span class="text-[10px] text-gray-400 dark:text-gray-500 uppercase">{{ \Carbon\Carbon::parse($date)->translatedFormat('D') }}</span>
                                <span class="text-gray-900 dark:text-white font-semibold">{{ \Carbon\Carbon::parse($date)->format('d.m') }}</span>
                            </div>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-white/5 bg-white dark:bg-gray-900">
                @foreach($tableData as $metricName => $row)
                    @php $isPercent = str_contains($metricName, 'CR') || str_contains($metricName, '%'); @endphp
                    <tr class="hover:bg-gray-50 dark:hover:bg-white/5 transition duration-75 group">
                        
                        <td class="sticky left-0 z-10 px-4 py-3 font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-900 border-r border-gray-200 dark:border-white/5 group-hover:bg-gray-50 dark:group-hover:bg-white/5 shadow-lg">
                            {{ $metricName }}
                        </td>

                        <td class="sticky left-[150px] z-10 px-4 py-3 font-bold text-center text-gray-900 dark:text-white bg-white dark:bg-gray-900 border-r border-gray-200 dark:border-white/5 group-hover:bg-gray-50 dark:group-hover:bg-white/5 shadow-lg">
                            {{ $isPercent ? $row['total'] . '%' : number_format((float)$row['total'], 0, '.', ' ') }}
                        </td>

                        @foreach($dates as $date)
                            @php
                                $cell = $row['days'][$date]; $val = $cell['value']; $diff = $cell['diff']; $isZero = $val == 0;
                            @endphp
                            <td class="px-2 py-2 text-center border-r border-gray-200/50 dark:border-white/5 align-top">
                                <div class="flex flex-col items-center justify-center gap-0.5 h-full">
                                    <span class="text-sm font-semibold {{ $isZero ? 'text-gray-300 dark:text-gray-600' : 'text-gray-900 dark:text-white' }}">
                                        {{ $isPercent ? $val . '%' : number_format((float)$val, 0, '.', ' ') }}
                                    </span>
                                    
                                    @if($diff != 0)
                                        <span class="text-[11px] font-bold {{ $diff > 0 ? 'text-green-600 dark:text-green-500' : 'text-red-600 dark:text-red-500' }}">
                                            {{ $diff > 0 ? '+' : '' }}{{ $isPercent ? round($diff, 1) . '%' : number_format($diff, 0, '.', ' ') }}
                                        </span>
                                    @else
                                        <div class="h-[17px]"></div>
                                    @endif
                                </div>
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
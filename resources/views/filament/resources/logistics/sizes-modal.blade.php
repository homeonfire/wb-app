<div class="overflow-x-auto">
    <table class="w-full text-sm text-left rtl:text-right divide-y divide-gray-200 dark:divide-white/5">
        <thead class="bg-gray-50 dark:bg-white/5 text-gray-900 dark:text-white">
            <tr>
                <th class="px-4 py-3 font-semibold">Размер / Баркод</th>
                <th class="px-4 py-3 text-center">Продаж/день</th>
                <th class="px-4 py-3 text-center">Остаток WB</th>
                <th class="px-4 py-3 text-center text-blue-500">К клиенту</th>
                <th class="px-4 py-3 text-center text-amber-500">От клиента</th>
                <th class="px-4 py-3 text-center">Оборачиваемость</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-white/5 bg-white dark:bg-gray-900">
            @foreach($record->skus as $sku)
                @php
                    // Расчеты (дублируем логику для отображения)
                    $sales30 = $sku->sales()->where('sale_date', '>=', now()->subDays(30))->count();
                    $speed = $sales30 > 0 ? $sales30 / 30 : 0;
                    
                    $wbStock = $sku->warehouseStocks->sum('quantity');
                    $toClient = $sku->warehouseStocks->sum('in_way_to_client');
                    $fromClient = $sku->warehouseStocks->sum('in_way_from_client');
                    
                    $turnover = $speed > 0 ? round($wbStock / $speed) : '∞';
                    
                    // Цвет оборачиваемости
                    $turnoverColor = match(true) {
                        $turnover === '∞' => 'text-gray-400',
                        $turnover < 60 => 'text-green-600 font-bold',
                        $turnover < 100 => 'text-amber-600 font-bold',
                        default => 'text-red-600 font-bold',
                    };
                @endphp
                <tr class="hover:bg-gray-50 dark:hover:bg-white/5">
                    <td class="px-4 py-2">
                        <div class="font-bold text-gray-900 dark:text-white">{{ $sku->tech_size }}</div>
                        <div class="text-xs text-gray-500">{{ $sku->barcode }}</div>
                    </td>
                    <td class="px-4 py-2 text-center">
                        {{ number_format($speed, 2) }}
                    </td>
                    <td class="px-4 py-2 text-center font-medium text-gray-500">
                        {{ $wbStock }}
                    </td>
                    <td class="px-4 py-2 text-center text-blue-600 dark:text-blue-400">
                        {{ $toClient }}
                    </td>
                    <td class="px-4 py-2 text-center text-amber-600 dark:text-amber-400">
                        {{ $fromClient }}
                    </td>
                    <td class="px-4 py-2 text-center {{ $turnoverColor }}">
                        {{ $turnover }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
<div class="overflow-x-auto">
    {{-- Легенда --}}
    <div class="mb-4 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg flex gap-6 text-sm items-center">
        <div>
            <span class="text-gray-500">Товар:</span> 
            <span class="font-bold text-lg ml-1">{{ $record->title }}</span>
        </div>
        <div class="px-3 py-1 bg-white dark:bg-gray-700 rounded border border-gray-200 dark:border-gray-600">
            <span class="text-gray-500">Себестоимость:</span> 
            <span class="font-bold text-gray-900 dark:text-white">{{ number_format($record->cost_price, 0) }} ₽</span>
        </div>
    </div>

    <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400 border-collapse">
        <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
            <tr>
                <th class="px-4 py-3 border-b">Размер</th>
                <th class="px-4 py-3 border-b">Баркод</th>
                
                {{-- Завод --}}
                <th class="px-2 py-3 bg-purple-50 dark:bg-purple-900/20 text-purple-700 dark:text-purple-400 border border-purple-100 text-center">
                    <div class="flex flex-col items-center">
                        <x-heroicon-o-building-office-2 class="w-4 h-4 mb-1"/>
                        <span>Завод</span>
                    </div>
                </th>

                {{-- Карго --}}
                <th class="px-2 py-3 bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-400 border border-blue-100 text-center">
                    <div class="flex flex-col items-center">
                        <x-heroicon-o-globe-alt class="w-4 h-4 mb-1"/>
                        <span>Карго</span>
                    </div>
                </th>

                {{-- Склад --}}
                <th class="px-2 py-3 bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-400 border border-green-100 text-center">
                    <div class="flex flex-col items-center">
                        <x-heroicon-o-home-modern class="w-4 h-4 mb-1"/>
                        <span>Склад</span>
                    </div>
                </th>

                {{-- Путь WB --}}
                <th class="px-2 py-3 bg-yellow-50 dark:bg-yellow-900/20 text-yellow-700 dark:text-yellow-400 border border-yellow-100 text-center">
                    <div class="flex flex-col items-center">
                        <x-heroicon-o-truck class="w-4 h-4 mb-1"/>
                        <span>Путь WB</span>
                    </div>
                </th>

                {{-- FBO Остаток --}}
                <th class="px-4 py-3 text-center border-b font-bold text-gray-900 dark:text-white">
                    На WB
                </th>

                {{-- К клиенту (ВЕРНУЛИ) --}}
                <th class="px-4 py-3 text-center border-b text-blue-600 dark:text-blue-400">
                    К клиенту
                </th>

                <th class="px-4 py-3 text-center border-b">Скор.</th>
            </tr>
        </thead>
        <tbody>
            @foreach($record->skus as $sku)
                <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium">{{ $sku->tech_size ?? '-' }}</td>
                    <td class="px-4 py-3 font-mono text-xs">{{ $sku->barcode }}</td>

                    {{-- Цветные колонки --}}
                    <td class="px-2 py-3 text-center bg-purple-50 dark:bg-purple-900/10 font-bold text-purple-700 border-x border-purple-100">
                        {{ $sku->stock->at_factory ?: '-' }}
                    </td>
                    <td class="px-2 py-3 text-center bg-blue-50 dark:bg-blue-900/10 font-bold text-blue-700 border-x border-blue-100">
                        {{ $sku->stock->in_transit_general ?: '-' }}
                    </td>
                    <td class="px-2 py-3 text-center bg-green-50 dark:bg-green-900/10 font-bold text-green-700 border-x border-green-100">
                        {{ $sku->stock->stock_own ?: '-' }}
                    </td>
                    <td class="px-2 py-3 text-center bg-yellow-50 dark:bg-yellow-900/10 font-bold text-yellow-700 border-x border-yellow-100">
                        {{ $sku->stock->in_transit_to_wb ?: '-' }}
                    </td>

                    {{-- Остатки WB --}}
                    <td class="px-4 py-3 text-center font-bold text-gray-900 dark:text-white">
                        {{ $sku->warehouseStocks->sum('quantity') }}
                    </td>

                    {{-- К Клиенту (ВЕРНУЛИ) --}}
                    <td class="px-4 py-3 text-center font-semibold text-blue-600 dark:text-blue-400">
                        {{ $sku->warehouseStocks->sum('in_way_to_client') }}
                    </td>

                    <td class="px-4 py-3 text-center text-gray-500">
                        {{ round($sku->sales()->where('sale_date', '>=', now()->subDays(30))->count() / 30, 1) }}
                    </td>
                </tr>
            @endforeach
        </tbody>
        
        <tfoot class="border-t-2 border-gray-200 dark:border-gray-600">
            {{-- СТРОКА ШТУКИ --}}
            <tr class="bg-gray-50 dark:bg-gray-700 font-bold">
                <td class="px-4 py-2 text-right" colspan="2">ИТОГО (шт):</td>
                <td class="px-2 py-2 text-center text-purple-700">{{ $record->skus->sum(fn($s) => $s->stock->at_factory) }}</td>
                <td class="px-2 py-2 text-center text-blue-700">{{ $record->skus->sum(fn($s) => $s->stock->in_transit_general) }}</td>
                <td class="px-2 py-2 text-center text-green-700">{{ $record->skus->sum(fn($s) => $s->stock->stock_own) }}</td>
                <td class="px-2 py-2 text-center text-yellow-700">{{ $record->skus->sum(fn($s) => $s->stock->in_transit_to_wb) }}</td>
                <td class="px-4 py-2 text-center text-gray-900 dark:text-white">{{ $record->skus->flatMap->warehouseStocks->sum('quantity') }}</td>
                <td class="px-4 py-2 text-center text-blue-600">{{ $record->skus->flatMap->warehouseStocks->sum('in_way_to_client') }}</td>
                <td></td>
            </tr>
            
            {{-- СТРОКА ДЕНЬГИ --}}
            @if($record->cost_price > 0)
            <tr class="text-xs text-gray-500 bg-white dark:bg-gray-800 border-t border-gray-100">
                <td class="px-4 py-2 text-right" colspan="2">В деньгах:</td>
                <td class="px-2 py-2 text-center">{{ number_format($record->skus->sum(fn($s) => $s->stock->at_factory) * $record->cost_price, 0, '.', ' ') }}</td>
                <td class="px-2 py-2 text-center">{{ number_format($record->skus->sum(fn($s) => $s->stock->in_transit_general) * $record->cost_price, 0, '.', ' ') }}</td>
                <td class="px-2 py-2 text-center">{{ number_format($record->skus->sum(fn($s) => $s->stock->stock_own) * $record->cost_price, 0, '.', ' ') }}</td>
                <td class="px-2 py-2 text-center">{{ number_format($record->skus->sum(fn($s) => $s->stock->in_transit_to_wb) * $record->cost_price, 0, '.', ' ') }}</td>
                <td class="px-4 py-2 text-center">{{ number_format($record->skus->flatMap->warehouseStocks->sum('quantity') * $record->cost_price, 0, '.', ' ') }}</td>
                <td class="px-4 py-2 text-center text-blue-400">{{ number_format($record->skus->flatMap->warehouseStocks->sum('in_way_to_client') * $record->cost_price, 0, '.', ' ') }}</td>
                <td></td>
            </tr>
            @endif
        </tfoot>
    </table>
</div>
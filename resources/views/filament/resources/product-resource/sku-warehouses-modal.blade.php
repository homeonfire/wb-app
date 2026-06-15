<div class="overflow-x-auto">
    <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400 border-collapse">
        <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
            <tr>
                <th class="px-4 py-3 border-b">Склад WB</th>
                
                {{-- FBO Остаток --}}
                <th class="px-4 py-3 text-center border-b font-bold text-gray-900 dark:text-white">
                    На WB (FBO)
                </th>

                {{-- К клиенту --}}
                <th class="px-4 py-3 text-center border-b text-blue-600 dark:text-blue-400">
                    К клиенту
                </th>
            </tr>
        </thead>
        <tbody>
            @forelse($sku->warehouseStocks as $stock)
                <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">
                        {{ $stock->warehouse_name ?? 'Неизвестный склад' }}
                    </td>

                    {{-- Остатки WB --}}
                    <td class="px-4 py-3 text-center font-bold text-gray-900 dark:text-white">
                        {{ $stock->quantity ?? 0 }}
                    </td>

                    {{-- К Клиенту --}}
                    <td class="px-4 py-3 text-center font-semibold text-blue-600 dark:text-blue-400">
                        {{ $stock->in_way_to_client ?? 0 }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="px-4 py-8 text-center text-gray-500">
                        Нет данных об остатках на складах для этого размера.
                    </td>
                </tr>
            @endforelse
        </tbody>
        
        @if($sku->warehouseStocks->isNotEmpty())
        <tfoot class="border-t-2 border-gray-200 dark:border-gray-600">
            <tr class="bg-gray-50 dark:bg-gray-700 font-bold">
                <td class="px-4 py-3 text-right">ИТОГО (шт):</td>
                <td class="px-4 py-3 text-center text-gray-900 dark:text-white">
                    {{ $sku->warehouseStocks->sum('quantity') }}
                </td>
                <td class="px-4 py-3 text-center text-blue-600">
                    {{ $sku->warehouseStocks->sum('in_way_to_client') }}
                </td>
            </tr>
        </tfoot>
        @endif
    </table>
</div>
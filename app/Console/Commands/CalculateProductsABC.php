<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\SaleRaw;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CalculateProductsABC extends Command
{
    protected $signature = 'products:calculate-abc';
    protected $description = 'Расчет выручки, маржи и АВС-анализ товаров за 30 дней';

    public function handle()
    {
        $this->info('Начинаем пересчет метрик...');
        $dateFrom = now()->subDays(30)->format('Y-m-d');

        // 1. Получаем агрегированные данные по всем товарам за 30 дней (ОДНИМ запросом)
        $salesStats = SaleRaw::where('sale_date', '>=', $dateFrom)
            ->selectRaw('nm_id, COALESCE(SUM(price_with_disc), 0) as revenue, COUNT(*) as buyouts_count')
            ->groupBy('nm_id')
            ->get()
            ->keyBy('nm_id');

        $products = Product::all();
        $totalRevenue = 0;

        // 2. Считаем выручку и маржу для каждого товара
        foreach ($products as $product) {
            $stat = $salesStats->get($product->nm_id);
            
            $revenue = $stat ? (float) $stat->revenue : 0;
            $buyouts = $stat ? (int) $stat->buyouts_count : 0;
            
            $cogs = $buyouts * $product->cost_price; // Себестоимость
            $profit = $revenue - $cogs;
            
            $product->revenue_30d = $revenue;
            $product->margin_30d = $revenue > 0 ? round(($profit / $revenue) * 100, 2) : 0;
            
            $totalRevenue += $revenue;
        }

        // 3. АВС-Анализ: Сортируем товары по выручке от большего к меньшему
        $products = $products->sortByDesc('revenue_30d')->values();
        
        $accumulatedRevenue = 0;

        DB::transaction(function () use ($products, $totalRevenue, &$accumulatedRevenue) {
            foreach ($products as $product) {
                if ($totalRevenue > 0) {
                    $accumulatedRevenue += $product->revenue_30d;
                    $percent = $accumulatedRevenue / $totalRevenue;

                    // Классика АВС: 80% - A, 15% - B, 5% - C
                    if ($percent <= 0.80) {
                        $product->abc_class = 'A';
                    } elseif ($percent <= 0.95) {
                        $product->abc_class = 'B';
                    } else {
                        $product->abc_class = 'C';
                    }
                } else {
                    $product->abc_class = 'C';
                }
                
                $product->save();
            }
        });

        $this->info('✅ АВС-анализ и маржа успешно обновлены!');
    }
}
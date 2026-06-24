<?php

namespace App\Services\Reports;

use App\Models\Order;
use App\Models\OrderItem;

class DailySalesReportService
{
    public function generateWithoutBatch(): array
    {
        $start = microtime(true);

        $orders = Order::query()
            ->whereDate('created_at', today())
            ->where('status', 'paid')
            ->with('items:id,order_id,quantity')
            ->get(['id', 'total_price']);

        $totalOrders = $orders->count();
        $totalSales = (float) $orders->sum('total_price');
        $totalItems = (int) $orders->sum(fn (Order $order) => $order->items->sum('quantity'));

        return $this->metrics('before_without_batch', $start, $totalOrders, $totalSales, $totalItems);
    }

    public function generateWithBatch(int $chunkSize = 1000): array
    {
        $start = microtime(true);
        $totalOrders = 0;
        $totalSales = 0.0;
        $totalItems = 0;

        Order::query()
            ->whereDate('created_at', today())
            ->where('status', 'paid')
            ->select(['id', 'total_price'])
            ->chunkById($chunkSize, function ($orders) use (&$totalOrders, &$totalSales, &$totalItems): void {
                $orderIds = $orders->pluck('id');
                $itemsByOrder = OrderItem::query()
                    ->whereIn('order_id', $orderIds)
                    ->selectRaw('order_id, SUM(quantity) as item_count')
                    ->groupBy('order_id')
                    ->pluck('item_count', 'order_id');

                foreach ($orders as $order) {
                    $totalOrders++;
                    $totalSales += (float) $order->total_price;
                    $totalItems += (int) ($itemsByOrder[$order->id] ?? 0);
                }
            });

        $result = $this->metrics('after_chunked', $start, $totalOrders, $totalSales, $totalItems);
        $result['chunk_size'] = $chunkSize;

        return $result;
    }

    private function metrics(string $mode, float $start, int $orders, float $sales, int $items): array
    {
        return [
            'processing_mode' => $mode,
            'execution_time_ms' => (int) round((microtime(true) - $start) * 1000),
            'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'total_processed_orders' => $orders,
            'total_sales' => round($sales, 2),
            'total_items' => $items,
        ];
    }
}
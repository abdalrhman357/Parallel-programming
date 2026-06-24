<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BenchmarkSeedCommand extends Command
{
    protected $signature = 'benchmark:seed
                            {--products=300 : Number of products}
                            {--orders=8000 : Number of orders}
                            {--order-items=10000 : Number of order items}
                            {--reset : Clear benchmark tables before generating}';

    protected $description = 'Generate benchmark data for batch-processing tests';

    public function handle(): int
    {
        $productsCount = max(1, (int) $this->option('products'));
        $ordersCount = max(1, (int) $this->option('orders'));
        $itemsCount = max(1, (int) $this->option('order-items'));

        $this->info('Preparing benchmark dataset...');

        if ((bool) $this->option('reset')) {
            $this->warn('Reset enabled: deleting order_items, orders, products.');
            if (DB::getDriverName() === 'mysql') {
                DB::statement('SET FOREIGN_KEY_CHECKS=0');
                OrderItem::query()->truncate();
                Order::query()->truncate();
                Product::query()->truncate();
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            } else {
                DB::transaction(function (): void {
                    OrderItem::query()->delete();
                    Order::query()->delete();
                    Product::query()->delete();
                });
            }
        }

        Product::factory()->count($productsCount)->create();

        $userIds = User::query()->pluck('id')->all();
        if ($userIds === []) {
            $userIds = User::factory()->count(10)->create()->pluck('id')->all();
        }

        $ordersRows = [];
        for ($i = 0; $i < $ordersCount; $i++) {
            $ordersRows[] = [
                'user_id' => $userIds[array_rand($userIds)],
                'total_price' => 0,
                'status' => random_int(1, 100) <= 80 ? 'paid' : 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (count($ordersRows) === 2000) {
                Order::insert($ordersRows);
                $ordersRows = [];
            }
        }
        if ($ordersRows !== []) {
            Order::insert($ordersRows);
        }

        $products = Product::query()->get(['id', 'price'])->keyBy('id');
        $productIds = $products->keys()->all();

        $minOrderId = (int) Order::query()->min('id');
        $maxOrderId = (int) Order::query()->max('id');

        $itemRows = [];
        for ($i = 0; $i < $itemsCount; $i++) {
            $qty = random_int(1, 5);
            $productId = $productIds[array_rand($productIds)];

            $itemRows[] = [
                'order_id' => random_int($minOrderId, $maxOrderId),
                'product_id' => $productId,
                'quantity' => $qty,
                'price' => (float) $products[$productId]->price,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (count($itemRows) === 2000) {
                OrderItem::insert($itemRows);
                $itemRows = [];
            }
        }
        if ($itemRows !== []) {
            OrderItem::insert($itemRows);
        }

        $this->info('Computing order totals...');
        if (DB::getDriverName() === 'mysql') {
            DB::statement('
                UPDATE orders o
                JOIN (
                    SELECT order_id, SUM(quantity * price) AS t
                    FROM order_items
                    GROUP BY order_id
                ) s ON s.order_id = o.id
                SET o.total_price = s.t
            ');
        } else {
            Order::query()->chunkById(1000, function ($orders): void {
                foreach ($orders as $order) {
                    $total = OrderItem::query()
                        ->where('order_id', $order->id)
                        ->selectRaw('SUM(quantity * price) as total')
                        ->value('total');
                    $order->update(['total_price' => round((float) $total, 2)]);
                }
            });
        }

        $this->info('Benchmark dataset generated successfully.');
        $this->line('Products: '.Product::count());
        $this->line('Orders: '.Order::count());
        $this->line('Order Items: '.OrderItem::count());

        return self::SUCCESS;
    }
}
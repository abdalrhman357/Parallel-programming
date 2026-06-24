<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Models\Product;

class ProductController extends Controller
{
    public const CACHE_ALL     = 'products:all';
    public const CACHE_POPULAR = 'products:popular';
    public const TTL           = 300;

    public function index(Request $request)
    {
        if ($request->boolean('fresh')) {
            $products = Product::query()
                ->select(['id', 'name', 'description', 'price', 'stock_quantity'])
                ->get();

            return response()->json($products)->header('X-Cache', 'BYPASS');
        }

        $wasCached = Cache::has(self::CACHE_ALL);

        $products = Cache::remember(self::CACHE_ALL, self::TTL, function () {
            return Product::query()
                ->select(['id', 'name', 'description', 'price', 'stock_quantity'])
                ->get();
        });

        return response()->json($products)->header('X-Cache', $wasCached ? 'HIT' : 'MISS');
    }

    public function popular()
    {
        $popular = Cache::remember(self::CACHE_POPULAR, self::TTL * 2, function () {
            return Product::query()
                ->select('products.id', 'products.name', 'products.price')
                ->selectRaw('COALESCE(SUM(order_items.quantity), 0) as total_sold')
                ->leftJoin('order_items', 'order_items.product_id', '=', 'products.id')
                ->groupBy('products.id', 'products.name', 'products.price')
                ->orderByDesc('total_sold')
                ->limit(10)
                ->get();
        });

        return response()->json($popular);
    }

    public static function flushCatalogCache(): void
    {
        Cache::forget(self::CACHE_ALL);
        Cache::forget(self::CACHE_POPULAR);
    }

    public function benchmark(Request $request)
    {
        $iterations = (int) $request->query('iterations', 50);
        $iterations = max(1, min($iterations, 500));

        $cacheKey = 'benchmark_catalog_stats';

        // العملية المكلفة (الاختناق): جلب كل المنتجات + حساب إحصائيات ثقيلة في كل تكرار
        $heavyComputation = function () {
            $products = Product::query()->get(['id', 'name', 'price', 'stock_quantity']);

            $totalValue = 0.0;
            $totalStock = 0;
            $buckets = ['cheap' => 0, 'medium' => 0, 'expensive' => 0];

            foreach ($products as $p) {
                $totalValue += (float) $p->price * (int) $p->stock_quantity;
                $totalStock += (int) $p->stock_quantity;
                if ($p->price < 100) {
                    $buckets['cheap']++;
                } elseif ($p->price < 300) {
                    $buckets['medium']++;
                } else {
                    $buckets['expensive']++;
                }
            }

            return [
                'count'         => $products->count(),
                'total_value'   => round($totalValue, 2),
                'total_stock'   => $totalStock,
                'avg_price'     => $products->count() ? round($products->avg('price'), 2) : 0,
                'price_buckets' => $buckets,
            ];
        };

        // قبل التحسين: الحساب الثقيل على قاعدة البيانات في كل تكرار
        Cache::forget($cacheKey);
        $startBefore = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $heavyComputation();
        }
        $beforeMs = (microtime(true) - $startBefore) * 1000;

        // بعد التحسين: التكرار الأول يحسب ويخزّن النتيجة الخفيفة، والبقية من الكاش
        Cache::forget($cacheKey);
        $startAfter = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            Cache::remember($cacheKey, 60, $heavyComputation);
        }
        $afterMs = (microtime(true) - $startAfter) * 1000;

        $improvement = $beforeMs > 0
            ? round((($beforeMs - $afterMs) / $beforeMs) * 100, 2)
            : 0;

        return response()->json([
            'operation'             => 'product_catalog_statistics',
            'iterations'            => $iterations,
            'products_processed'    => Product::count(),
            'bottleneck_identified' => 'Full catalog statistics calculation (fetching and processing all products) is repeated for every request',            'before_cache' => [
                'total_ms' => round($beforeMs, 2),
                'avg_ms'   => round($beforeMs / $iterations, 4),
                'source'   => 'database + computation every time',
            ],
            'after_cache' => [
                'total_ms' => round($afterMs, 2),
                'avg_ms'   => round($afterMs / $iterations, 4),
                'source'   => 'cache (memory) after first miss',
            ],
            'improvement_percent' => $improvement,
        ]);
    }
}

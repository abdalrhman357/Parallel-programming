<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateInvoiceJob;
use App\Jobs\SendOrderNotificationJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\Cache\LockTimeoutException;
use App\Models\Order;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SafeOrderController extends Controller
{
    public function checkoutWithLock(Request $request)
    {


        $userId = (int) $request->input('user_id', 1);
        $user   = User::findOrFail($userId);

        $cart  = Cart::where('user_id', $user->id)->firstOrFail();
        $items = CartItem::where('cart_id', $cart->id)->get();

        if ($items->isEmpty()) {
            return response()->json(['message' => 'Cart is empty'], 400);
        }

        $total = 0;
        foreach ($items as $item) {
            $product = Product::findOrFail($item->product_id);
            $total += $product->price * $item->quantity;
        }

        if ($user->wallet_balance < $total) {
            return response()->json(['message' => 'Not enough balance'], 400);
        }

        


        $productIds = $items->pluck('product_id')->unique()->sort()->values();

        $locks = [];

        try {


            foreach ($productIds as $pid) {
                $lock = Cache::lock('product_'.$pid, 10);
                $lock->block(5);
                $locks[$pid] = $lock;
            }


            DB::beginTransaction();


            $user->refresh();
            $products = Product::whereIn('id', $productIds)->lockForUpdate()->get()->keyBy('id');

            foreach ($items as $item) {
                $product = $products[$item->product_id];
                if ($product->stock_quantity < $item->quantity) {
                    throw new \RuntimeException('Out of stock for product '.$product->id);
                }
            }


            if ($user->wallet_balance < $total) {
                throw new \RuntimeException('Not enough balance');
            }

            foreach ($items as $item) {
                $product = $products[$item->product_id];
                $product->stock_quantity -= $item->quantity;
                $product->save();
            }

            $user->wallet_balance -= $total;
            $user->save();

            $order = Order::create([
                'user_id'     => $user->id,
                'total_price' => $total,
                'status'      => 'paid',
            ]);

            foreach ($items as $item) {
                OrderItem::create([
                    'order_id'   => $order->id,
                    'product_id' => $item->product_id,
                    'quantity'   => $item->quantity,
                    'price'      => $products[$item->product_id]->price,
                ]);
            }

            DB::commit();



            ProductController::flushCatalogCache();


            GenerateInvoiceJob::dispatch($order->id, $user->id, $total, $order->status);
            SendOrderNotificationJob::dispatch($order->id, $user->id, $user->name, $total);

            return response()->json([
                'message'          => 'Order created successfully',
                'order_id'         => $order->id,
                'total_orders'     => Order::count(),
                'background_tasks' => [
                    'invoice'      => 'queued',
                    'notification' => 'queued',
                ],
            ]);

        } catch (LockTimeoutException $e) {

            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            return response()->json([
                'message' => 'System is busy, please retry.',
            ], 503);

        } catch (\Throwable $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            return response()->json([
                'message' => 'Something went wrong',
                'error'   => $e->getMessage(),
            ], 400);

        } finally {

            foreach ($locks as $lock) {
                optional($lock)->release();
            }
        }
    }
}

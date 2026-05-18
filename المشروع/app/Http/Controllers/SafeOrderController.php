<?php

namespace App\Http\Controllers;
use App\Jobs\GenerateInvoiceJob;
use App\Jobs\SendOrderNotificationJob;
use Illuminate\Support\Facades\Cache;
use App\Models\Order;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
class SafeOrderController extends Controller
{
    public function checkoutWithLock()
{
    $user = User::find(1);

    $cart = Cart::where('user_id', $user->id)->firstOrFail();
    $items = CartItem::where('cart_id', $cart->id)->get();

    $total = 0;

    foreach ($items as $item) {
        $product = Product::findOrFail($item->product_id);
        $total += $product->price * $item->quantity;
    }

    if ($user->wallet_balance < $total) {
        return response()->json([
            'message' => 'Not enough balance'
        ], 400);
    }

    DB::beginTransaction();

    try {

        $locks = [];

        foreach ($items as $item) {
            $locks[$item->product_id] = Cache::lock('product_'.$item->product_id, 10);
            $locks[$item->product_id]->block(5);
        }

        foreach ($items as $item) {

            $product = Product::findOrFail($item->product_id);
            $product->refresh();

            if ($product->stock_quantity < $item->quantity) {
                throw new \Exception('Out of stock for product '.$product->id);
            }
        }

        foreach ($items as $item) {

            $product = Product::find($item->product_id);
            $product->stock_quantity -= $item->quantity;
            $product->save();
        }

        $user->wallet_balance -= $total;
        $user->save();

        $order = Order::create([
            'user_id' => $user->id,
            'total_price' => $total,
            'status' => 'paid'
        ]);

        foreach ($items as $item) {
            $product = Product::find($item->product_id);

            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => $item->quantity,
                'price' => $product->price
            ]);
        }


        // CartItem::where('cart_id', $cart->id)->delete();

        DB::commit();
        GenerateInvoiceJob::dispatch(
                $order->id,
                $user->id,
                $total,
                $order->status
            );
        SendOrderNotificationJob::dispatch(
                $order->id,
                $user->id,
                $user->name,
                $total
            );
       return response()->json([
        'message' => 'Order created successfully',
        'order_id' => $order->id,
        'total_orders' => Order::count(),
        'remaining_stock' => $product->stock_quantity,
                    'background_tasks' => [
                    'invoice' => 'queued',
                    'notification' => 'queued',
                ]
    ]);

    } catch (\Exception $e) {

        DB::rollBack();

        return response()->json([
            'message' => 'Something went wrong',
            'error' => $e->getMessage()
        ], 500);

    } finally {

        if (!empty($locks)) {
            foreach ($locks as $lock) {
                optional($lock)->release();
            }
        }
    }
}
}

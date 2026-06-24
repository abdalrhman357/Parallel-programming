<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateInvoiceJob;
use App\Jobs\SendOrderNotificationJob;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;


class QueueDemoController extends Controller
{
    private function createOrder(int $userId): array
    {
        $user = User::findOrFail($userId);
        $cart = Cart::where('user_id', $user->id)->first();

        $total = 100;
        if ($cart) {
            $items = CartItem::where('cart_id', $cart->id)->get();
            $total = 0;
            foreach ($items as $item) {
                $product = Product::find($item->product_id);
                if ($product) {
                    $total += $product->price * $item->quantity;
                }
            }
            if ($total <= 0) {
                $total = 100;
            }
        }

        $order = Order::create([
            'user_id'     => $user->id,
            'total_price' => $total,
            'status'      => 'paid',
        ]);

        return [$user, $order, $total];
    }

    public function sync(Request $request)
    {
        $userId = (int) $request->input('user_id', 1);
        [$user, $order, $total] = $this->createOrder($userId);

        (new GenerateInvoiceJob($order->id, $user->id, $total, $order->status))->handle();
        (new SendOrderNotificationJob($order->id, $user->id, $user->name, $total))->handle();

        return response()->json([
            'message'  => 'Order created (synchronous)',
            'order_id' => $order->id,
            'mode'     => 'sync',
            'invoice'      => 'done',
            'notification' => 'done',
        ]);
    }

    public function async(Request $request)
    {
        $userId = (int) $request->input('user_id', 1);
        [$user, $order, $total] = $this->createOrder($userId);

        GenerateInvoiceJob::dispatch($order->id, $user->id, $total, $order->status);
        SendOrderNotificationJob::dispatch($order->id, $user->id, $user->name, $total);

        return response()->json([
            'message'  => 'Order created (asynchronous)',
            'order_id' => $order->id,
            'mode'     => 'async',
            'background_tasks' => [
                'invoice'      => 'queued',
                'notification' => 'queued',
            ],
        ]);
    }
}

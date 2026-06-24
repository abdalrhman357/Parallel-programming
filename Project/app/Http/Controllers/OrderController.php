<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\OrderItem;
use App\Models\User;

class OrderController extends Controller
{
    /**
     * نسخة "قبل الحماية" — تُستخدم لإظهار مشكلة Race Condition في القياس.
     * تستقبل user_id حتى تضرب المحاكاة سلالاً مختلفة (المتطلب التاسع).
     */
    public function checkout(Request $request)
    {
        $userId = (int) $request->input('user_id', 1);
        $user = User::find($userId);

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $cart = Cart::where('user_id', $user->id)->first();
        if (!$cart) {
            return response()->json(['message' => 'Cart not found'], 400);
        }

        $items = CartItem::where('cart_id', $cart->id)->get();

        $total = 0;
        foreach ($items as $item) {
            $product = Product::find($item->product_id);
            if ($product->stock_quantity < $item->quantity) {
                return response()->json([
                    'message' => 'Out of stock for product ' . $product->id
                ], 400);
            }


            $total += $product->price * $item->quantity;
        }

        if ($user->wallet_balance < $total) {
            return response()->json(['message' => 'Not enough balance'], 400);
        }

        $user->wallet_balance -= $total;
        $user->save();

        $order = Order::create([
            'user_id' => $user->id,
            'total_price' => $total,
            'status' => 'paid',
        ]);

        foreach ($items as $item) {
            $product = Product::find($item->product_id);

            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => $item->quantity,
                'price' => $product->price,
            ]);
            $product->stock_quantity -= $item->quantity;
            $product->save();
        }

        return response()->json([
            'message' => 'Order created successfully',
            'order_id' => $order->id,
            'total_orders' => Order::count(),
        ]);
    }
}

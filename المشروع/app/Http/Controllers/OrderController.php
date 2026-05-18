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
    public function checkout()
{
    $user = User::find(1);

    $cart = Cart::where('user_id', $user->id)->first();
    $items = CartItem::where('cart_id', $cart->id)->get();

    $total = 0;

    foreach ($items as $item) {
        $product = Product::find($item->product_id);
        $total += $product->price * $item->quantity;
    }

    if ($user->wallet_balance < $total) {
        return response()->json(['message' => 'Not enough balance'], 400);
    }

    $user->wallet_balance -= $total;
    $user->save();

    // إنشاء الطلب
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

        $product->stock_quantity -= $item->quantity;
        $product->save();
    }

    // CartItem::where('cart_id', $cart->id)->delete();

        return response()->json([
            'message' => 'Order created successfully',
            'order_id' => $order->id,
            'total_orders' => Order::count(),
            'remaining_stock' => $product->stock_quantity
        ]);
}
}

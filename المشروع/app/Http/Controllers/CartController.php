<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cart;
use App\Models\CartItem;
class CartController extends Controller
{
    public function addToCart(Request $request)
        {
            $cart = Cart::firstOrCreate([
                'user_id' => auth()->id()
            ]);

            $item = CartItem::create([
                'cart_id' => $cart->id,
                'product_id' => $request->product_id,
                'quantity' => $request->quantity
            ]);

            return response()->json($item);
        }
}

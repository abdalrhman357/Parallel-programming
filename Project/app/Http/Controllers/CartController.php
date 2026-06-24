<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;

class CartController extends Controller
{
    public function addToCart(Request $request)
    {
        // يقبل user_id من الطلب (للمحاكاة)، أو المستخدم المسجّل، أو 1 افتراضياً.
        // هذا يمنع خطأ 500 عندما لا يوجد تسجيل دخول (auth()->id() = null).
        $userId = (int) ($request->input('user_id') ?? auth()->id() ?? 1);

        // تأكد أن المنتج موجود قبل الإضافة (يمنع خطأ foreign key = 500).
        $productId = (int) $request->input('product_id');
        if (! Product::where('id', $productId)->exists()) {
            return response()->json(['message' => 'Product not found'], 400);
        }

        $cart = Cart::firstOrCreate(['user_id' => $userId]);

        $item = CartItem::create([
            'cart_id'    => $cart->id,
            'product_id' => $productId,
            'quantity'   => max(1, (int) $request->input('quantity', 1)),
        ]);

        return response()->json($item, 201);
    }
}
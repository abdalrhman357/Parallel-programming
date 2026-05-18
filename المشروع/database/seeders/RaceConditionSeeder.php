<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Product;
use App\Models\Cart;
use App\Models\CartItem;

class RaceConditionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        CartItem::query()->delete();
        Cart::query()->delete();
        Product::query()->delete();
        User::query()->delete();

        // مستخدم واحد
        $user = User::create([
            'id' => 1,
            'name' => 'Test User',
            'email' => 'test@test.com',
            'password' => bcrypt('123456'),
            'wallet_balance' => 10000000,
        ]);

        // منتج واحد (المهم في التجربة)
        $product = Product::create([
            'name' => 'iPhone 15',
            'price' => 100,
            'stock_quantity' => 10,
        ]);

        // سلة واحدة
        $cart = Cart::create([
            'user_id' => $user->id,
        ]);

        // عنصر واحد في السلة
        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);
    }
}

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

    // منتج واحد يتنافس عليه الجميع
    $product = Product::create([
        'name' => 'iPhone 15',
        'price' => 100,
        'stock_quantity' => 5, // جرّب 5 أو 1 حسب شدة الاختبار
    ]);

    // إنشاء 10 مستخدمين
    for ($i = 1; $i <= 10; $i++) {

        $user = User::create([
            'name' => "User {$i}",
            'email' => "user{$i}@test.com",
            'password' => bcrypt('123456'),
            'wallet_balance' => 1000000,
        ]);

        // سلة لكل مستخدم
        $cart = Cart::create([
            'user_id' => $user->id,
        ]);

        // الجميع يريد نفس المنتج
        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);
    }
}
}

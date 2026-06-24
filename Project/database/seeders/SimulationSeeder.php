<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Product;
use App\Models\Cart;
use App\Models\CartItem;
use Illuminate\Support\Facades\Hash;

class SimulationSeeder extends Seeder
{
    public function run(): void
    {
        CartItem::query()->delete();
        Cart::query()->delete();
        Product::query()->delete();
        User::query()->delete();

        // منتجات بمخزون مرتفع (ضمن حدود الأعمدة)
        $products = [];
        for ($i = 1; $i <= 10; $i++) {
            $products[$i] = Product::create([
                'name' => "Product {$i}",
                'description' => "Simulation product number {$i}",
                'price' => rand(50, 500),
                'stock_quantity' => 1000000, // مخزون كبير وكافٍ
            ]);
        }

        // 10 مستخدمين، كل واحد له سلّة وعنصران
        for ($u = 1; $u <= 10; $u++) {
            $user = User::create([
                'id' => $u,
                'name' => "User {$u}",
                'email' => "user{$u}@sim.test",
                'password' => Hash::make('password'),
                'wallet_balance' => 1000000, // ✅ ضمن decimal(10,2)
            ]);

            $cart = Cart::create(['user_id' => $user->id]);

            CartItem::create([
                'cart_id' => $cart->id,
                'product_id' => $products[$u]->id,
                'quantity' => 1,
            ]);

            $secondProduct = $products[($u % 10) + 1];
            CartItem::create([
                'cart_id' => $cart->id,
                'product_id' => $secondProduct->id,
                'quantity' => 1,
            ]);
        }

        $this->command->info('SimulationSeeder: 10 users, 10 products, carts ready.');
    }
}
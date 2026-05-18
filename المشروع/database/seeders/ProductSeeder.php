<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Product;
class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Product::create([
            'name' => 'iPhone 14',
            'description' => 'Apple smartphone',
            'price' => 3000,
            'stock_quantity' => 10
        ]);

        Product::create([
            'name' => 'Samsung S23',
            'description' => 'Samsung smartphone',
            'price' => 2500,
            'stock_quantity' => 15
        ]);

        Product::create([
            'name' => 'Laptop Dell',
            'description' => 'Dell laptop',
            'price' => 4000,
            'stock_quantity' => 5
        ]);
    }
}

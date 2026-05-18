<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
                User::create([
            'name' => 'Ali',
            'email' => 'ali@test.com',
            'password' => Hash::make('123456'),
            'wallet_balance' => 100000
        ]);

        User::create([
            'name' => 'Sara',
            'email' => 'sara@test.com',
            'password' => Hash::make('123456'),
            'wallet_balance' => 500
        ]);

        User::create([
            'name' => 'Omar',
            'email' => 'omar@test.com',
            'password' => Hash::make('123456'),
            'wallet_balance' => 200000
        ]);
    }
}

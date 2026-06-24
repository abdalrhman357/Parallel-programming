<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

\DB::listen(function($query) {
    echo $query->sql . "\n";
    print_r($query->bindings);
});

$p = \App\Models\Product::first();
$p->stock_quantity -= 1;
$p->save();

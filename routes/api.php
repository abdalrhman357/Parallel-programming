<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\SafeOrderController;
use App\Http\Controllers\DailySalesReportController;
use App\Http\Controllers\SystemStatusController;
use App\Http\Controllers\BenchmarkController;
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/products', [ProductController::class, 'index']);
Route::post('/cart/add', [CartController::class, 'addToCart']);
Route::post('/checkout', [OrderController::class, 'checkout']);
Route::post('/checkoutWithLock', [SafeOrderController::class, 'checkoutWithLock'])
    ->middleware(['rate.limit', 'concurrency']);
Route::get('/reports/daily-sales/before', [DailySalesReportController::class, 'before']);
Route::get('/reports/daily-sales/after', [DailySalesReportController::class, 'after']);
Route::post('/reports/daily-sales/job', [DailySalesReportController::class, 'dispatch']);
Route::post('/benchmark/seed', [BenchmarkController::class, 'seed']);
Route::get('/system/server-info', [SystemStatusController::class, 'serverInfo']);

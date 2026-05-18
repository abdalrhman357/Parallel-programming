<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;

class BenchmarkController extends Controller
{
    public function seed(): JsonResponse
    {
        Artisan::call('benchmark:seed');

        return response()->json([
            'message' => 'Benchmark seed command executed',
            'output' => Artisan::output(),
        ]);
    }
}
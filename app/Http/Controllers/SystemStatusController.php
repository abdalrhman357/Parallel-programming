<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SystemStatusController extends Controller
{
    public function serverInfo(Request $request): JsonResponse
    {
        return response()->json([
            'app_instance' => env('APP_INSTANCE', 'laravel-node-unknown'),
            'server_port' => $request->getPort(),
            'process_id' => getmypid(),
            'hostname' => gethostname(),
            'timestamp' => now()->toIso8601String(),
            'request_id' => $request->header('X-Request-ID', (string) Str::uuid()),
        ]);
    }
}
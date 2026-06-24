<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PerformanceMonitor
{
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage();


        $cpuStart = getrusage();

        $response = $next($request);

        $executionTime = (microtime(true) - $startTime) * 1000; // بالملي ثانية
        $memoryUsed = (memory_get_usage() - $startMemory) / 1024 / 1024; // بالميجابايت

        $cpuEnd = getrusage();
        $cpuTime = ($cpuEnd['ru_utime.tv_sec'] + $cpuEnd['ru_utime.tv_usec'] / 1000000) -
                   ($cpuStart['ru_utime.tv_sec'] + $cpuStart['ru_utime.tv_usec'] / 1000000);

        $response->headers->set('X-Server-Time-Ms', round($executionTime, 2));
        $response->headers->set('X-Server-Memory-Mb', round($memoryUsed, 4));
        $response->headers->set('X-Server-CPU-Usage', round($cpuTime, 4));

        return $response;
    }
}

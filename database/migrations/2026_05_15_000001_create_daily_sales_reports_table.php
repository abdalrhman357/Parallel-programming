<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_sales_reports', function (Blueprint $table) {
            $table->id();
            $table->date('report_date')->index();
            $table->unsignedBigInteger('total_orders')->default(0);
            $table->decimal('total_sales', 14, 2)->default(0);
            $table->unsignedBigInteger('total_items')->default(0);
            $table->string('processing_mode', 40);
            $table->unsignedInteger('execution_time_ms');
            $table->decimal('memory_peak_mb', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_sales_reports');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_usage_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('platform_services')->cascadeOnDelete();
            $table->char('month', 7);
            $table->unsignedInteger('quantity')->default(0);
            $table->decimal('unit_cost', 10, 4);
            $table->decimal('total_cost', 10, 2);
            $table->timestamps();

            $table->unique(['clinic_id', 'service_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_usage_records');
    }
};

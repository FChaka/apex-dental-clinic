<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('platform_services')->cascadeOnDelete();
            $table->boolean('is_enabled')->default(true);
            $table->decimal('unit_price_override', 10, 4)->nullable();
            $table->decimal('flat_price_override', 10, 2)->nullable();
            $table->unsignedInteger('monthly_quota')->nullable();
            $table->date('enabled_at');
            $table->date('disabled_at')->nullable();
            $table->timestamps();

            $table->unique(['clinic_id', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_services');
    }
};

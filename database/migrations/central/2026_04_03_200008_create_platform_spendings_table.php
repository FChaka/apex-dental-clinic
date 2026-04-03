<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_spendings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('platform_cost_categories')->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('platform_services')->nullOnDelete();
            $table->char('month', 7);
            $table->decimal('amount', 10, 2);
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_spendings');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planner_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('planner_categories')->cascadeOnDelete();
            $table->string('name', 255);
            $table->decimal('default_price', 10, 2);
            $table->foreignId('treatment_type_id')->nullable()->constrained('treatment_types')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planner_materials');
    }
};

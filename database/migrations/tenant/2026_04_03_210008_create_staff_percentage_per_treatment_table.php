<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_percentage_per_treatment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff_members')->cascadeOnDelete();
            $table->foreignId('treatment_type_id')->constrained('treatment_types')->cascadeOnDelete();
            $table->decimal('percentage', 5, 2);

            $table->unique(['staff_id', 'treatment_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_percentage_per_treatment');
    }
};

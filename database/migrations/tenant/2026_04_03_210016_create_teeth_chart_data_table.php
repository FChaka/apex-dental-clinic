<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teeth_chart_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->string('tooth_number', 10);
            $table->enum('procedure', ['Filling', 'Crown', 'Extraction', 'Root Canal', 'Implant'])->nullable();
            $table->boolean('is_initial_exam')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['patient_id', 'tooth_number', 'is_initial_exam']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teeth_chart_data');
    }
};

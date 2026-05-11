<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teeth_chart_surfaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->string('tooth_number', 10);
            $table->string('surface_key', 20);
            $table->json('values')->nullable()->default(null);
            $table->boolean('is_initial_exam')->default(false);
            $table->timestamps();

            $table->unique(
                ['patient_id', 'tooth_number', 'surface_key', 'is_initial_exam'],
                'tcs_patient_tooth_surface_initial_unq'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teeth_chart_surfaces');
    }
};

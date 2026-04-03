<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_anamnesis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->unique()->constrained('patients')->cascadeOnDelete();
            $table->text('chief_complaint')->nullable();
            $table->text('present_illness')->nullable();
            $table->text('current_medications')->nullable();
            $table->text('previous_surgeries')->nullable();
            $table->text('family_history')->nullable();
            $table->text('dental_history')->nullable();
            $table->text('other')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_anamnesis');
    }
};

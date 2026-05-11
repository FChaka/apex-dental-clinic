<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_monthly_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->string('plan_name', 255)->nullable();
            $table->decimal('total_amount', 10, 2);
            $table->unsignedInteger('months');
            $table->decimal('interest_percent', 5, 2)->default(0);
            $table->date('start_date')->nullable();
            $table->unsignedTinyInteger('payment_day_of_month')->nullable();
            $table->decimal('initial_payment', 10, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_monthly_plans');
    }
};

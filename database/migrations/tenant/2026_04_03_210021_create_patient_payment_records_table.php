<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_payment_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->date('date');
            $table->decimal('amount', 10, 2);
            $table->enum('method', ['cash', 'card', 'transfer', 'other'])->nullable();
            $table->text('note')->nullable();
            $table->foreignId('treatment_id')->nullable()->constrained('patient_treatment_entries')->nullOnDelete();
            $table->string('treatment_label', 255)->nullable();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('monthly_plan_id')->nullable()->constrained('patient_monthly_plans')->nullOnDelete();
            $table->boolean('is_monthly_plan_payment')->default(false);
            $table->enum('source', ['treatment', 'manual'])->default('manual');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_payment_records');
    }
};

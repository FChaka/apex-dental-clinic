<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained('subscriptions');
            $table->foreignId('clinic_id')->constrained('clinics');
            $table->decimal('amount', 10, 2);
            $table->enum('status', ['paid', 'pending', 'overdue', 'void'])->default('pending');
            $table->date('issued_at');
            $table->date('paid_at')->nullable();
            $table->date('due_at');
            $table->string('external_id', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_invoices');
    }
};

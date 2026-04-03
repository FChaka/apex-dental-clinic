<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics');
            $table->enum('plan', ['Starter', 'Professional', 'Enterprise']);
            $table->decimal('amount', 10, 2);
            $table->enum('status', ['ok', 'past_due', 'canceled'])->default('ok');
            $table->date('starts_at');
            $table->date('renews_at');
            $table->date('canceled_at')->nullable();
            $table->string('payment_method', 50)->nullable();
            $table->string('external_id', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};

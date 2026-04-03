<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_services', function (Blueprint $table) {
            $table->id();
            $table->string('key', 50)->unique();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->enum('type', ['core', 'addon']);
            $table->enum('billing_model', ['flat', 'per_unit', 'tiered', 'included'])->default('included');
            $table->string('unit_label', 50)->nullable();
            $table->decimal('default_unit_price', 10, 4)->nullable();
            $table->decimal('default_flat_price', 10, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->date('launched_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_services');
    }
};

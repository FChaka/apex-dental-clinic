<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinics', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('slug', 255)->unique();
            $table->string('region', 100)->nullable();
            $table->enum('plan', ['Starter', 'Professional', 'Enterprise'])->default('Starter');
            $table->integer('seats')->default(5);
            $table->enum('status', ['active', 'trial', 'suspended'])->default('trial');
            $table->string('contact_email', 255);
            $table->decimal('mrr', 10, 2)->default(0);
            $table->string('db_name', 255);
            $table->string('db_host', 255)->default('127.0.0.1');
            $table->string('db_port', 10)->default('3306');
            $table->timestamp('trial_ends_at')->nullable();
            // stancl VirtualColumn: internal tenancy_* attributes when not dedicated columns
            $table->json('data')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinics');
    }
};

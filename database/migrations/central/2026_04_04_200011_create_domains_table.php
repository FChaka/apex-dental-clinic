<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->create('domains', function (Blueprint $table) {
            $table->id();
            $table->string('domain', 255)->unique()->comment('Subdomain label only, e.g. smile for smile.apex.com');
            $table->foreignId('tenant_id')->constrained('clinics')->cascadeOnUpdate()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('domains');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('treatment_types', function (Blueprint $table) {
            $table->unsignedInteger('default_duration')->nullable()->change();
            $table->decimal('default_price', 10, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('treatment_types', function (Blueprint $table) {
            $table->unsignedInteger('default_duration')->nullable(false)->change();
            $table->decimal('default_price', 10, 2)->nullable(false)->change();
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('date_time_settings', function (Blueprint $table) {
            $table->id();
            $table->enum('time_zone_mode', ['automatic', 'manual'])->default('automatic');
            $table->string('manual_time_zone', 100)->nullable();
            $table->enum('date_format', ['dd/mm/yyyy', 'mm/dd/yyyy', 'yyyy-mm-dd'])->default('dd/mm/yyyy');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('date_time_settings');
    }
};

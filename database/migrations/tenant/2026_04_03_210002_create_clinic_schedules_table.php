<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('day_of_week');
            $table->boolean('is_open')->default(true);
            $table->time('start_hour');
            $table->time('end_hour');
            $table->timestamps();

            $table->unique('day_of_week');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_schedules');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_widget_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff_members')->cascadeOnDelete();
            $table->string('page', 50);
            $table->json('widget_order');
            $table->timestamps();

            $table->unique(['staff_id', 'page']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_widget_preferences');
    }
};

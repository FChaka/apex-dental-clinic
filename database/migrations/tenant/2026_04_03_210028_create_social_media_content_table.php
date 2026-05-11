<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_media_content', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->text('caption')->nullable();
            $table->enum('type', ['image', 'video']);
            $table->string('file_path', 255);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_media_content');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff_members')->cascadeOnDelete();
            $table->string('name', 255);
            $table->enum('type', ['license', 'diploma', 'certification', 'other']);
            $table->string('file_name', 255);
            $table->string('file_path', 255);
            $table->timestamp('uploaded_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_documents');
    }
};

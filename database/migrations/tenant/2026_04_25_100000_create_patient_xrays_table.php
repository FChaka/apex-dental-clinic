<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_xrays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->string('title', 255)->nullable();
            $table->string('file_name', 255);
            $table->string('file_path', 255);
            $table->string('thumbnail_path', 255)->nullable();
            $table->string('mime_type', 100);
            $table->unsignedInteger('file_size')->nullable();
            $table->text('notes')->nullable();
            $table->date('taken_at')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('staff_members')->nullOnDelete();
            $table->timestamps();

            $table->index('patient_id');
            $table->index('taken_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_xrays');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('surname', 255)->nullable();
            $table->string('fathers_name', 255)->nullable();
            $table->date('birthday')->nullable();
            $table->enum('gender', ['Male', 'Female', 'Other'])->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email', 255)->nullable();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('personal_number', 50)->nullable();
            $table->string('blood_type', 10)->nullable();
            $table->string('avatar_path', 255)->nullable();
            $table->text('general_notes')->nullable();
            $table->foreignId('assigned_dentist_id')->nullable()->constrained('staff_members')->nullOnDelete();
            $table->date('last_visit')->nullable();
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->text('medical_alert')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};

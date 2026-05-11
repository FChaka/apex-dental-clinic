<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_members', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('email', 255);
            $table->string('phone', 50)->nullable();
            $table->string('avatar_path', 255)->nullable();
            $table->enum('role', ['Dentist', 'Dental Hygienist', 'Receptionist', 'Dental Nurse', 'Other']);
            $table->enum('clinic_access_level', ['super_admin', 'admin', 'staff'])->default('staff');
            $table->string('specialty', 255)->nullable();
            $table->string('experience', 100)->nullable();
            $table->enum('status', ['Active', 'On Leave', 'Off Duty'])->default('Active');
            $table->string('username', 100)->unique();
            $table->enum('sign_in_method', ['pin', 'password'])->default('pin');
            $table->unsignedTinyInteger('pin_length')->default(4);
            $table->string('login_pin', 255)->nullable();
            $table->string('login_password', 255)->nullable();
            $table->string('color', 7)->nullable();
            $table->unsignedInteger('annual_leave_days')->nullable();
            $table->date('leave_start')->nullable();
            $table->date('leave_end')->nullable();
            $table->boolean('paid_by_percentage')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_members');
    }
};

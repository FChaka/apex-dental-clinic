<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_members', function (Blueprint $table) {
            $table->timestamp('temp_pin_expires_at')->nullable()->after('login_password');
            $table->boolean('must_change_credentials')->default(false)->after('temp_pin_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('staff_members', function (Blueprint $table) {
            $table->dropColumn(['temp_pin_expires_at', 'must_change_credentials']);
        });
    }
};

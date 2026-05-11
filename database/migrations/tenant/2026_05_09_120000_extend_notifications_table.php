<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->renameColumn('staff_id', 'receiver_staff_id');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->foreignId('from_staff_id')
                ->nullable()
                ->after('receiver_staff_id')
                ->constrained('staff_members')
                ->nullOnDelete();

            $table->string('type', 100)
                ->nullable()
                ->after('from_staff_id');

            $table->string('path', 255)
                ->nullable()
                ->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropForeign(['from_staff_id']);
            $table->dropColumn(['from_staff_id', 'type', 'path']);
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->renameColumn('receiver_staff_id', 'staff_id');
        });
    }
};

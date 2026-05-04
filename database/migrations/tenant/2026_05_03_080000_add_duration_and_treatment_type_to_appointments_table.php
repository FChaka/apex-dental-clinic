<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->foreignId('treatment_type_id')
                ->nullable()
                ->after('dentist_id')
                ->constrained('treatment_types')
                ->nullOnDelete();

            $table->unsignedSmallInteger('duration')
                ->nullable()
                ->after('treatment');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('treatment_type_id');
            $table->dropColumn('duration');
        });
    }
};

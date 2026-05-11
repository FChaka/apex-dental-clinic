<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('patients', 'assigned_dentist_id')) {
            return;
        }

        Schema::table('patients', function (Blueprint $table) {
            // Column was created via ->foreignId()->constrained()->nullOnDelete()
            $table->dropConstrainedForeignId('assigned_dentist_id');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('patients', 'assigned_dentist_id')) {
            return;
        }

        Schema::table('patients', function (Blueprint $table) {
            $table
                ->foreignId('assigned_dentist_id')
                ->nullable()
                ->constrained('staff_members')
                ->nullOnDelete();
        });
    }
};


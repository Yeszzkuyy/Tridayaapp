<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('proposals', 'lead_task_id')) {
            return;
        }

        Schema::table('proposals', function (Blueprint $table) {
            $table->foreignId('lead_task_id')
                ->nullable()
                ->after('lead_id')
                ->constrained()
                ->nullOnDelete();
            $table->unique('lead_task_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('proposals', 'lead_task_id')) {
            return;
        }

        Schema::table('proposals', function (Blueprint $table) {
            $table->dropUnique(['lead_task_id']);
            $table->dropConstrainedForeignId('lead_task_id');
        });
    }
};

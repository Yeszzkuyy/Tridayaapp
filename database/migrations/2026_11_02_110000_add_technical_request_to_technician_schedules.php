<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('technician_schedules', function (Blueprint $table) {
            $table->foreignId('technical_request_id')
                ->nullable()
                ->after('project_id')
                ->constrained('technical_requests')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('technician_schedules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('technical_request_id');
        });
    }
};

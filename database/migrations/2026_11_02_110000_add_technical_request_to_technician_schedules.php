<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('technician_schedules', function (Blueprint $table) {
            // Tanpa foreign key: ALTER + FK memaksa SQLite membangun ulang
            // tabel dan menghapus partial index google_event_id.
            $table->unsignedBigInteger('technical_request_id')
                ->nullable()
                ->after('project_id')
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('technician_schedules', function (Blueprint $table) {
            $table->dropIndex(['technical_request_id']);
            $table->dropColumn('technical_request_id');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jadwal teknisi bisa berdiri tanpa project (pekerjaan non-project).
     * Supaya tetap terhubung ke customer, simpan customer langsung di
     * jadwal. Bila project dipilih, customer diambil dari project.
     */
    public function up(): void
    {
        Schema::table('technician_schedules', function (Blueprint $table) {
            $table->foreignId('customer_id')
                ->nullable()
                ->after('project_id')
                ->constrained('customers')
                ->nullOnDelete();
            $table->index('customer_id');
        });

        // Jadwal lama yang sudah punya project: ikutkan customer project
        // supaya kolom baru tidak kosong.
        DB::table('technician_schedules')
            ->whereNotNull('project_id')
            ->whereNull('customer_id')
            ->update([
                'customer_id' => DB::raw('(SELECT customer_id FROM projects WHERE projects.id = technician_schedules.project_id)'),
            ]);
    }

    public function down(): void
    {
        Schema::table('technician_schedules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_id');
        });
    }
};

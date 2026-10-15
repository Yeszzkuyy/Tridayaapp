<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Satu jadwal bisa ditangani lebih dari satu teknisi (survey/instalasi
     * biasanya berangkat tim). Relasi many-to-many lewat tabel pivot.
     *
     * Kolom technician_user_id lama TIDAK dihapus — data lama disalin ke
     * tabel pivot supaya jadwal lama tetap tampil dengan teknisinya.
     */
    public function up(): void
    {
        Schema::create('technician_schedule_user', function (Blueprint $table) {
            $table->foreignId('technician_schedule_id')->constrained('technician_schedules')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->primary(['technician_schedule_id', 'user_id']);
        });

        // Salin data lama: teknisi tunggal → satu baris pivot.
        DB::table('technician_schedules')
            ->whereNotNull('technician_user_id')
            ->orderBy('id')
            ->chunkById(200, function ($schedules) {
                foreach ($schedules as $schedule) {
                    DB::table('technician_schedule_user')->insertOrIgnore([
                        'technician_schedule_id' => $schedule->id,
                        'user_id' => $schedule->technician_user_id,
                    ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('technician_schedule_user');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->replaceWorkTypeForeignKey('restrict');
    }

    public function down(): void
    {
        $this->replaceWorkTypeForeignKey('cascade');
    }

    private function replaceWorkTypeForeignKey(string $onDelete): void
    {
        // SQLite tidak dipakai untuk validasi FK produksi; rebuild tabel di
        // SQLite berisiko menghapus partial index yang sudah ada.
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['work_type_id']);
        });

        Schema::table('projects', function (Blueprint $table) use ($onDelete) {
            $foreign = $table->foreign('work_type_id')->references('id')->on('work_types');

            $onDelete === 'restrict'
                ? $foreign->restrictOnDelete()
                : $foreign->cascadeOnDelete();
        });
    }
};

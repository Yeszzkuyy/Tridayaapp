<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * PRD project workflow: kategori dokumen teknis butuh "Sizing" di samping
 * Survey, Instalasi, dan BAST yang sudah ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('document_categories')->where('name', 'Sizing')->exists();
        if (! $exists) {
            DB::table('document_categories')->insert([
                'name' => 'Sizing',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('document_categories')->where('name', 'Sizing')->delete();
    }
};

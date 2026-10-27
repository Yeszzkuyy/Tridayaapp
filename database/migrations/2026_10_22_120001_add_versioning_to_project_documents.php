<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambahkan dukungan versi dokumen dan grup dokumen.
     *
     * Setiap dokumen logis dikelompokkan via document_group_id (UUID).
     * Setiap unggahan baru membuat baris baru dengan version + 1,
     * file baru, dan baris lama (versi sebelumnya) tetap dipertahankan.
     *
     * Kolom title menyimpan nama logis dokumen yang stabil antar versi.
     */
    public function up(): void
    {
        Schema::table('project_documents', function (Blueprint $table) {
            // Grup dokumen logis (lintas versi). Nullable untuk kompatibilitas
            // dengan data lama yang akan di-backfill secara terpisah.
            $table->uuid('document_group_id')->nullable()->after('id');
            $table->index('document_group_id', 'idx_project_documents_group');

            // Nomor urut versi dalam grup. Default 1 untuk baris lama.
            $table->unsignedTinyInteger('version')->default(1)->after('document_group_id');

            // Judul logis dokumen (stabil antar versi), nullable.
            $table->string('title', 255)->nullable()->after('version');

            // Unique constraint: satu grup hanya boleh punya satu versi per nomor.
            $table->unique(['document_group_id', 'version'], 'idx_project_documents_group_version')
                ->whereNull('deleted_at');
        });
    }

    /**
     * Kembalikan skema ke sebelum dukungan versi.
     * Catatan: data versi akan hilang, tapi baris dokumen itu sendiri tetap.
     */
    public function down(): void
    {
        Schema::table('project_documents', function (Blueprint $table) {
            $table->dropUnique('idx_project_documents_group_version');
            $table->dropIndex(['document_group_id']);
            $table->dropColumn(['document_group_id', 'version', 'title']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Riwayat revisi quotation. Satu proposal bisa punya banyak versi.
     * `snapshot` menyimpan ringkasan perubahan (item + total + margin) agar
     * riwayat tetap terbaca tanpa bergantung pada data item yang sudah diubah.
     */
    public function up(): void
    {
        Schema::create('proposal_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_id')->constrained('proposals')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('label', 255)->nullable();
            $table->json('snapshot')->nullable();
            $table->decimal('total_before', 15, 2)->nullable();
            $table->decimal('total_after', 15, 2)->nullable();
            $table->text('change_summary')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['proposal_id', 'version'], 'proposal_versions_proposal_version_unique');
            $table->index('proposal_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proposal_versions');
    }
};

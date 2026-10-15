<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('knowledge_documents')->cascadeOnDelete();
            $table->unsignedInteger('chunk_index')->default(0);
            $table->text('content');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['document_id', 'chunk_index']);
        });

        // Pencarian teks bawaan PostgreSQL (tanpa ekstensi tambahan).
        // SQLite (test) tidak mendukung GIN/tsvector -> lewati.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("CREATE INDEX knowledge_chunks_content_fts ON knowledge_chunks USING gin (to_tsvector('simple', content))");
            DB::statement("CREATE INDEX knowledge_documents_content_fts ON knowledge_documents USING gin (to_tsvector('simple', title || ' ' || content))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_chunks');
    }
};

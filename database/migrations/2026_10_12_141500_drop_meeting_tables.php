<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hapus fitur Meeting lama: tabel meetings + meeting_drafts di-drop,
 * kolom follow_ups.meeting_id (FK ke meetings) ikut dibuang.
 *
 * PERHATIAN: data di kedua tabel hilang permanen. Backup DB
 * (backups/pre-drop-meeting-*.dump) sebelum menjalankan migration ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('follow_ups', function (Blueprint $table) {
            $table->dropForeign(['meeting_id']);
            $table->dropColumn('meeting_id');
        });

        Schema::dropIfExists('meeting_drafts');
        Schema::dropIfExists('meetings');
    }

    public function down(): void
    {
        // Struktur bisa dibuat ulang, DATA yang terhapus tidak bisa dikembalikan.
        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->date('meeting_date');
            $table->string('participants')->nullable();
            $table->text('user_needs')->nullable();
            $table->text('user_complaints')->nullable();
            $table->text('existing_system')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('meeting_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->date('meeting_date')->nullable();
            $table->string('participants', 500)->nullable();
            $table->text('user_needs')->nullable();
            $table->text('user_complaints')->nullable();
            $table->text('existing_system')->nullable();
            $table->text('notes')->nullable();
            $table->text('source_sentence');
            $table->string('status', 20)->default('pending');
            $table->timestamps();
        });

        Schema::table('follow_ups', function (Blueprint $table) {
            $table->foreignId('meeting_id')->nullable()->after('lead_id')->constrained()->nullOnDelete();
        });
    }
};

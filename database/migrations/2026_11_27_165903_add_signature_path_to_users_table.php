<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TTD digital Sales untuk blok tanda tangan quotation PDF.
 * `phone` sudah ada di tabel users; hanya signature_path yang ditambahkan.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'signature_path')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('signature_path')->nullable()->after('avatar');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'signature_path')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('signature_path');
        });
    }
};

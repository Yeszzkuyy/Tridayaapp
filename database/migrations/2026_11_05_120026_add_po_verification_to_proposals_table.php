<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('proposals', function (Blueprint $table) {
            $table->timestamp('po_verified_at')->nullable()->after('po_document_path');
            $table->foreignId('po_verified_by')->nullable()->after('po_verified_at')->constrained('users')->nullOnDelete();
            $table->timestamp('po_returned_at')->nullable()->after('po_verified_by');
            $table->foreignId('po_returned_by')->nullable()->after('po_returned_at')->constrained('users')->nullOnDelete();
            $table->text('po_return_reason')->nullable()->after('po_returned_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('proposals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('po_verified_by');
            $table->dropConstrainedForeignId('po_returned_by');
            $table->dropColumn(['po_verified_at', 'po_returned_at', 'po_return_reason']);
        });
    }
};

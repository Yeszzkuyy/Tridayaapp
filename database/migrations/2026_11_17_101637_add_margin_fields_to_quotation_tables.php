<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Margin quotation memakai gross profit margin dari harga jual sebelum
     * PPN. Target margin disimpan per item, dengan default quotation yang
     * diwariskan ke item baru.
     */
    public function up(): void
    {
        Schema::table('proposals', function (Blueprint $table) {
            if (! Schema::hasColumn('proposals', 'tax_rate')) {
                $table->decimal('tax_rate', 5, 2)->nullable()->after('tax');
            }
            if (! Schema::hasColumn('proposals', 'default_margin_percent')) {
                $table->decimal('default_margin_percent', 5, 2)->nullable()->after('tax_rate');
            }
        });

        Schema::table('proposal_items', function (Blueprint $table) {
            if (! Schema::hasColumn('proposal_items', 'target_margin_percent')) {
                $table->decimal('target_margin_percent', 5, 2)->nullable()->after('unit_cost');
            }
        });

        Schema::table('lead_task_items', function (Blueprint $table) {
            if (! Schema::hasColumn('lead_task_items', 'target_margin_percent')) {
                $table->decimal('target_margin_percent', 5, 2)->nullable()->after('unit_cost');
            }
        });
    }

    public function down(): void
    {
        Schema::table('lead_task_items', function (Blueprint $table) {
            $table->dropColumn('target_margin_percent');
        });

        Schema::table('proposal_items', function (Blueprint $table) {
            $table->dropColumn('target_margin_percent');
        });

        Schema::table('proposals', function (Blueprint $table) {
            $table->dropColumn(['tax_rate', 'default_margin_percent']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('customers', 'created_by')) {
            return;
        }

        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('created_by')
                ->nullable()
                ->after('id')
                ->constrained('users')
                ->nullOnDelete();
        });

        // Customer lama: pemilik tunggal diambil dari satu-satunya Sales yang
        // memegang lead customer tersebut. Customer dengan banyak pemilik
        // dibiarkan null (hanya Management/Admin yang melihat).
        DB::table('customers')
            ->whereNull('created_by')
            ->whereRaw('(SELECT COUNT(DISTINCT l.assigned_to) FROM leads l WHERE l.customer_id = customers.id AND l.assigned_to IS NOT NULL) = 1')
            ->update([
                'created_by' => DB::raw('(SELECT MIN(l.assigned_to) FROM leads l WHERE l.customer_id = customers.id AND l.assigned_to IS NOT NULL)'),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasColumn('customers', 'created_by')) {
            return;
        }

        Schema::table('customers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Target margin kotor per periode (wawancara Adi Santosa: kinerja sales
     * dinilai dari omzet DAN margin). Nominal Rupiah; persentase dihitung dari
     * perbandingan terhadap target omzet bila tersedia.
     */
    public function up(): void
    {
        Schema::table('sales_targets', function (Blueprint $table) {
            $table->decimal('target_margin', 15, 2)->nullable()->after('target_revenue');
        });
    }

    public function down(): void
    {
        Schema::table('sales_targets', function (Blueprint $table) {
            $table->dropColumn('target_margin');
        });
    }
};

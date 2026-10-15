<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Survey bisa lahir dari Technical Request (tipe survey). Satu request
     * maksimal menghasilkan SATU survey — kunci unik di level database
     * menjamin idempotensi walaupun proses yang sama dijalankan berulang.
     */
    public function up(): void
    {
        Schema::table('surveys', function (Blueprint $table) {
            $table->foreignId('technical_request_id')->nullable()->after('sales_user_id')
                ->constrained('technical_requests')->nullOnDelete();
            $table->unique('technical_request_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('surveys', function (Blueprint $table) {
            $table->dropUnique(['technical_request_id']);
            $table->dropConstrainedForeignId('technical_request_id');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estimator_requests', function (Blueprint $table) {
            $table->string('attachment_name')->nullable()->after('attachment_path');
            $table->unsignedBigInteger('attachment_size')->nullable()->after('attachment_name');
        });
    }

    public function down(): void
    {
        Schema::table('estimator_requests', function (Blueprint $table) {
            $table->dropColumn(['attachment_name', 'attachment_size']);
        });
    }
};

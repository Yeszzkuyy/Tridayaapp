<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('google_app_credentials', function (Blueprint $table) {
            // api_key di-cast encrypted (base64+IV+MAC) sehingga melebihi
            // varchar(255) → perlu text agar tidak "value too long" (500).
            $table->text('api_key')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('google_app_credentials', function (Blueprint $table) {
            $table->string('api_key', 255)->nullable()->change();
        });
    }
};

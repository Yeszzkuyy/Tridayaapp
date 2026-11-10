<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('proposal_items', 'scope_of_supply')) {
            return;
        }

        Schema::table('proposal_items', function (Blueprint $table) {
            $table->string('scope_of_supply', 100)->nullable()->after('description');
            $table->string('lead_time', 100)->nullable()->after('scope_of_supply');
            $table->string('section', 100)->nullable()->after('lead_time');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('proposal_items', 'scope_of_supply')) {
            return;
        }

        Schema::table('proposal_items', function (Blueprint $table) {
            $table->dropColumn(['scope_of_supply', 'lead_time', 'section']);
        });
    }
};

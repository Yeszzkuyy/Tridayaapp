<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('lead_task_items', 'unit_cost')) {
            return;
        }

        Schema::table('lead_task_items', function (Blueprint $table) {
            $table->decimal('unit_cost', 15, 2)->nullable()->after('unit_price');
            $table->string('scope_of_supply', 100)->nullable()->after('description');
            $table->string('lead_time', 100)->nullable()->after('scope_of_supply');
            $table->string('section', 100)->nullable()->after('lead_time');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('lead_task_items', 'unit_cost')) {
            return;
        }

        Schema::table('lead_task_items', function (Blueprint $table) {
            $table->dropColumn(['unit_cost', 'scope_of_supply', 'lead_time', 'section']);
        });
    }
};

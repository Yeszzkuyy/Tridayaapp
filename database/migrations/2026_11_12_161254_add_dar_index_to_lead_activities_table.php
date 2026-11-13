<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_activities', function (Blueprint $table) {
            $table->index(['user_id', 'created_at'], 'lead_activities_user_created_index');
            $table->index(['action', 'created_at'], 'lead_activities_action_created_index');
        });
    }

    public function down(): void
    {
        Schema::table('lead_activities', function (Blueprint $table) {
            $table->dropIndex('lead_activities_user_created_index');
            $table->dropIndex('lead_activities_action_created_index');
        });
    }
};

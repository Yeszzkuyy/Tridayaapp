<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * PRD project workflow: project hasil Convert to Project masuk dengan status
 * awal "Pending" (Waiting Assignment) — bukan langsung "Open".
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('project_statuses')->update(['is_default' => false]);
        DB::table('project_statuses')->where('name', 'Pending')->update(['is_default' => true]);
    }

    public function down(): void
    {
        DB::table('project_statuses')->where('name', 'Pending')->update(['is_default' => false]);
        DB::table('project_statuses')->where('name', 'Open')->update(['is_default' => true]);
    }
};

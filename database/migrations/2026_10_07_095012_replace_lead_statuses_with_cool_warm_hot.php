<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Skip di SQLite (test :memory:): kolom status sudah string dari create_leads_table.
        if (DB::getDriverName() === 'sqlite') {
            DB::table('leads')->where('status', 'new')->update(['status' => 'cool']);
            DB::table('leads')->where('status', 'contacted')->update(['status' => 'warm']);
            DB::table('leads')->whereIn('status', ['qualified', 'proposal'])->update(['status' => 'hot']);
            return;
        }
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE leads MODIFY COLUMN status ENUM('new','contacted','qualified','proposal','won','lost','cool','warm','hot') DEFAULT 'new'");
        } elseif (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE leads ALTER COLUMN status TYPE VARCHAR(20) USING status::VARCHAR");
            DB::statement("ALTER TABLE leads DROP CONSTRAINT IF EXISTS leads_status_check");
            DB::statement("ALTER TABLE leads ALTER COLUMN status SET DEFAULT 'cool'");
        }
        DB::table('leads')->where('status', 'new')->update(['status' => 'cool']);
        DB::table('leads')->where('status', 'contacted')->update(['status' => 'warm']);
        DB::table('leads')->whereIn('status', ['qualified', 'proposal'])->update(['status' => 'hot']);
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE leads MODIFY COLUMN status ENUM('cool','warm','hot','won','lost') DEFAULT 'cool'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::table('leads')->where('status', 'cool')->update(['status' => 'new']);
            DB::table('leads')->where('status', 'warm')->update(['status' => 'contacted']);
            DB::table('leads')->where('status', 'hot')->update(['status' => 'qualified']);
            return;
        }
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE leads MODIFY COLUMN status ENUM('new','contacted','qualified','proposal','won','lost','cool','warm','hot') DEFAULT 'cool'");
        } elseif (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE leads ALTER COLUMN status TYPE VARCHAR(20) USING status::VARCHAR");
            DB::statement("ALTER TABLE leads DROP CONSTRAINT IF EXISTS leads_status_check");
            DB::statement("ALTER TABLE leads ALTER COLUMN status SET DEFAULT 'cool'");
        }
        DB::table('leads')->where('status', 'cool')->update(['status' => 'new']);
        DB::table('leads')->where('status', 'warm')->update(['status' => 'contacted']);
        DB::table('leads')->where('status', 'hot')->update(['status' => 'qualified']);
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE leads MODIFY COLUMN status ENUM('new','contacted','qualified','proposal','won','lost') DEFAULT 'new'");
        }
    }
};

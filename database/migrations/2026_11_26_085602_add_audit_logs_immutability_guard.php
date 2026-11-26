<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION guard_audit_logs_immutable()
RETURNS trigger AS $$
BEGIN
    RAISE EXCEPTION 'audit_logs is append-only and cannot be changed or deleted'
        USING ERRCODE = '23514';
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER guard_audit_logs_immutable
BEFORE UPDATE OR DELETE ON audit_logs
FOR EACH ROW EXECUTE FUNCTION guard_audit_logs_immutable();
SQL);
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS guard_audit_logs_immutable ON audit_logs');
        DB::unprepared('DROP FUNCTION IF EXISTS guard_audit_logs_immutable()');
    }
};

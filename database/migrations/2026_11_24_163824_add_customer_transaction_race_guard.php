<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var list<string> */
    private array $tables = [
        'invoices',
        'projects',
        'leads',
        'purchase_orders',
        'goods_deliveries',
        'estimator_requests',
        'follow_ups',
        'pocs',
        'price_requests',
        'project_documents',
        'surveys',
        'instalasis',
        'technician_schedules',
    ];

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        // Trigger BEFORE INSERT/UPDATE mengambil FOR KEY SHARE pada baris
        // customer. Delete customer memakai FOR UPDATE, sehingga kedua
        // transaksi saling menunggu dan tidak bisa melewati guard.
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION guard_customer_transaction()
RETURNS trigger AS $$
DECLARE
    parent_deleted_at timestamp;
    parent_archived_at timestamp;
BEGIN
    IF NEW.customer_id IS NULL THEN
        RETURN NEW;
    END IF;

    IF TG_OP = 'UPDATE' AND NEW.customer_id IS NOT DISTINCT FROM OLD.customer_id THEN
        RETURN NEW;
    END IF;

    SELECT deleted_at, archived_at
    INTO parent_deleted_at, parent_archived_at
    FROM customers
    WHERE id = NEW.customer_id
    FOR KEY SHARE;

    IF NOT FOUND THEN
        RETURN NEW;
    END IF;

    IF parent_deleted_at IS NOT NULL THEN
        RAISE EXCEPTION 'Customer % is in Trash and cannot receive new transactions', NEW.customer_id
            USING ERRCODE = '23514';
    END IF;

    IF parent_archived_at IS NOT NULL THEN
        RAISE EXCEPTION 'Customer % is archived and cannot receive new transactions', NEW.customer_id
            USING ERRCODE = '23514';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;
SQL);

        foreach ($this->tables as $table) {
            DB::unprepared(
                "CREATE TRIGGER guard_customer_transaction "
                ."BEFORE INSERT OR UPDATE OF customer_id ON {$table} "
                ."FOR EACH ROW EXECUTE FUNCTION guard_customer_transaction()"
            );
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->tables as $table) {
            DB::unprepared("DROP TRIGGER IF EXISTS guard_customer_transaction ON {$table}");
        }

        DB::unprepared('DROP FUNCTION IF EXISTS guard_customer_transaction()');
    }
};

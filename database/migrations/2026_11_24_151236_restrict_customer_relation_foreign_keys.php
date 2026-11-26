<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private array $cascadeTables = [
        'estimator_requests',
        'follow_ups',
        'pocs',
        'price_requests',
        'project_documents',
    ];

    /** @var list<string> */
    private array $nullTables = [
        'surveys',
        'instalasis',
        'technician_schedules',
    ];

    public function up(): void
    {
        foreach ([...$this->cascadeTables, ...$this->nullTables] as $tableName) {
            $this->replaceCustomerForeignKey($tableName, 'restrict');
        }
    }

    public function down(): void
    {
        foreach ($this->cascadeTables as $tableName) {
            $this->replaceCustomerForeignKey($tableName, 'cascade');
        }

        foreach ($this->nullTables as $tableName) {
            $this->replaceCustomerForeignKey($tableName, 'null');
        }
    }

    private function replaceCustomerForeignKey(string $tableName, string $onDelete): void
    {
        // SQLite tidak dipakai untuk validasi FK produksi; rebuild tabel di
        // SQLite berisiko menghapus partial index yang sudah ada.
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
        });

        Schema::table($tableName, function (Blueprint $table) use ($onDelete) {
            $foreign = $table->foreign('customer_id')->references('id')->on('customers');

            match ($onDelete) {
                'restrict' => $foreign->restrictOnDelete(),
                'null' => $foreign->nullOnDelete(),
                default => $foreign->cascadeOnDelete(),
            };
        });
    }
};

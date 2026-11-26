<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_statuses', function (Blueprint $table) {
            $table->string('code')->nullable()->unique()->after('name');
            $table->boolean('is_system')->default(false)->after('code');
        });

        $statuses = [
            'Open' => 'open',
            'On Progress' => 'on_progress',
            'Progress' => 'on_progress',
            'Pending' => 'pending',
            'Hold' => 'hold',
            'Done' => 'done',
            'Cancelled' => 'cancelled',
            'Cancel' => 'cancelled',
            'Warranty' => 'warranty',
            'Maintenance' => 'maintenance',
        ];

        foreach ($statuses as $name => $code) {
            // Hanya satu baris per nama yang dijadikan status inti. Data lama
            // bisa memiliki nama duplikat; baris pertama dipertahankan.
            $id = DB::table('project_statuses')
                ->whereRaw('lower(name) = ?', [strtolower($name)])
                ->orderBy('id')
                ->value('id');

            if (! $id) {
                continue;
            }

            DB::table('project_statuses')
                ->where('id', $id)
                ->update(['code' => $code, 'is_system' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('project_statuses', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn(['code', 'is_system']);
        });
    }
};

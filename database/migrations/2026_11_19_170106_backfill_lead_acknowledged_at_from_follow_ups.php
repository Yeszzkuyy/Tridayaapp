<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sebelumnya badge "New" hanya hilang saat tahap lead diubah. Sales yang sudah
 * mencatat follow up/catatan tetap terlihat "New", jadi aturannya disamakan:
 * lead yang sudah punya aktivitas nyata dianggap sudah diakui.
 *
 * Backfill ini merapikan data lama supaya badge di layar langsung sesuai
 * aturan baru. Hanya mengisi baris yang masih kosong; tidak menimpa
 * acknowledged_at yang sudah ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('leads')
            ->whereNull('acknowledged_at')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('follow_ups')
                    ->whereColumn('follow_ups.lead_id', 'leads.id')
                    ->where(function ($activity) {
                        $activity
                            ->where(fn ($description) => $description
                                ->whereNotNull('description')
                                ->where('description', '!=', ''))
                            ->orWhereNotNull('completed_at')
                            ->orWhereNotNull('outcome');
                    });
            })
            ->update([
                'acknowledged_at' => DB::raw(
                    '(SELECT MIN(follow_ups.created_at) FROM follow_ups WHERE follow_ups.lead_id = leads.id)'
                ),
            ]);
    }

    public function down(): void
    {
        // Backfill tidak bisa dibalik dengan aman: timestamp pengakuan yang
        // sudah terisi tidak dibedakan asalnya. Sengaja dibiarkan.
    }
};

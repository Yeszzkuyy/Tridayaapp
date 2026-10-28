<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sinkronkan data PIC lama: dulu PIC hanya disimpan di kolom
 * `customers.contact_person`, sementara halaman Customer membaca tabel
 * `customer_contacts`. Migrasi ini mengisi baris PIC primary dari kolom
 * tersebut, lalu menyalin nama PIC primary kembali ke kolom sebagai mirror.
 *
 * Data-only, tanpa hapus kolom: aman dijalankan berulang (idempoten).
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $legacy = DB::table('customers')
            ->whereNull('customers.deleted_at')
            ->whereNotNull('customers.contact_person')
            ->where('customers.contact_person', '!=', '')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('customer_contacts')
                    ->whereColumn('customer_contacts.customer_id', 'customers.id')
                    ->whereNull('customer_contacts.deleted_at');
            })
            ->get(['customers.id', 'customers.contact_person', 'customers.phone', 'customers.whatsapp']);

        foreach ($legacy as $customer) {
            DB::table('customer_contacts')->insert([
                'customer_id' => $customer->id,
                'name' => $customer->contact_person,
                'phone' => $customer->phone,
                'whatsapp' => $customer->whatsapp,
                'is_primary' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $primary = DB::table('customer_contacts')
            ->where('is_primary', true)
            ->whereNull('deleted_at')
            ->orderByDesc('id')
            ->get(['customer_id', 'name'])
            ->unique('customer_id');

        foreach ($primary as $contact) {
            DB::table('customers')
                ->where('id', $contact->customer_id)
                ->where(function ($query) use ($contact) {
                    $query->whereNull('contact_person')
                        ->orWhere('contact_person', '!=', $contact->name);
                })
                ->update(['contact_person' => $contact->name, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        // Data-only: tidak ada rollback otomatis supaya PIC hasil backfill
        // tidak ikut hilang saat migrasi dibatalkan.
    }
};

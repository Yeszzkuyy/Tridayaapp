<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerContact;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya pintu tulis untuk data kontak customer.
 *
 * `customer_contacts` adalah sumber kebenaran. Kolom lama
 * `customers.contact_person` hanya cache kompatibilitas yang selalu
 * disamakan dengan kontak primary lewat service ini.
 */
class CustomerContactService
{
    /** Kontak primary aktif milik customer (null bila belum ada). */
    public function primary(Customer $customer): ?CustomerContact
    {
        return $customer->contacts()
            ->where('is_primary', true)
            ->orderByDesc('id')
            ->first();
    }

    public function hasContacts(Customer $customer): bool
    {
        return $customer->contacts()->exists();
    }

    public function create(Customer $customer, array $data): CustomerContact
    {
        $makePrimary = (bool) ($data['is_primary'] ?? false);
        unset($data['is_primary']);
        $data['created_by'] ??= auth()->id();

        return DB::transaction(function () use ($customer, $data, $makePrimary) {
            $this->lockContacts($customer);

            if ($makePrimary) {
                $this->clearPrimary($customer);
            }

            $contact = $customer->contacts()->create($data + ['is_primary' => $makePrimary]);

            $this->syncPicCache($customer);

            return $contact;
        });
    }

    /**
     * @param  array<string,mixed>  $data
     */
    public function update(CustomerContact $contact, array $data): CustomerContact
    {
        $customer = $contact->customer;
        $makePrimary = array_key_exists('is_primary', $data) ? (bool) $data['is_primary'] : null;
        unset($data['is_primary']);

        return DB::transaction(function () use ($contact, $customer, $data, $makePrimary) {
            $this->lockContacts($customer);

            if ($makePrimary === true) {
                $this->clearPrimary($customer, $contact->id);
                $data['is_primary'] = true;
            } elseif ($makePrimary === false) {
                $data['is_primary'] = false;
            }

            $contact->update($data);

            $this->syncPicCache($customer);

            return $contact->refresh();
        });
    }

    public function setPrimary(CustomerContact $contact): void
    {
        $customer = $contact->customer;

        DB::transaction(function () use ($contact, $customer) {
            $this->lockContacts($customer);
            $this->clearPrimary($customer, $contact->id);
            $contact->update(['is_primary' => true]);
            $this->syncPicCache($customer);
        });
    }

    /**
     * Hapus kontak. Primary TIDAK diganti otomatis — cache dibiarkan kosong
     * sampai pengguna memilih kontak utama baru.
     */
    public function delete(CustomerContact $contact): void
    {
        $customer = $contact->customer;

        DB::transaction(function () use ($contact, $customer) {
            $this->lockContacts($customer);
            $wasPrimary = (bool) $contact->is_primary;

            $contact->delete();

            if ($wasPrimary) {
                $this->syncPicCache($customer);
            }
        });
    }

    /**
     * Samakan cache `customers.contact_person` dengan kontak primary.
     *
     * - Ada kontak tapi tanpa primary → cache dikosongkan (jangan pakai
     *   nilai lama yang bisa jadi basi).
     * - Belum ada kontak sama sekali → kolom legacy dibiarkan, supaya
     *   customer lama tetap tampil sampai backfill dijalankan.
     */
    public function syncPicCache(Customer $customer): void
    {
        if (! $customer->contacts()->exists()) {
            return;
        }

        $primary = $this->primary($customer);

        $customer->forceFill(['contact_person' => $primary?->name])->saveQuietly();
    }

    /** Kunci baris kontak customer agar perubahan primary tidak balapan. */
    private function lockContacts(Customer $customer): void
    {
        $customer->contacts()->lockForUpdate()->pluck('id');
    }

    private function clearPrimary(Customer $customer, ?int $exceptId = null): void
    {
        $customer->contacts()
            ->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))
            ->update(['is_primary' => false]);
    }
}

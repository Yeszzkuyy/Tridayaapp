<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Lead;

/**
 * Siklus hidup Customer dari Lead.
 *
 * Lead baru disimpan sebagai customer berstatus `lead` agar tidak
 * mengotori menu Customer. Saat lead benar-benar menjadi customer
 * (tahap serius, quotation accepted, PO masuk, atau convert project),
 * statusnya diangkat menjadi `deal` sehingga muncul di menu Customer.
 */
class CustomerLifecycleService
{
    /**
     * Tahap lead yang dianggap serius: begitu lead masuk sini, customer
     * otomatis diangkat menjadi deal tanpa menunggu tombol Promote.
     */
    public const SERIOUS_STAGES = ['warm', 'hot', 'won'];

    /**
     * Promosikan customer bila lead sudah berada di tahap serius.
     * Mengembalikan true hanya saat status customer benar-benar berubah,
     * supaya pemanggil bisa mencatat aktivitas tepat satu kali.
     */
    public function promoteIfSerious(?Lead $lead): bool
    {
        if (! $lead || ! in_array($lead->status, self::SERIOUS_STAGES, true)) {
            return false;
        }

        return $this->promoteFromLead($lead);
    }

    public function promoteFromLead(?Lead $lead): bool
    {
        $customer = $lead?->customer;

        return $customer ? $this->promote($customer) : false;
    }

    public function promote(Customer $customer): bool
    {
        if ($customer->trashed() || $customer->isArchived()) {
            return false;
        }

        if ($customer->status === 'lead') {
            $customer->update(['status' => 'deal']);

            return true;
        }

        return false;
    }
}

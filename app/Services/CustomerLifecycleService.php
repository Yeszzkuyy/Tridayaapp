<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Lead;

/**
 * Siklus hidup Customer dari Lead.
 *
 * Lead baru disimpan sebagai customer berstatus `lead` agar tidak
 * mengotori menu Customer. Saat lead benar-benar menjadi customer
 * (quotation accepted, PO masuk, atau convert project), statusnya
 * diangkat menjadi `deal` sehingga muncul di menu Customer.
 */
class CustomerLifecycleService
{
    public function promoteFromLead(?Lead $lead): void
    {
        $customer = $lead?->customer;

        if ($customer) {
            $this->promote($customer);
        }
    }

    public function promote(Customer $customer): void
    {
        if ($customer->trashed()) {
            return;
        }

        if ($customer->status === 'lead') {
            $customer->update(['status' => 'deal']);
        }
    }
}

<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\User;

final class CustomerArchiveService
{
    public function archive(Customer $customer, User $actor): void
    {
        if ($customer->isArchived()) {
            return;
        }

        $customer->forceFill([
            'archived_at' => now(),
            'archived_by' => $actor->getKey(),
        ])->save();
    }

    public function unarchive(Customer $customer): void
    {
        if (! $customer->isArchived()) {
            return;
        }

        $customer->forceFill([
            'archived_at' => null,
            'archived_by' => null,
        ])->save();
    }
}

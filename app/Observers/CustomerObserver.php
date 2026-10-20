<?php

namespace App\Observers;

use App\Models\Customer;

class CustomerObserver
{
    public function deleted(Customer $customer): void
    {
        if ($customer->isForceDeleting()) {
            return; // force delete tidak boleh di resurrect oleh saveQuietly
        }
        $customer->forceFill(['deleted_by' => auth()->id()])->saveQuietly();
        $customer->projects()->get()->each(fn ($project) => $project->delete());
        $customer->contacts()->get()->each(fn ($contact) => $contact->delete());
        $customer->documents()->get()->each(fn ($document) => $document->delete());
    }

    public function restored(Customer $customer): void
    {
        // ✅ Ganti onlyTrashed → withTrashed
        $customer->projects()->withTrashed()->get()->each(fn ($project) => $project->restore());
        $customer->contacts()->withTrashed()->get()->each(fn ($contact) => $contact->restore());
        $customer->documents()->withTrashed()->get()->each(fn ($document) => $document->restore());
    }
}
<?php

namespace App\Observers;

use App\Models\Customer;
use App\Services\CustomerArchiveService;
use Illuminate\Validation\ValidationException;

class CustomerObserver
{
    /**
     * Guard terakhir untuk semua jalur model delete/force delete. Jalur
     * controller tetap memakai service agar pesannya ramah; observer ini
     * memastikan tidak ada kode lain yang menghapus customer bertransaksi.
     */
    public function deleting(Customer $customer): void
    {
        if ($customer->isArchived()) {
            throw ValidationException::withMessages([
                'customer' => __('Archived customers are read-only. Unarchive this customer before deleting it.'),
            ]);
        }

        $blockers = app(CustomerArchiveService::class)->deletionBlockers($customer);

        if ($blockers !== []) {
            throw ValidationException::withMessages([
                'customer' => __('This customer cannot be deleted because it still has transaction history: :relations. Archive it instead.', [
                    'relations' => implode(', ', $blockers),
                ]),
            ]);
        }
    }

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
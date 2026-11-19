<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Services\CustomerContactService;
use Illuminate\Console\Command;

/**
 * Backfill data kontak lama ke `customer_contacts` (idempotent).
 *
 * Hanya mengangkat `contact_person` legacy menjadi satu kontak primary
 * untuk customer yang BELUM punya kontak sama sekali. Konflik tidak
 * diselesaikan otomatis — semuanya masuk laporan review.
 */
class BackfillCustomerContacts extends Command
{
    protected $signature = 'customers:backfill-contacts {--dry-run : Hanya tampilkan rencana, tanpa menulis}';

    protected $description = 'Angkat PIC legacy (contact_person) menjadi kontak primary untuk customer yang belum punya kontak';

    public function handle(CustomerContactService $contacts): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $created = 0;
        $skippedHasPrimary = 0;
        $skippedNoPrimary = 0;
        $skippedWithTrashed = 0;
        $review = [];

        Customer::query()
            ->whereNotNull('contact_person')
            ->where('contact_person', '!=', '')
            ->orderBy('id')
            ->chunkById(200, function ($customers) use (
                $contacts,
                $dryRun,
                &$created,
                &$skippedHasPrimary,
                &$skippedNoPrimary,
                &$skippedWithTrashed,
                &$review
            ) {
                foreach ($customers as $customer) {
                    $allContacts = $customer->contacts()->withTrashed()->get();

                    if ($allContacts->isEmpty()) {
                        if (! $dryRun) {
                            $contacts->create($customer, [
                                'name' => trim((string) $customer->contact_person),
                                'is_primary' => true,
                            ]);
                        }

                        $created++;

                        continue;
                    }

                    $active = $allContacts->whereNull('deleted_at');

                    if ($active->isEmpty()) {
                        $skippedWithTrashed++;
                        $review[] = "#{$customer->id} {$customer->name}: kontak hanya ada di Trash — tidak dibuat ulang";

                        continue;
                    }

                    if ($active->where('is_primary', true)->isEmpty()) {
                        $skippedNoPrimary++;
                        $review[] = "#{$customer->id} {$customer->name}: punya kontak tapi belum ada primary — perlu dipilih manual";

                        continue;
                    }

                    $skippedHasPrimary++;
                }
            });

        $this->newLine();
        $this->table(
            ['Hasil', 'Jumlah'],
            [
                [$dryRun ? 'Akan dibuat (dry-run)' : 'Kontak primary dibuat', $created],
                ['Dilewati — sudah punya primary', $skippedHasPrimary],
                ['Dilewati — kontak tanpa primary', $skippedNoPrimary],
                ['Dilewati — kontak hanya di Trash', $skippedWithTrashed],
                ['Perlu review manual', count($review)],
            ]
        );

        foreach ($review as $line) {
            $this->warn($line);
        }

        if ($dryRun) {
            $this->info('Dry-run: tidak ada data yang diubah.');
        }

        return self::SUCCESS;
    }
}

<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

final class CustomerArchiveService
{
    /**
     * Relasi yang menandakan customer sudah punya riwayat bisnis. Customer
     * dengan salah satu relasi ini tidak boleh dihapus, hanya diarsipkan.
     *
     * @var array<string, string>
     */
    private const HISTORY_RELATIONS = [
        'invoices' => 'Invoices / payments',
        'projects' => 'Projects',
        'leads' => 'Leads / quotations',
        'purchaseOrders' => 'Customer purchase orders',
        'goodsDeliveries' => 'Goods deliveries',
        'estimatorRequests' => 'Estimator requests',
        'followUps' => 'Follow ups / meetings',
        'pocs' => 'POC / demos',
        'priceRequests' => 'Price requests',
        'documents' => 'Documents',
        'surveys' => 'Surveys',
        'instalasis' => 'Installations',
        'technicianSchedules' => 'Technician schedules',
    ];

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

    /** @return list<string> */
    public function deletionBlockers(Customer $customer): array
    {
        $blockers = [];

        foreach (self::HISTORY_RELATIONS as $relation => $label) {
            if ($this->relationHasRecords($customer, $relation)) {
                $blockers[] = __($label);
            }
        }

        return $blockers;
    }

    public function isDeletable(Customer $customer): bool
    {
        return ! $customer->isArchived() && $this->deletionBlockers($customer) === [];
    }

    /**
     * Soft delete dengan lock baris customer. Lock + cek ulang mencegah
     * transaksi baru lolos di antara pengecekan dan penghapusan.
     *
     * @return array{deleted: bool, archived: bool, blockers: list<string>}
     */
    public function delete(Customer $customer): array
    {
        return DB::transaction(function () use ($customer) {
            $locked = Customer::withTrashed()
                ->whereKey($customer->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->isArchived()) {
                return ['deleted' => false, 'archived' => true, 'blockers' => []];
            }

            $blockers = $this->deletionBlockers($locked);

            if ($blockers !== []) {
                return ['deleted' => false, 'archived' => false, 'blockers' => $blockers];
            }

            $locked->delete();

            return ['deleted' => true, 'archived' => false, 'blockers' => []];
        });
    }

    /**
     * Force delete dengan lock baris customer dan callback pembersihan file
     * sebelum baris database dihapus.
     *
     * @param  null|callable(Customer):void  $beforeDelete
     * @return array{deleted: bool, archived: bool, blockers: list<string>}
     */
    public function forceDelete(Customer $customer, ?callable $beforeDelete = null): array
    {
        return DB::transaction(function () use ($customer, $beforeDelete) {
            $locked = Customer::withTrashed()
                ->whereKey($customer->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->isArchived()) {
                return ['deleted' => false, 'archived' => true, 'blockers' => []];
            }

            $blockers = $this->deletionBlockers($locked);

            if ($blockers !== []) {
                return ['deleted' => false, 'archived' => false, 'blockers' => $blockers];
            }

            if ($beforeDelete !== null) {
                $beforeDelete($locked);
            }

            $locked->forceDelete();

            return ['deleted' => true, 'archived' => false, 'blockers' => []];
        });
    }

    private function relationHasRecords(Customer $customer, string $relation): bool
    {
        $query = $customer->{$relation}();
        $model = $query->getModel();

        if ($model instanceof Model && in_array(SoftDeletes::class, class_uses_recursive($model), true)) {
            $query->withTrashed();
        }

        return $query->exists();
    }
}

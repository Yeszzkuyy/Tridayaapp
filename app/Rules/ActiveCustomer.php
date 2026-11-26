<?php

namespace App\Rules;

use App\Models\Customer;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Customer untuk transaksi baru harus aktif (tidak diarsipkan dan tidak
 * dihapus). Saat mengubah transaksi lama, customer yang sedang terpasang
 * tetap boleh dipakai walau sudah diarsipkan.
 */
class ActiveCustomer implements ValidationRule
{
    public function __construct(private readonly ?int $allowArchivedId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_numeric($value)) {
            $fail(__('The selected customer is archived, deleted, or unavailable.'));

            return;
        }

        $query = Customer::withTrashed()->whereKey((int) $value);

        if ($this->allowArchivedId === null || (int) $value !== $this->allowArchivedId) {
            $query->whereNull('archived_at')->whereNull('deleted_at');
        }

        if (! $query->exists()) {
            $fail(__('The selected customer is archived, deleted, or unavailable.'));
        }
    }
}

<?php

namespace App\Rules;

use App\Models\WorkType;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Work Type untuk project baru harus aktif. Saat mengubah project lama,
 * Work Type yang sedang terpasang tetap boleh dipakai walau nonaktif.
 */
class ActiveWorkType implements ValidationRule
{
    public function __construct(private readonly ?int $allowInactiveId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_numeric($value)) {
            $fail(__('The selected work type is inactive or unavailable.'));

            return;
        }

        $query = WorkType::query()->whereKey((int) $value);

        if ($this->allowInactiveId === null || (int) $value !== $this->allowInactiveId) {
            $query->where('is_active', true);
        }

        if (! $query->exists()) {
            $fail(__('The selected work type is inactive or unavailable.'));
        }
    }
}

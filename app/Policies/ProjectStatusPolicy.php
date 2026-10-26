<?php

namespace App\Policies;

use App\Models\ProjectStatus;
use App\Models\User;

/**
 * Master data status proyek (global, tanpa pemilik per baris).
 *
 * Mutasi (create/update/delete) berada di belakang permission `manage-monitoring`,
 * sesuai matriks role AGENTS.md: tulis selalu di belakang `manage-*`.
 * Tidak membuat permission baru — cukup mengeksplisitkan gate yang sudah ada.
 */
class ProjectStatusPolicy
{
    public function manage(User $user): bool
    {
        return $user->can('manage-monitoring');
    }
}

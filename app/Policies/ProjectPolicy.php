<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use App\Support\PtAccess;

class ProjectPolicy
{
    /**
     * Semua user dengan akses ke divisi teknisi/sales boleh melihat daftar & detail project
     * (sesama teknisi boleh melihat project yang bukan miliknya, sesuai keputusan bisnis).
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['view-technician', 'manage-technician', 'view-sales']);
    }

    public function view(User $user, Project $project): bool
    {
        return $user->hasAnyPermission(['view-technician', 'manage-technician', 'view-sales']);
    }

    public function create(User $user): bool
    {
        return $user->can('manage-technician');
    }

    public function update(User $user, Project $project): bool
    {
        return $user->can('manage-technician');
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->can('manage-technician');
    }

    public function restore(User $user, Project $project): bool
    {
        return $this->ownsTrash($user, $project);
    }

    public function forceDelete(User $user, Project $project): bool
    {
        return $this->ownsTrash($user, $project);
    }

    /**
     * Owner data (ikut owner customer) ATAU super-admin.
     */
    private function ownsTrash(User $user, Project $project): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        $owner = $project->trashOwnerId();
        $ptGroup = $project->customer()->withTrashed()->first()?->pt_group;

        return $owner !== null && (int) $owner === (int) $user->id
            && PtAccess::canWritePt($user, $ptGroup);
    }
}

<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Aturan akses dokumen di Trash.
 *
 * Dipakai bersama oleh Policy (aksi per dokumen) dan TrashController (query
 * daftar), supaya tombol di UI dan pengecekan backend tidak pernah berbeda.
 */
class DocumentAccess
{
    /** Dokumen teknis = semua dokumen non-transaksi (survey/sizing/instalasi/project). */
    public static function isTechnical(ProjectDocument $document): bool
    {
        return ! $document->isTransactionDocument();
    }

    /**
     * Boleh hapus (arsip), restore, atau hapus permanen dokumen ini?
     */
    public static function canManage(User $user, ProjectDocument $document): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        // Dokumen transaksi (invoice/po/payment) tidak boleh diarsipkan/hapus
        // melalui route project-dokumen — dikelola di modul Admin.
        if ($document->isTransactionDocument()) {
            return false;
        }

        if ($user->can('manage-technician') && self::technicianCanManage($user, $document)) {
            return true;
        }

        if ($user->can('manage-sales') && self::salesCanManage($user, $document)) {
            return true;
        }

        return false;
    }

    /**
     * Technician: hanya dokumen teknis miliknya / project yang ditugaskan.
     * Lead-technician: semua dokumen teknis.
     */
    private static function technicianCanManage(User $user, ProjectDocument $document): bool
    {
        if (! self::isTechnical($document)) {
            return false;
        }

        if ($user->hasRole('lead-technician')) {
            return true;
        }

        if (! $user->hasRole('technician')) {
            return false;
        }

        if ((int) $document->uploaded_by === (int) $user->id) {
            return true;
        }

        if ((int) $document->deleted_by === (int) $user->id) {
            return true;
        }

        $project = self::projectOf($document);
        if (! $project) {
            return false;
        }

        if ((int) $project->teknisi_user_id === (int) $user->id) {
            return true;
        }

        $name = mb_strtolower(trim((string) $user->name));
        if ($name === '') {
            return false;
        }

        return str_contains(mb_strtolower((string) $project->pic_engineer), $name)
            || str_contains(mb_strtolower((string) $project->support_technicians), $name);
    }

    /**
     * Sales: dokumen project/PT dalam cakupannya (bukan dokumen modul).
     */
    private static function salesCanManage(User $user, ProjectDocument $document): bool
    {
        if (! self::isTechnical($document) || $document->isModuleDocument()) {
            return false;
        }

        $project = self::projectOf($document);
        if ($project && (int) $project->sales_user_id === (int) $user->id) {
            return true;
        }

        return self::salesCanSee($user, $document);
    }

    /**
     * Sales boleh melihat dokumen teknis di PT/customer-nya + yang dia hapus.
     */
    private static function salesCanSee(User $user, ProjectDocument $document): bool
    {
        if (! self::isTechnical($document)) {
            return false;
        }

        if ((int) $document->deleted_by === (int) $user->id) {
            return true;
        }

        if (PtAccess::isUnrestricted($user)) {
            return true;
        }

        $project = self::projectOf($document);
        if ($project && (int) $project->sales_user_id === (int) $user->id) {
            return true;
        }

        $customer = self::customerOf($document);
        if (! $customer) {
            return false;
        }

        return (int) $customer->trashOwnerId() === (int) $user->id
            || PtAccess::canWritePt($user, $customer->pt_group);
    }

    /**
     * Boleh melihat dokumen yang sedang di Trash?
     */
    public static function canViewInTrash(User $user, ProjectDocument $document): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        if ($user->hasAnyRole(['management', 'ceo'])) {
            return true;
        }

        if ($user->can('manage-technician') || $user->can('view-technician')) {
            return self::isTechnical($document);
        }

        if ($user->can('manage-sales') || PtAccess::isSalesLike($user)) {
            return self::salesCanSee($user, $document);
        }

        if ($user->can('view-admin')) {
            return $document->isTransactionDocument()
                || (int) $document->deleted_by === (int) $user->id;
        }

        return (int) $document->deleted_by === (int) $user->id;
    }

    /**
     * Batasi query daftar dokumen di Trash sesuai role.
     */
    public static function applyTrashVisibility(Builder $query, User $user): Builder
    {
        if ($user->hasRole('super-admin') || $user->hasAnyRole(['management', 'ceo'])) {
            return $query;
        }

        if ($user->can('manage-technician') || $user->can('view-technician')) {
            return self::onlyTechnical($query);
        }

        if ($user->can('manage-sales') || PtAccess::isSalesLike($user)) {
            $query = self::onlyTechnical($query);

            // Tanpa role PT = tanpa batas (aturan PtAccess yang sama).
            if (PtAccess::isUnrestricted($user) || PtAccess::userPts($user) === []) {
                return $query;
            }

            $customerIds = self::salesCustomerIds($user);
            $projectIds = Project::withTrashed()
                ->where('sales_user_id', $user->id)
                ->pluck('id')
                ->all();

            return $query->where(function ($sub) use ($user, $customerIds, $projectIds) {
                $sub->where('deleted_by', $user->id);
                if ($customerIds) {
                    $sub->orWhereIn('customer_id', $customerIds);
                }
                if ($projectIds) {
                    $sub->orWhereIn('project_id', $projectIds);
                }
            });
        }

        if ($user->can('view-admin')) {
            return $query->where(function ($sub) use ($user) {
                $sub->where('deleted_by', $user->id)
                    ->orWhereIn('doc_type', ProjectDocument::DOC_TYPES);
            });
        }

        return $query->where('deleted_by', $user->id);
    }

    /** Hanya dokumen non-transaksi. */
    private static function onlyTechnical(Builder $query): Builder
    {
        return $query->where(function ($sub) {
            $sub->whereNull('doc_type')
                ->orWhereNotIn('doc_type', ProjectDocument::DOC_TYPES);
        });
    }

    /** Customer yang masuk cakupan sales: PT-nya + customer miliknya. */
    private static function salesCustomerIds(User $user): array
    {
        $pts = PtAccess::userPts($user);

        $ids = Customer::withTrashed()
            ->where(function ($query) use ($pts) {
                $query->whereNull('pt_group');
                if ($pts) {
                    $query->orWhereIn('pt_group', $pts);
                }
            })
            ->pluck('id')
            ->all();

        $owned = Lead::withTrashed()
            ->where('assigned_to', $user->id)
            ->whereNotNull('customer_id')
            ->distinct()
            ->pluck('customer_id')
            ->all();

        return array_values(array_unique(array_merge($ids, $owned)));
    }

    private static function projectOf(ProjectDocument $document): ?Project
    {
        if ($document->project) {
            return $document->project;
        }

        return $document->project_id
            ? Project::withTrashed()->find($document->project_id)
            : null;
    }

    private static function customerOf(ProjectDocument $document): ?Customer
    {
        if ($document->customer) {
            return $document->customer;
        }

        return $document->customer_id
            ? Customer::withTrashed()->find($document->customer_id)
            : null;
    }
}

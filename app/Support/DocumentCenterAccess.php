<?php

namespace App\Support;

use App\Models\ProjectDocument;
use App\Models\User;
use Illuminate\Database\Query\Builder;

/**
 * Matriks akses Document Center.
 *
 * Sengaja mencerminkan izin yang benar-benar dipakai endpoint aslinya
 * (middleware route + policy/controller), BUKAN izin baru yang lebih longgar.
 * Kalau sebuah role tidak boleh membuka file di modul asalnya, baris dokumen
 * itu juga tidak boleh muncul di sini — supaya tidak ada tautan mati dan
 * tidak ada metadata yang bocor.
 */
class DocumentCenterAccess
{
    /** Route project-documents.preview/download memakai izin ini. */
    public static function canViewProjectDocuments(User $user): bool
    {
        return $user->can('view-technician')
            || $user->can('manage-technician')
            || $user->can('view-sales')
            || $user->can('manage-sales')
            || $user->can('view-admin')
            || $user->can('manage-admin');
    }

    /**
     * Batasi dokumen project/customer sesuai policy + pemisahan PT sales.
     */
    public static function applyProjectDocumentScope(Builder $query, User $user): void
    {
        $allowedTypes = [];

        if ($user->can('view-admin') || $user->can('manage-admin')) {
            $allowedTypes[] = 'invoice';
            $allowedTypes[] = 'po';
        }

        if ($user->can('view-payment-proofs')) {
            $allowedTypes[] = 'payment';
        }

        $query->where(function ($where) use ($allowedTypes) {
            // Dokumen teknis / umum / modul: tidak butuh view-admin.
            $where->where(function ($nonTransaction) {
                $nonTransaction->whereNull('pd.doc_type')
                    ->orWhereNotIn('pd.doc_type', ProjectDocument::DOC_TYPES);
            });

            if ($allowedTypes !== []) {
                $where->orWhereIn('pd.doc_type', $allowedTypes);
            }
        });

        // Sales per PT hanya melihat dokumen PT-nya / project miliknya.
        if (! PtAccess::isSalesLike($user) || PtAccess::isUnrestricted($user)) {
            return;
        }

        $pts = PtAccess::userPts($user);
        if ($pts === []) {
            return;
        }

        $query->where(function ($where) use ($user, $pts) {
            $where->whereNull('pd.customer_id')
                ->orWhereIn('pd.customer_id', function ($sub) use ($pts) {
                    $sub->select('id')->from('customers')
                        ->whereNull('deleted_at')
                        ->where(function ($customer) use ($pts) {
                            $customer->whereNull('pt_group')->orWhereIn('pt_group', $pts);
                        });
                })
                ->orWhereIn('pd.customer_id', function ($sub) use ($user) {
                    $sub->select('customer_id')->from('leads')
                        ->whereNull('deleted_at')
                        ->where('assigned_to', $user->id)
                        ->whereNotNull('customer_id');
                })
                ->orWhereIn('pd.project_id', function ($sub) use ($user) {
                    $sub->select('id')->from('projects')
                        ->whereNull('deleted_at')
                        ->where('sales_user_id', $user->id);
                });
        });
    }

    /**
     * Route sales.proposals.* (lampiran & PO) hanya untuk izin ini.
     */
    public static function canReachProposalRoutes(User $user): bool
    {
        return $user->can('view-sales')
            || $user->can('manage-sales')
            || $user->can('manage-inside-sales')
            || $user->can('manage-technician');
    }

    /**
     * Samakan dengan ProposalController::authorizeView:
     * pemilik quotation (pembuat / sales pemilik lead), management hub,
     * admin (manage-admin), dan CEO. Role lain tidak melihat quotation.
     */
    public static function applyQuotationScope(Builder $query, ?User $user, string $proposalAlias = 'pr', string $leadAlias = 'l'): void
    {
        if (! $user || ! self::canReachProposalRoutes($user)) {
            $query->whereRaw('1 = 0');

            return;
        }

        if ($user->can('manage-sales-leads') || $user->can('manage-admin') || $user->hasRole('ceo')) {
            return;
        }

        $query->where(function ($where) use ($user, $proposalAlias, $leadAlias) {
            $where->where($proposalAlias.'.created_by', $user->id)
                ->orWhere($leadAlias.'.assigned_to', $user->id);
        });
    }

    public static function canViewSupplierDocuments(User $user): bool
    {
        return $user->can('view-supplier-invoices') || $user->can('manage-admin');
    }

    public static function canViewGoodsReceipts(User $user): bool
    {
        return $user->can('view-goods-receipts') || $user->can('manage-admin');
    }

    public static function canViewLeadDocuments(User $user): bool
    {
        return $user->can('view-marketing') || $user->can('manage-marketing');
    }

    public static function canViewNpwp(User $user): bool
    {
        return $user->can('view-customer-finance');
    }
}

<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Support\DocumentCenterAccess;
use App\Support\DocumentCenterRow;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Pencarian dokumen lintas modul (Document Center).
 *
 * Sumber data tetap milik modulnya masing-masing. Service ini hanya
 * menyusun UNION ALL read-only dengan kolom yang diseragamkan, sehingga:
 * - satu file = satu baris (tidak ada penyalinan / registry kedua);
 * - pencarian, filter, urutan, dan pagination dijalankan di database;
 * - setiap cabang hanya dimasukkan bila user memang boleh membuka file
 *   tersebut lewat endpoint aslinya (tidak ada kebocoran metadata).
 */
class DocumentCenterService
{
    public const PER_PAGE = 20;

    public const CATEGORIES = ['customer', 'quotation', 'purchasing', 'project', 'technical', 'finance'];

    public const SUBCATEGORIES = [
        'general', 'invoice', 'po', 'payment',
        'survey', 'sizing', 'instalasi', 'bast',
        'quotation', 'customer_po',
        'supplier_invoice', 'supplier_payment', 'goods_receipt',
        'proposal', 'company_profile', 'technical', 'requirement', 'supporting', 'other',
        'npwp',
    ];

    public const STATUSES = [
        'draft', 'unpaid', 'partial', 'paid', 'cancelled',
        'diproses', 'selesai', 'dibatalkan', 'accepted',
    ];

    public const SORTS = ['newest', 'oldest', 'name'];

    /**
     * @param  array{q?: ?string, category?: ?string, subcategory?: ?string, customer_id?: ?int,
     *               project_id?: ?int, date_from?: ?string, date_to?: ?string,
     *               date_field?: ?string, status?: ?string, sort?: ?string}  $filters
     */
    public function paginate(User $user, array $filters = [], string $pageName = 'page'): LengthAwarePaginator
    {
        $query = $this->authorizedQuery($user);

        if (! $query) {
            return $this->emptyPaginator($pageName);
        }

        $this->applyFilters($query, $filters);
        $this->applySort($query, $filters['sort'] ?? 'newest');

        return $query->paginate(self::PER_PAGE, ['*'], $pageName)
            ->withQueryString()
            ->through(fn ($row) => $this->present($row));
    }

    /**
     * Opsi filter hanya berisi customer/project yang punya dokumen dan boleh
     * dilihat user — bukan seluruh tabel customer.
     *
     * @return array{customers: array<int, string>, projects: array<int, string>}
     */
    public function filterOptions(User $user): array
    {
        $query = $this->authorizedQuery($user);

        if (! $query) {
            return ['customers' => [], 'projects' => []];
        }

        $customerIds = (clone $query)
            ->whereNotNull('docs.customer_id')
            ->distinct()
            ->pluck('docs.customer_id')
            ->all();

        $projectIds = (clone $query)
            ->whereNotNull('docs.project_id')
            ->distinct()
            ->pluck('docs.project_id')
            ->all();

        $customers = Customer::query()
            ->whereIn('id', $customerIds)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();

        $projects = Project::query()
            ->whereIn('id', $projectIds)
            ->orderBy('project_name')
            ->get(['id', 'project_name', 'project_code'])
            ->mapWithKeys(fn (Project $project) => [
                $project->id => trim($project->project_name.' '.($project->project_code ? '('.$project->project_code.')' : '')),
            ])
            ->all();

        return ['customers' => $customers, 'projects' => $projects];
    }

    /** Apakah user punya minimal satu sumber dokumen yang bisa diakses? */
    public function hasAnySource(User $user): bool
    {
        return $this->authorizedQuery($user) !== null;
    }

    private function authorizedQuery(User $user): ?QueryBuilder
    {
        $branches = [];

        if (DocumentCenterAccess::canViewProjectDocuments($user)) {
            $branches[] = $this->projectDocumentBranch($user);
        }

        if (DocumentCenterAccess::canReachProposalRoutes($user)) {
            $branches[] = $this->quotationAttachmentBranch($user);
            $branches[] = $this->customerPoBranch($user);
        }

        if (DocumentCenterAccess::canViewSupplierDocuments($user)) {
            $branches[] = $this->supplierInvoiceBranch();
            $branches[] = $this->supplierPaymentBranch();
        }

        if (DocumentCenterAccess::canViewGoodsReceipts($user)) {
            $branches[] = $this->goodsReceiptBranch();
        }

        if (DocumentCenterAccess::canViewLeadDocuments($user)) {
            $branches[] = $this->leadDocumentBranch();
        }

        if (DocumentCenterAccess::canViewNpwp($user)) {
            $branches[] = $this->npwpBranch();
        }

        if ($branches === []) {
            return null;
        }

        $union = array_shift($branches);
        foreach ($branches as $branch) {
            $union->unionAll($branch);
        }

        return DB::query()
            ->fromSub($union, 'docs')
            ->leftJoin('customers as dc', 'dc.id', '=', 'docs.customer_id')
            ->leftJoin('projects as dp', 'dp.id', '=', 'docs.project_id')
            ->leftJoin('users as du', 'du.id', '=', 'docs.uploader_id')
            ->select([
                'docs.source',
                'docs.source_id',
                'docs.parent_id',
                'docs.name',
                'docs.number',
                'docs.ref_number',
                'docs.category',
                'docs.subcategory',
                'docs.customer_id',
                'docs.project_id',
                'docs.doc_date',
                'docs.uploaded_at',
                'docs.uploader_id',
                'docs.status',
                'docs.file_path',
                'docs.ref_type',
                'docs.ref_id',
                'docs.raw_type',
                'docs.raw_category_id',
                'dc.name as customer_name',
                'dp.project_name',
                'dp.project_code',
                'du.name as uploader_name',
            ]);
    }

    /**
     * Dokumen customer, project, hasil modul, BAST, dan transaksi customer.
     * Invoice/PO/payment sudah tercatat di registry ini oleh modul aslinya.
     */
    private function projectDocumentBranch(User $user): QueryBuilder
    {
        $query = DB::table('project_documents as pd')
            ->leftJoin('projects as p', 'p.id', '=', 'pd.project_id')
            ->leftJoin('invoices as inv', function ($join) {
                $join->on('inv.id', '=', 'pd.documentable_id')
                    ->where('pd.documentable_type', '=', Invoice::class);
            })
            ->leftJoin('purchase_orders as cpo', function ($join) {
                $join->on('cpo.id', '=', 'pd.documentable_id')
                    ->where('pd.documentable_type', '=', PurchaseOrder::class);
            })
            ->leftJoin('payments as pay', function ($join) {
                $join->on('pay.id', '=', 'pd.documentable_id')
                    ->where('pd.documentable_type', '=', Payment::class);
            })
            ->leftJoin('invoices as payinv', 'payinv.id', '=', 'pay.invoice_id')
            ->whereNull('pd.deleted_at');

        DocumentCenterAccess::applyProjectDocumentScope($query, $user);

        return $query->select([
            DB::raw("'project' as source"),
            'pd.id as source_id',
            DB::raw('NULL as parent_id'),
            DB::raw('pd.file_name as name'),
            DB::raw('coalesce(inv.invoice_number, cpo.po_number, payinv.invoice_number, p.bast_number) as number'),
            DB::raw('coalesce(inv.customer_po_number, payinv.customer_po_number) as ref_number'),
            DB::raw("case
                when pd.doc_type in ('invoice', 'payment') then 'finance'
                when pd.doc_type = 'po' then 'purchasing'
                when pd.doc_type in ('survey', 'sizing', 'instalasi', 'bast') then 'technical'
                when pd.project_id is not null then 'project'
                else 'customer'
            end as category"),
            DB::raw("coalesce(pd.doc_type, 'general') as subcategory"),
            DB::raw('coalesce(pd.customer_id, p.customer_id) as customer_id'),
            'pd.project_id as project_id',
            DB::raw('coalesce(inv.issue_date, cpo.issue_date, pay.payment_date, p.bast_date) as doc_date'),
            'pd.created_at as uploaded_at',
            'pd.uploaded_by as uploader_id',
            DB::raw('coalesce(inv.status, cpo.status) as status'),
            'pd.file_path as file_path',
            'pd.documentable_type as ref_type',
            'pd.documentable_id as ref_id',
            'pd.doc_type as raw_type',
            'pd.document_category_id as raw_category_id',
        ]);
    }

    /** Lampiran file quotation (proposal_attachments) — file nyata, bukan PDF generate. */
    private function quotationAttachmentBranch(User $user): QueryBuilder
    {
        $query = DB::table('proposal_attachments as pa')
            ->join('proposals as pr', 'pr.id', '=', 'pa.proposal_id')
            ->join('leads as l', 'l.id', '=', 'pr.lead_id')
            ->leftJoin('projects as p', function ($join) {
                $join->on('p.proposal_id', '=', 'pr.id')->whereNull('p.deleted_at');
            })
            ->whereNull('pr.deleted_at')
            ->whereNull('l.deleted_at');

        DocumentCenterAccess::applyQuotationScope($query, $user);

        return $query->select([
            DB::raw("'quotation' as source"),
            'pa.id as source_id',
            'pa.proposal_id as parent_id',
            DB::raw('pa.original_name as name'),
            DB::raw('pr.proposal_number as number'),
            DB::raw('NULL as ref_number'),
            DB::raw("'quotation' as category"),
            DB::raw("'quotation' as subcategory"),
            'l.customer_id as customer_id',
            'p.id as project_id',
            DB::raw('pr.proposal_date as doc_date'),
            'pa.created_at as uploaded_at',
            'pa.uploaded_by as uploader_id',
            'pr.status as status',
            'pa.file_path as file_path',
                DB::raw('NULL as ref_type'),
                DB::raw('NULL as ref_id'),
                DB::raw('NULL as raw_type'),
                DB::raw('NULL as raw_category_id'),
        ]);
    }

    /** Customer PO dari handoff quotation (proposals.po_document_path). */
    private function customerPoBranch(User $user): QueryBuilder
    {
        $query = DB::table('proposals as pr')
            ->join('leads as l', 'l.id', '=', 'pr.lead_id')
            ->leftJoin('projects as p', function ($join) {
                $join->on('p.proposal_id', '=', 'pr.id')->whereNull('p.deleted_at');
            })
            ->whereNull('pr.deleted_at')
            ->whereNull('l.deleted_at')
            ->whereNotNull('pr.po_document_path');

        DocumentCenterAccess::applyQuotationScope($query, $user);

        return $query->select([
            DB::raw("'customer_po' as source"),
            'pr.id as source_id',
            DB::raw('NULL as parent_id'),
            DB::raw('coalesce(pr.po_number, pr.proposal_number) as name'),
            DB::raw('coalesce(pr.po_number, pr.proposal_number) as number'),
            DB::raw('pr.proposal_number as ref_number'),
            DB::raw("'purchasing' as category"),
            DB::raw("'customer_po' as subcategory"),
            'l.customer_id as customer_id',
            'p.id as project_id',
            DB::raw('coalesce(pr.po_date, pr.proposal_date) as doc_date'),
            'pr.updated_at as uploaded_at',
            'pr.created_by as uploader_id',
            'pr.status as status',
            'pr.po_document_path as file_path',
                DB::raw('NULL as ref_type'),
                DB::raw('NULL as ref_id'),
                DB::raw('NULL as raw_type'),
                DB::raw('NULL as raw_category_id'),
        ]);
    }

    private function supplierInvoiceBranch(): QueryBuilder
    {
        return DB::table('supplier_invoices as si')
            ->leftJoin('supplier_purchase_orders as spo', 'spo.id', '=', 'si.supplier_purchase_order_id')
            ->leftJoin('projects as p', 'p.id', '=', 'si.project_id')
            ->whereNull('si.deleted_at')
            ->whereNotNull('si.invoice_file_path')
            ->select([
                DB::raw("'supplier_invoice' as source"),
                'si.id as source_id',
                DB::raw('NULL as parent_id'),
                'si.supplier_invoice_number as name',
                'si.supplier_invoice_number as number',
                'spo.po_number as ref_number',
                DB::raw("'purchasing' as category"),
                DB::raw("'supplier_invoice' as subcategory"),
                'p.customer_id as customer_id',
                'si.project_id as project_id',
                'si.issue_date as doc_date',
                'si.created_at as uploaded_at',
                'si.created_by as uploader_id',
                'si.status as status',
                'si.invoice_file_path as file_path',
                DB::raw('NULL as ref_type'),
                DB::raw('NULL as ref_id'),
                DB::raw('NULL as raw_type'),
                DB::raw('NULL as raw_category_id'),
            ]);
    }

    private function supplierPaymentBranch(): QueryBuilder
    {
        return DB::table('supplier_payments as sp')
            ->join('supplier_invoices as si', 'si.id', '=', 'sp.supplier_invoice_id')
            ->leftJoin('supplier_purchase_orders as spo', 'spo.id', '=', 'sp.supplier_purchase_order_id')
            ->leftJoin('projects as p', 'p.id', '=', 'si.project_id')
            ->whereNull('sp.deleted_at')
            ->whereNull('si.deleted_at')
            ->whereNotNull('sp.proof_file')
            ->select([
                DB::raw("'supplier_payment' as source"),
                'sp.id as source_id',
                'si.id as parent_id',
                'si.supplier_invoice_number as name',
                'si.supplier_invoice_number as number',
                'spo.po_number as ref_number',
                DB::raw("'purchasing' as category"),
                DB::raw("'supplier_payment' as subcategory"),
                'p.customer_id as customer_id',
                'si.project_id as project_id',
                'sp.payment_date as doc_date',
                'sp.created_at as uploaded_at',
                'sp.created_by as uploader_id',
                'si.status as status',
                'sp.proof_file as file_path',
                DB::raw('NULL as ref_type'),
                DB::raw('NULL as ref_id'),
                DB::raw('NULL as raw_type'),
                DB::raw('NULL as raw_category_id'),
            ]);
    }

    private function goodsReceiptBranch(): QueryBuilder
    {
        return DB::table('goods_receipts as gr')
            ->leftJoin('supplier_purchase_orders as spo', 'spo.id', '=', 'gr.supplier_purchase_order_id')
            ->leftJoin('projects as p', 'p.id', '=', 'gr.project_id')
            ->whereNull('gr.deleted_at')
            ->whereNotNull('gr.attachment_path')
            ->select([
                DB::raw("'goods_receipt' as source"),
                'gr.id as source_id',
                DB::raw('NULL as parent_id'),
                DB::raw("coalesce(gr.supplier_delivery_note_no, gr.receipt_number) as name"),
                'gr.receipt_number as number',
                'spo.po_number as ref_number',
                DB::raw("'purchasing' as category"),
                DB::raw("'goods_receipt' as subcategory"),
                'p.customer_id as customer_id',
                'gr.project_id as project_id',
                'gr.received_date as doc_date',
                'gr.created_at as uploaded_at',
                'gr.received_by as uploader_id',
                DB::raw('NULL as status'),
                'gr.attachment_path as file_path',
                DB::raw('NULL as ref_type'),
                DB::raw('NULL as ref_id'),
                DB::raw('NULL as raw_type'),
                DB::raw('NULL as raw_category_id'),
            ]);
    }

    private function leadDocumentBranch(): QueryBuilder
    {
        return DB::table('lead_documents as ld')
            ->join('leads as l', 'l.id', '=', 'ld.lead_id')
            ->whereNull('l.deleted_at')
            ->select([
                DB::raw("'lead' as source"),
                'ld.id as source_id',
                'ld.lead_id as parent_id',
                'ld.file_name as name',
                DB::raw('NULL as number'),
                DB::raw('NULL as ref_number'),
                DB::raw("case
                    when ld.category in ('quotation', 'proposal') then 'quotation'
                    when ld.category = 'technical' then 'technical'
                    else 'customer'
                end as category"),
                DB::raw("coalesce(ld.category, 'other') as subcategory"),
                'l.customer_id as customer_id',
                DB::raw('NULL as project_id'),
                DB::raw('NULL as doc_date'),
                'ld.created_at as uploaded_at',
                DB::raw('NULL as uploader_id'),
                DB::raw('NULL as status'),
                'ld.file_path as file_path',
                DB::raw('NULL as ref_type'),
                DB::raw('NULL as ref_id'),
                DB::raw('NULL as raw_type'),
                DB::raw('NULL as raw_category_id'),
            ]);
    }

    private function npwpBranch(): QueryBuilder
    {
        return DB::table('customers as c')
            ->whereNull('c.deleted_at')
            ->whereNotNull('c.npwp_document_path')
            ->select([
                DB::raw("'npwp' as source"),
                'c.id as source_id',
                DB::raw('NULL as parent_id'),
                DB::raw("coalesce(c.npwp_document_name, 'NPWP') as name"),
                DB::raw('NULL as number'),
                DB::raw('NULL as ref_number'),
                DB::raw("'customer' as category"),
                DB::raw("'npwp' as subcategory"),
                'c.id as customer_id',
                DB::raw('NULL as project_id'),
                DB::raw('NULL as doc_date'),
                'c.updated_at as uploaded_at',
                DB::raw('NULL as uploader_id'),
                DB::raw('NULL as status'),
                'c.npwp_document_path as file_path',
                DB::raw('NULL as ref_type'),
                DB::raw('NULL as ref_id'),
                DB::raw('NULL as raw_type'),
                DB::raw('NULL as raw_category_id'),
            ]);
    }

    private function applyFilters(QueryBuilder $query, array $filters): void
    {
        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $term = '%'.mb_strtolower($search).'%';

            $query->where(function ($where) use ($term) {
                $where->whereRaw('lower(docs.name) like ?', [$term])
                    ->orWhereRaw("lower(coalesce(docs.number, '')) like ?", [$term])
                    ->orWhereRaw("lower(coalesce(docs.ref_number, '')) like ?", [$term])
                    ->orWhereRaw("lower(coalesce(dc.name, '')) like ?", [$term])
                    ->orWhereRaw("lower(coalesce(dp.project_name, '')) like ?", [$term])
                    ->orWhereRaw("lower(coalesce(dp.project_code, '')) like ?", [$term]);
            });
        }

        if (in_array($filters['category'] ?? null, self::CATEGORIES, true)) {
            $query->where('docs.category', $filters['category']);
        }

        if (in_array($filters['subcategory'] ?? null, self::SUBCATEGORIES, true)) {
            $query->where('docs.subcategory', $filters['subcategory']);
        }

        if (($customerId = (int) ($filters['customer_id'] ?? 0)) > 0) {
            $query->where('docs.customer_id', $customerId);
        }

        if (($projectId = (int) ($filters['project_id'] ?? 0)) > 0) {
            $query->where('docs.project_id', $projectId);
        }

        $dateColumn = ($filters['date_field'] ?? 'document') === 'upload'
            ? 'docs.uploaded_at'
            : 'docs.doc_date';

        if ($from = $this->parseDate($filters['date_from'] ?? null)) {
            $query->where($dateColumn, '>=', $from.' 00:00:00');
        }

        if ($to = $this->parseDate($filters['date_to'] ?? null)) {
            $query->where($dateColumn, '<=', $to.' 23:59:59');
        }

        if (in_array($filters['status'] ?? null, self::STATUSES, true)) {
            $query->where('docs.status', $filters['status']);
        }
    }

    private function applySort(QueryBuilder $query, string $sort): void
    {
        // Tie-breaker wajib: tanpa ini, dua dokumen dengan waktu sama bisa
        // berpindah halaman dan muncul dobel/hilang saat pagination.
        $query->orderByRaw('case when docs.uploaded_at is null then 1 else 0 end');

        match ($sort) {
            'oldest' => $query->orderBy('docs.uploaded_at')->orderBy('docs.source')->orderBy('docs.source_id'),
            'name' => $query->orderByRaw('lower(docs.name)')->orderBy('docs.source')->orderBy('docs.source_id'),
            default => $query->orderByDesc('docs.uploaded_at')->orderBy('docs.source')->orderByDesc('docs.source_id'),
        };
    }

    private function parseDate(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function present(object $row): DocumentCenterRow
    {
        $available = false;
        if (filled($row->file_path)) {
            try {
                $available = Storage::disk('private')->exists($row->file_path);
            } catch (\Throwable $e) {
                $available = false;
            }
        }

        $canPreview = $available && DocumentCenterRow::canPreviewExtension($row->name);

        return new DocumentCenterRow(
            source: (string) $row->source,
            sourceId: (int) $row->source_id,
            parentId: $row->parent_id !== null ? (int) $row->parent_id : null,
            name: (string) $row->name,
            number: $row->number !== null ? (string) $row->number : null,
            refNumber: $row->ref_number !== null ? (string) $row->ref_number : null,
            category: (string) $row->category,
            subcategory: (string) $row->subcategory,
            customerId: $row->customer_id !== null ? (int) $row->customer_id : null,
            projectId: $row->project_id !== null ? (int) $row->project_id : null,
            customerName: $row->customer_name !== null ? (string) $row->customer_name : null,
            projectName: $row->project_name !== null ? (string) $row->project_name : null,
            projectCode: $row->project_code !== null ? (string) $row->project_code : null,
            docDate: $row->doc_date !== null ? (string) $row->doc_date : null,
            uploadedAt: $row->uploaded_at !== null ? (string) $row->uploaded_at : null,
            uploaderName: $row->uploader_name !== null ? (string) $row->uploader_name : null,
            status: $row->status !== null ? (string) $row->status : null,
            refType: $row->ref_type !== null ? (string) $row->ref_type : null,
            refId: $row->ref_id !== null ? (int) $row->ref_id : null,
            rawType: $row->raw_type !== null ? (string) $row->raw_type : null,
            rawCategoryId: $row->raw_category_id !== null ? (int) $row->raw_category_id : null,
            available: $available,
            canPreview: $canPreview,
            previewUrl: $available ? $this->previewUrl($row) : null,
            downloadUrl: $available ? $this->downloadUrl($row) : null,
            sourceUrl: $this->sourceUrl($row),
        );
    }

    private function previewUrl(object $row): ?string
    {
        return match ($row->source) {
            'project' => route('project-documents.preview', $row->source_id),
            'supplier_invoice' => route('admin.supplier-invoices.file', $row->source_id),
            'supplier_payment' => route('admin.supplier-payments.proof', $row->source_id),
            'goods_receipt' => route('admin.goods-receipts.attachment', $row->source_id),
            'lead' => route('leads.attachments.show', [$row->parent_id, $row->source_id]),
            'npwp' => route('customers.npwp', $row->source_id),
            default => null,
        };
    }

    private function downloadUrl(object $row): ?string
    {
        return match ($row->source) {
            'project' => route('project-documents.download', $row->source_id),
            'quotation' => route('sales.proposals.attachments.download', [$row->parent_id, $row->source_id]),
            'customer_po' => route('sales.proposals.download-po', $row->source_id),
            'supplier_invoice' => route('admin.supplier-invoices.file', $row->source_id),
            'supplier_payment' => route('admin.supplier-payments.proof', $row->source_id),
            'goods_receipt' => route('admin.goods-receipts.attachment', $row->source_id),
            'lead' => route('leads.attachments.download', [$row->parent_id, $row->source_id]),
            'npwp' => route('customers.npwp', $row->source_id),
            default => null,
        };
    }

    private function sourceUrl(object $row): ?string
    {
        return match ($row->source) {
            'project' => $this->linkedRecordUrl($row) ?? ($row->project_id
                ? route('projects.show', $row->project_id)
                : ($row->customer_id ? route('customers.show', $row->customer_id) : null)),
            'quotation' => $row->parent_id ? route('sales.proposals.show', $row->parent_id) : null,
            'customer_po' => route('sales.proposals.show', $row->source_id),
            'supplier_invoice' => route('admin.supplier-invoices.show', $row->source_id),
            'supplier_payment' => $row->parent_id
                ? route('admin.supplier-invoices.show', $row->parent_id)
                : null,
            'goods_receipt' => route('admin.goods-receipts.show', $row->source_id),
            'lead' => $row->parent_id ? route('leads.show', $row->parent_id) : null,
            'npwp' => route('customers.show', $row->source_id),
            default => null,
        };
    }

    /** Tautan ke record transaksi asli (invoice/PO/payment) bila ada. */
    private function linkedRecordUrl(object $row): ?string
    {
        if (blank($row->ref_type) || blank($row->ref_id)) {
            return null;
        }

        return match ($row->ref_type) {
            Invoice::class => route('admin.invoices.show', $row->ref_id),
            PurchaseOrder::class => route('admin.pos.show', $row->ref_id),
            Payment::class => route('admin.payments.show', $row->ref_id),
            default => null,
        };
    }

    private function emptyPaginator(string $pageName = 'page'): LengthAwarePaginator
    {
        return new Paginator([], 0, self::PER_PAGE, 1, [
            'path' => request()->url(),
            'query' => request()->query(),
            'pageName' => $pageName,
        ]);
    }
}

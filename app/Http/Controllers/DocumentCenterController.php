<?php

namespace App\Http\Controllers;

use App\Models\DocumentCategory;
use App\Models\Invoice;
use App\Models\ProjectDocument;
use App\Models\PurchaseOrder;
use App\Services\DocumentActivityLogger;
use App\Services\DocumentCenterService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Menu utama Dokumen: pencarian lintas modul.
 *
 * Halaman ini tidak menyimpan file sendiri. Semua baris berasal dari tabel
 * modul aslinya lewat DocumentCenterService, dan tautan preview/download
 * memakai endpoint lama yang sudah ber-otorisasi.
 */
class DocumentCenterController extends Controller
{
    public function index(Request $request, DocumentCenterService $documents)
    {
        $user = $request->user();
        $filters = $this->filters($request);
        $results = $documents->paginate($user, $filters);
        $canManageAdmin = $user->can('manage-admin');

        return view('documents.index', [
            'documents' => $results,
            'options' => $documents->filterOptions($user),
            'filters' => $filters,
            'hasAnySource' => $documents->hasAnySource($user),
            'categories' => DocumentCenterService::CATEGORIES,
            'subcategories' => DocumentCenterService::SUBCATEGORIES,
            'statuses' => DocumentCenterService::STATUSES,
            'documentCategories' => $this->documentCategories(),
            'documentLinkOptions' => $canManageAdmin ? $this->linkOptions($results) : [],
            'canReclassifyDocs' => $canManageAdmin,
            'canEditDocCategory' => $canManageAdmin,
        ]);
    }

    /**
     * Ubah jenis dokumen (general/invoice/PO).
     *
     * Field ini menentukan batas akses, jadi hanya manage-admin. Payment dan
     * dokumen modul/BAST sengaja diblokir karena terikat ke record sumbernya.
     */
    public function updateType(Request $request, ProjectDocument $document)
    {
        abort_unless($request->user()->can('manage-admin'), 403);

        abort_if(
            $document->isModuleDocument() || $document->doc_type === 'bast',
            422,
            __('Documents owned by Survey/Sizing/Installation or BAST cannot change type.')
        );

        // Bukti transfer utama (payments.proof_file menunjuk file ini) tidak
        // boleh dilepas dari halaman Payment. Dokumen payment tambahan boleh.
        if ($document->doc_type === 'payment') {
            $payment = $document->documentable;

            abort_if(
                $payment instanceof \App\Models\Payment && $payment->proof_file === $document->file_path,
                422,
                __('This file is the primary payment proof. Manage it from the Payment page.')
            );
        }

        $validated = $request->validate([
            'doc_type' => ['required', Rule::in(['general', 'invoice', 'po', 'payment'])],
            'transaction_id' => ['nullable', 'integer'],
            'document_category_id' => ['nullable', 'exists:document_categories,id'],
        ]);

        $target = $validated['doc_type'];
        $documentable = $this->resolveDocumentable($document, $target, $request->integer('transaction_id'));

        $oldType = $document->doc_type;
        $oldDocumentable = $document->documentable_type
            ? $document->documentable_type.'#'.$document->documentable_id
            : null;

        DB::transaction(function () use ($document, $target, $documentable, $validated, $oldType, $oldDocumentable, $request) {
            $document->forceFill([
                'doc_type' => $target === 'general' ? null : $target,
                'documentable_type' => $documentable?->getMorphClass(),
                'documentable_id' => $documentable?->getKey(),
            ]);

            if ($request->has('document_category_id')) {
                $document->document_category_id = $validated['document_category_id'];
            }

            $document->save();

            DocumentActivityLogger::log($document, 'reclassified', [
                'from_type' => $oldType ?: 'general',
                'to_type' => $target,
                'from_documentable' => $oldDocumentable,
                'to_documentable' => $documentable
                    ? $documentable->getMorphClass().'#'.$documentable->getKey()
                    : null,
            ]);
        });

        return back()->with('success', __('Document type updated.'));
    }

    /**
     * Ubah label kategori (DocumentCategory) tanpa mengubah hak akses.
     */
    public function updateCategory(Request $request, ProjectDocument $document)
    {
        abort_unless(
            Gate::allows('update', $document) || $request->user()->can('manage-admin'),
            403
        );

        $validated = $request->validate([
            'document_category_id' => ['nullable', 'exists:document_categories,id'],
        ]);

        $oldCategory = $document->document_category_id;

        $document->update([
            'document_category_id' => $validated['document_category_id'] ?? null,
        ]);

        DocumentActivityLogger::log($document, 'categorized', [
            'from_category_id' => $oldCategory,
            'to_category_id' => $document->document_category_id,
        ]);

        return back()->with('success', __('Document category updated.'));
    }

    /**
     * Record transaksi tujuan untuk dokumen invoice/PO.
     * Wajib milik customer yang sama supaya akses & tautan sumber konsisten.
     */
    private function resolveDocumentable(ProjectDocument $document, string $target, int $transactionId): Invoice|PurchaseOrder|\App\Models\Payment|null
    {
        if ($target === 'general') {
            return null;
        }

        if (! $document->customer_id) {
            throw ValidationException::withMessages([
                'doc_type' => __('This document is not linked to a customer, so it cannot be classified as a transaction document.'),
            ]);
        }

        if ($transactionId <= 0) {
            throw ValidationException::withMessages([
                'transaction_id' => __('Choose the related transaction record.'),
            ]);
        }

        if ($target === 'invoice') {
            $invoice = Invoice::query()
                ->whereKey($transactionId)
                ->where('customer_id', $document->customer_id)
                ->first();

            if (! $invoice) {
                throw ValidationException::withMessages([
                    'transaction_id' => __('Choose an invoice that belongs to the same customer.'),
                ]);
            }

            return $invoice;
        }

        if ($target === 'po') {
            $purchaseOrder = PurchaseOrder::query()
                ->whereKey($transactionId)
                ->where('customer_id', $document->customer_id)
                ->first();

            if (! $purchaseOrder) {
                throw ValidationException::withMessages([
                    'transaction_id' => __('Choose a purchase order that belongs to the same customer.'),
                ]);
            }

            return $purchaseOrder;
        }

        $payment = \App\Models\Payment::query()
            ->whereKey($transactionId)
            ->whereHas('invoice', fn ($query) => $query->where('customer_id', $document->customer_id))
            ->first();

        if (! $payment) {
            throw ValidationException::withMessages([
                'transaction_id' => __('Choose a payment that belongs to the same customer.'),
            ]);
        }

        return $payment;
    }

    /**
     * Opsi invoice/PO untuk customer pada halaman ini saja (bukan seluruh DB).
     *
     * @return array<int, array{invoice?: array<int, array{id:int,label:string}>, po?: array<int, array{id:int,label:string}>, payment?: array<int, array{id:int,label:string}>}>
     */
    private function linkOptions(LengthAwarePaginator $results): array
    {
        $customerIds = $results->getCollection()
            ->pluck('customerId')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($customerIds === []) {
            return [];
        }

        $map = [];

        Invoice::query()
            ->whereIn('customer_id', $customerIds)
            ->where('status', '!=', 'cancelled')
            ->orderByDesc('issue_date')
            ->get(['id', 'invoice_number', 'issue_date', 'customer_id'])
            ->each(function (Invoice $invoice) use (&$map) {
                $map[$invoice->customer_id]['invoice'][] = [
                    'id' => $invoice->id,
                    'label' => $invoice->invoice_number.' · '.$invoice->issue_date?->format('d M Y'),
                ];
            });

        PurchaseOrder::query()
            ->whereIn('customer_id', $customerIds)
            ->where('status', '!=', 'dibatalkan')
            ->orderByDesc('issue_date')
            ->get(['id', 'po_number', 'issue_date', 'customer_id'])
            ->each(function (PurchaseOrder $order) use (&$map) {
                $map[$order->customer_id]['po'][] = [
                    'id' => $order->id,
                    'label' => $order->po_number.' · '.$order->issue_date?->format('d M Y'),
                ];
            });

        \App\Models\Payment::query()
            ->whereHas('invoice', fn ($query) => $query->whereIn('customer_id', $customerIds))
            ->with('invoice:id,invoice_number,customer_id')
            ->orderByDesc('payment_date')
            ->get(['id', 'invoice_id', 'payment_date'])
            ->each(function (\App\Models\Payment $payment) use (&$map) {
                $customerId = $payment->invoice?->customer_id;
                if (! $customerId) {
                    return;
                }

                $map[$customerId]['payment'][] = [
                    'id' => $payment->id,
                    'label' => ($payment->invoice?->invoice_number ?? '-').' · '.$payment->payment_date?->format('d M Y'),
                ];
            });

        return $map;
    }

    /** @return array<int, string> */
    private function documentCategories(): array
    {
        return DocumentCategory::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        $category = $request->query('category');
        $subcategory = $request->query('subcategory');
        $status = $request->query('status');
        $sort = $request->query('sort');
        $dateField = $request->query('date_field');

        return [
            'q' => filled($request->query('q')) ? mb_substr(trim((string) $request->query('q')), 0, 120) : null,
            'category' => in_array($category, DocumentCenterService::CATEGORIES, true) ? $category : null,
            'subcategory' => in_array($subcategory, DocumentCenterService::SUBCATEGORIES, true) ? $subcategory : null,
            'customer_id' => $request->integer('customer_id') ?: null,
            'project_id' => $request->integer('project_id') ?: null,
            'date_from' => filled($request->query('date_from')) ? (string) $request->query('date_from') : null,
            'date_to' => filled($request->query('date_to')) ? (string) $request->query('date_to') : null,
            'date_field' => $dateField === 'upload' ? 'upload' : 'document',
            'status' => in_array($status, DocumentCenterService::STATUSES, true) ? $status : null,
            'sort' => in_array($sort, DocumentCenterService::SORTS, true) ? $sort : 'newest',
        ];
    }
}

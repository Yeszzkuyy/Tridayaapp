<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\Proposal;
use App\Models\PurchaseOrder;
use App\Models\RequestHarga;
use App\Rules\SecureFile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdminService
{
    public function generateInvoiceNumber(): string
    {
        $year = date('Y');
        $last = Invoice::withTrashed()->whereYear('created_at', $year)
            ->orderBy('id', 'desc')->value('invoice_number');
        $seq = $last ? (int) Str::afterLast($last, '-') + 1 : 1;
        return "INV-{$year}-" . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }

    public function generatePoNumber(): string
    {
        $year = date('Y');
        $last = PurchaseOrder::withTrashed()->whereYear('created_at', $year)
            ->orderBy('id', 'desc')->value('po_number');
        $seq = $last ? (int) Str::afterLast($last, '-') + 1 : 1;
        return "PO-{$year}-" . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }

    public function getInvoices(array $filters = [])
    {
        $query = Invoice::with(['customer', 'project', 'payments'])
            ->withSum('payments', 'amount');

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->whereLike('invoice_number', $filters['search'])
                  ->orWhereHas('customer', fn($c) => $c->whereLike('name', $filters['search']));
            });
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->latest('issue_date')->paginate(15);
    }

    public function createInvoice(array $data, ?UploadedFile $file = null): Invoice
    {
        $this->assertProjectBelongsToCustomer($data);

        return DB::transaction(function () use ($data, $file) {
            $data['invoice_number'] = $this->generateInvoiceNumber();
            $data['created_by'] = auth()->id();
            $invoice = Invoice::create($data);

            if ($file) {
                $this->attachDocument($invoice, 'invoice', $file, $data['notes'] ?? null);
            }

            return $invoice;
        });
    }

    public function updateInvoice(Invoice $invoice, array $data, ?UploadedFile $file = null): Invoice
    {
        $this->assertTransactionAccess($invoice);
        $this->assertProjectBelongsToCustomer($data);

        return DB::transaction(function () use ($invoice, $data, $file) {
            $invoice->update($data);

            if ($file) {
                $this->attachDocument($invoice, 'invoice', $file, $data['notes'] ?? null);
            }

            return $invoice;
        });
    }

    public function deleteInvoice(Invoice $invoice): void
    {
        $this->assertTransactionAccess($invoice);
        DB::transaction(function () use ($invoice) {
            $this->deleteDocuments($invoice);
            $invoice->delete();
        });
    }

    public function getPurchaseOrders(array $filters = [])
    {
        $query = PurchaseOrder::with(['customer', 'project']);

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->whereLike('po_number', $filters['search'])
                  ->orWhereHas('customer', fn($c) => $c->whereLike('name', $filters['search']));
            });
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->latest('issue_date')->paginate(15);
    }

    public function createPurchaseOrder(array $data, ?UploadedFile $file = null): PurchaseOrder
    {
        $this->assertProjectBelongsToCustomer($data);

        return DB::transaction(function () use ($data, $file) {
            $data['po_number'] = $this->generatePoNumber();
            $data['created_by'] = auth()->id();
            $po = PurchaseOrder::create($data);

            if ($file) {
                $this->attachDocument($po, 'po', $file, $data['notes'] ?? null);
            }

            return $po;
        });
    }

    /**
     * Pastikan user yang login adalah pemilik dokumen transaksi (created_by).
     * Super Admin bypass via Gate::before.
     * Admin hanya boleh mengubah/hapus dokumen transaksi yang dibuatnya sendiri.
     */
    private function assertTransactionAccess(Model $document): void
    {
        $user = auth()->user();

        // Super Admin bypass.
        if ($user && $user->hasRole('super-admin')) {
            return;
        }

        // Admin dengan manage-admin boleh mengelola SEMUA transaksi (bukan hanya miliknya).
        abort_unless(
            $user && $user->can('manage-admin'),
            403
        );
    }

    public function updatePurchaseOrder(PurchaseOrder $po, array $data, ?UploadedFile $file = null): PurchaseOrder
    {
        $this->assertTransactionAccess($po);
        $this->assertProjectBelongsToCustomer($data);

        return DB::transaction(function () use ($po, $data, $file) {
            $po->update($data);

            if ($file) {
                $this->attachDocument($po, 'po', $file, $data['notes'] ?? null);
            }

            return $po;
        });
    }

    public function deletePurchaseOrder(PurchaseOrder $po): void
    {
        $this->assertTransactionAccess($po);
        DB::transaction(function () use ($po) {
            $this->deleteDocuments($po);
            $po->delete();
        });
    }

    public function getPayments(array $filters = [])
    {
        $query = Payment::with(['invoice.customer', 'creator']);

        if (!empty($filters['search'])) {
            $query->whereHas('invoice', function ($q) use ($filters) {
                $q->whereLike('invoice_number', $filters['search'])
                  ->orWhereHas('customer', fn($c) => $c->whereLike('name', $filters['search']));
            });
        }

        return $query->latest('payment_date')->paginate(15);
    }

    public function createPayment(array $data, ?UploadedFile $proof = null): Payment
    {
        return DB::transaction(function () use ($data, $proof) {
            $data['created_by'] = auth()->id();
            $payment = Payment::create($data);

            if ($proof) {
                // Satu file dipakai dua arah: sebagai bukti transfer di record
                // payment dan sebagai dokumen (doc_type=payment) di tab
                // Documents customer — tidak menduplikasi file di storage.
                $document = $this->attachDocument(
                    $payment,
                    'payment',
                    $proof,
                    $data['notes'] ?? null
                );

                $payment->update(['proof_file' => $document->file_path]);
            }

            $this->syncInvoiceStatus($payment->invoice);

            return $payment;
        });
    }

    public function deletePayment(Payment $payment): void
    {
        $this->assertTransactionAccess($payment);
        DB::transaction(function () use ($payment) {
            $invoice = $payment->invoice;

            $this->deleteDocuments($payment);
            $payment->delete();

            $this->syncInvoiceStatus($invoice);
        });
    }

    /**
     * Status invoice mengikuti total pembayaran: paid saat lunas (boleh
     * dicicil), kembali unpaid bila totalnya masih kurang. Invoice yang
     * dibatalkan tidak diubah otomatis.
     */
    public function syncInvoiceStatus(?Invoice $invoice): void
    {
        if (! $invoice || $invoice->status === 'cancelled') {
            return;
        }

        $paid = (float) $invoice->payments()->sum('amount');

        $invoice->update([
            'status' => $paid >= (float) $invoice->amount ? 'paid' : 'unpaid',
        ]);
    }

    /**
     * Simpan file ke disk private sekaligus catat sebagai dokumen customer
     * (doc_type) yang tertaut ke record invoice/PO/payment.
     */
    private function attachDocument(Model $owner, string $docType, UploadedFile $file, ?string $notes = null): ProjectDocument
    {
        $customer = $owner instanceof Payment
            ? $owner->invoice->customer
            : $owner->customer;

        $originalName = SecureFile::sanitizeName($file->getClientOriginalName());
        $path = $file->storeAs(
            'documents/customer-' . $customer->id,
            Str::uuid() . '.' . strtolower($file->getClientOriginalExtension()),
            'private'
        );

        return ProjectDocument::create([
            'project_id' => null,
            'customer_id' => $customer->id,
            'doc_type' => $docType,
            'documentable_type' => $owner::class,
            'documentable_id' => $owner->getKey(),
            'file_name' => $originalName,
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'notes' => $notes,
            'uploaded_by' => auth()->id(),
        ]);
    }

    private function deleteDocuments(Model $owner): void
    {
        // Soft delete — file storage TIDAK dihapus, bisa dipulihkan dari Trash.
        $owner->documents()->get()->each(function (ProjectDocument $document) {
            $document->delete();
        });
    }

    private function assertProjectBelongsToCustomer(array $data): void
    {
        if (empty($data['project_id'])) {
            return;
        }

        $project = Project::withTrashed()->find($data['project_id']);
        if (! $project || (int) $project->customer_id !== (int) ($data['customer_id'] ?? null)) {
            throw ValidationException::withMessages([
                'project_id' => 'Project yang dipilih tidak sesuai dengan customer.',
            ]);
        }
    }

    public function getHubData(array $filters = []): array
    {
        $ptGroup = !empty($filters['pt_group']) ? $filters['pt_group'] : null;

        // 1. Ready to invoice projects (projects having proposal/proposal accepted but no invoices yet)
        $readyToInvoiceQuery = Project::with(['customer', 'proposal'])
            ->whereNotNull('proposal_id')
            ->doesntHave('invoices');

        if ($ptGroup) {
            $readyToInvoiceQuery->whereHas('customer', fn ($q) => $q->where('pt_group', $ptGroup));
        }

        $readyToInvoiceProjects = (clone $readyToInvoiceQuery)->latest()->limit(10)->get();
        $readyToInvoiceCount = (clone $readyToInvoiceQuery)->count();

        // 2. Invoices & Jatuh Tempo
        $invoicesQuery = Invoice::with(['customer', 'project', 'payments'])
            ->withSum('payments', 'amount');

        if ($ptGroup) {
            $invoicesQuery->whereHas('customer', fn ($q) => $q->where('pt_group', $ptGroup));
        }

        $unpaidQuery = (clone $invoicesQuery)->where('status', 'unpaid');
        $unpaidCount = (clone $unpaidQuery)->count();
        $unpaidAmount = (float) (clone $unpaidQuery)->sum('amount');

        $today = now()->toDateString();
        $overdueQuery = (clone $unpaidQuery)->whereNotNull('due_date')->where('due_date', '<', $today);
        $overdueCount = (clone $overdueQuery)->count();
        $overdueAmount = (float) (clone $overdueQuery)->sum('amount');

        $recentInvoices = (clone $invoicesQuery)->latest('issue_date')->limit(10)->get();

        // 3. Request Harga (Teknisi)
        $requestHargaQuery = RequestHarga::with(['project.customer']);
        if ($ptGroup) {
            $requestHargaQuery->whereHas('project.customer', fn ($q) => $q->where('pt_group', $ptGroup));
        }
        $queueRequestHargaQuery = (clone $requestHargaQuery)->whereNotIn('status', ['completed', 'rejected']);
        $requestHargaQueueCount = (clone $queueRequestHargaQuery)->count();
        $recentRequestHargas = (clone $requestHargaQuery)->latest()->limit(10)->get();

        // 4. Purchase Orders (PO)
        $poQuery = PurchaseOrder::with(['customer', 'project']);
        if ($ptGroup) {
            $poQuery->whereHas('customer', fn ($q) => $q->where('pt_group', $ptGroup));
        }
        $activePoCount = (clone $poQuery)->whereIn('status', ['draft', 'diproses'])->count();
        $recentPos = (clone $poQuery)->latest('issue_date')->limit(10)->get();

        // 5. Recent Payments
        $paymentQuery = Payment::with(['invoice.customer', 'invoice.project', 'creator']);
        if ($ptGroup) {
            $paymentQuery->whereHas('invoice.customer', fn ($q) => $q->where('pt_group', $ptGroup));
        }
        $recentPayments = (clone $paymentQuery)->latest('payment_date')->limit(10)->get();

        // 6. Documents Checklist Projects
        $documentProjectsQuery = Project::with([
            'customer',
            'proposal',
            'documents.category',
            'invoices',
        ]);
        if ($ptGroup) {
            $documentProjectsQuery->whereHas('customer', fn ($q) => $q->where('pt_group', $ptGroup));
        }
        $documentProjects = (clone $documentProjectsQuery)->latest()->limit(15)->get();

        return [
            'ptGroup' => $ptGroup,
            'kpi' => [
                'ready_to_invoice_count' => $readyToInvoiceCount,
                'unpaid_invoices_count' => $unpaidCount,
                'unpaid_invoices_amount' => $unpaidAmount,
                'overdue_invoices_count' => $overdueCount,
                'overdue_invoices_amount' => $overdueAmount,
                'request_harga_count' => $requestHargaQueueCount,
                'active_pos_count' => $activePoCount,
            ],
            'purchasing' => [
                'request_hargas' => $recentRequestHargas,
                'purchase_orders' => $recentPos,
            ],
            'finance' => [
                'ready_projects' => $readyToInvoiceProjects,
                'invoices' => $recentInvoices,
                'payments' => $recentPayments,
            ],
            'documents' => [
                'projects' => $documentProjects,
            ],
        ];
    }
}

<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\PurchaseOrder;
use App\Models\Payment;
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
        $this->assertDocumentOwnership($invoice);
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
        $this->assertDocumentOwnership($invoice);
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
    private function assertDocumentOwnership(Model $document): void
    {
        $user = auth()->user();

        // Super Admin bypass.
        if ($user && $user->hasRole('super-admin')) {
            return;
        }

        // Admin hanya boleh mengubah/hapus dokumen transaksi yang dibuatnya sendiri.
        abort_unless(
            $document->created_by !== null && (int) $document->created_by === (int) $user->id,
            403
        );
    }

    public function updatePurchaseOrder(PurchaseOrder $po, array $data, ?UploadedFile $file = null): PurchaseOrder
    {
        $this->assertDocumentOwnership($po);
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
        $this->assertDocumentOwnership($po);
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
        $this->assertDocumentOwnership($payment);
        DB::transaction(function () use ($payment) {
            $invoice = $payment->invoice;

            $this->deleteDocuments($payment);

            if ($payment->proof_file) {
                Storage::disk('private')->delete($payment->proof_file);
            }

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
        $owner->documents()->get()->each(function (ProjectDocument $document) {
            if ($document->file_path) {
                Storage::disk('private')->delete($document->file_path);
            }

            $document->forceDelete();
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
}

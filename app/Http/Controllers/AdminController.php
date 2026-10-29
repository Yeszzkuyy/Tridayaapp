<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\PurchaseOrder;
use App\Rules\SecureFile;
use App\Services\AdminService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    public function __construct(private AdminService $adminService) {}

    // ========== ADMIN HUB ==========

    public function hub(Request $request)
    {
        $filters = $request->only(['pt_group']);
        $hubData = $this->adminService->getHubData($filters);

        return view('admin.hub', $hubData);
    }

    // ========== INVOICE ==========

    public function invoicesIndex(Request $request)
    {
        $invoices = $this->adminService->getInvoices($request->only(['search', 'status']));

        return view('admin.invoices.index', compact('invoices'));
    }

    public function invoicesCreate(Request $request)
    {
        $customers = Customer::orderBy('name')->get(['id', 'name']);
        $projects = Project::orderBy('project_name')->get(['id', 'project_name', 'customer_id']);
        // Status paid/partial hanya boleh lahir dari payment record.
        $statuses = ['unpaid' => __('Unpaid'), 'cancelled' => __('Cancelled')];
        $types = collect(Invoice::TYPES)->mapWithKeys(fn ($type) => [$type => Invoice::typeLabel($type)])->all();
        $customerId = $request->integer('customer_id') ?: null;
        $projectId = $request->integer('project_id') ?: null;
        $amount = $request->filled('amount') ? $request->input('amount') : null;
        $notes = $request->input('notes');
        $proposalId = $request->integer('proposal_id') ?: null;
        $invoiceType = $request->filled('invoice_type') ? $request->input('invoice_type') : null;
        $customerPoNumber = $request->input('customer_po_number');
        $dueDate = $request->filled('due_date') ? $request->input('due_date') : null;
        $paymentTermsSummary = null;
        $idempotencyKey = (string) \Illuminate\Support\Str::uuid();

        $project = $projectId ? Project::with('proposal')->find($projectId) : null;

        if ($project) {
            $customerId = $customerId ?: $project->customer_id;
            $proposalId = $proposalId ?: $project->proposal_id;
            if (! $notes && $project->proposal) {
                $notes = __('Reference Proposal: ') . $project->proposal->proposal_number;
            }
        }

        $proposal = $project?->proposal;
        if (! $proposal && $proposalId) {
            $proposal = Proposal::find($proposalId);
        }

        if ($proposal) {
            $customerPoNumber = $customerPoNumber ?: $proposal->po_number;
            $paymentTermsSummary = $proposal->paymentTermsSummary();

            // Sisa tagihan = nilai quotation - invoice aktif yang sudah terbit.
            $invoiceQuery = $project
                ? $project->invoices()
                : Invoice::where('proposal_id', $proposal->id);
            $invoiced = (float) (clone $invoiceQuery)->where('status', '!=', 'cancelled')->sum('amount');
            $remaining = max(0, (float) $proposal->grand_total - $invoiced);

            if (! $amount) {
                $hasDp = Invoice::where('proposal_id', $proposal->id)
                    ->where('invoice_type', 'dp')
                    ->where('status', '!=', 'cancelled')
                    ->exists();

                if ($proposal->payment_scheme === 'dp' && ! $hasDp && $invoiced <= 0) {
                    // Tagihan pertama: DP sesuai persentase quotation.
                    $invoiceType = $invoiceType ?: 'dp';
                    $amount = $proposal->expectedDpAmount() ?? $remaining;
                } elseif ($proposal->payment_scheme === 'dp') {
                    // DP sudah terbit: berikutnya pelunasan sisa.
                    $invoiceType = $invoiceType ?: 'pelunasan';
                    $amount = $remaining;
                } else {
                    $invoiceType = $invoiceType ?: 'full';
                    $amount = $remaining;
                }
            }

            if (! $dueDate && $proposal->payment_scheme === 'tempo' && $proposal->term_days) {
                $dueDate = now()->addDays((int) $proposal->term_days)->toDateString();
            }

            if ($paymentTermsSummary && ! str_contains((string) $notes, $paymentTermsSummary)) {
                $notes = trim(($notes ? $notes . "\n" : '') . __('Payment Terms: ') . $paymentTermsSummary);
            }
        }

        $invoiceType = $invoiceType ?: 'full';

        return view('admin.invoices.create', compact(
            'customers', 'projects', 'statuses', 'types', 'customerId', 'projectId',
            'amount', 'notes', 'proposalId', 'invoiceType', 'customerPoNumber', 'dueDate',
            'paymentTermsSummary', 'idempotencyKey'
        ));
    }

    public function invoicesStore(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'project_id' => 'nullable|exists:projects,id',
            'proposal_id' => 'nullable|exists:proposals,id',
            'customer_po_number' => 'nullable|string|max:100',
            'invoice_type' => 'nullable|in:dp,termin,pelunasan,full',
            'idempotency_key' => 'nullable|string|max:64',
            'amount' => 'required|numeric|min:0',
            'status' => 'required|in:unpaid,partial,paid,cancelled',
            'issue_date' => 'required|date',
            'due_date' => 'nullable|date',
            'notes' => 'nullable|string|max:2000',
            'file' => ['nullable', 'file', 'max:20480', SecureFile::documents()],
        ]);

        $validated['invoice_type'] = $validated['invoice_type'] ?? 'full';

        $invoice = $this->adminService->createInvoice($validated, $request->file('file'));

        return redirect()->route('admin.invoices.show', $invoice)
            ->with('success', __('Invoice created successfully.'));
    }

    public function invoicesShow(Invoice $invoice)
    {
        $invoice->load(['customer', 'project', 'proposal', 'payments.creator', 'creator', 'documents']);


        return view('admin.invoices.show', compact('invoice'));
    }

    public function invoicesEdit(Invoice $invoice)
    {
        $invoice->load('proposal');
        $customers = Customer::orderBy('name')->get(['id', 'name']);
        $projects = Project::orderBy('project_name')->get(['id', 'project_name', 'customer_id']);
        $statuses = ['unpaid' => __('Unpaid'), 'partial' => __('Partial'), 'paid' => __('Paid'), 'cancelled' => __('Cancelled')];
        $types = collect(Invoice::TYPES)->mapWithKeys(fn ($type) => [$type => Invoice::typeLabel($type)])->all();

        return view('admin.invoices.edit', compact('invoice', 'customers', 'projects', 'statuses', 'types'));
    }

    public function invoicesUpdate(Request $request, Invoice $invoice)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'project_id' => 'nullable|exists:projects,id',
            'proposal_id' => 'nullable|exists:proposals,id',
            'customer_po_number' => 'nullable|string|max:100',
            'invoice_type' => 'nullable|in:dp,termin,pelunasan,full',
            'amount' => 'required|numeric|min:0',
            'status' => 'required|in:unpaid,partial,paid,cancelled',
            'issue_date' => 'required|date',
            'due_date' => 'nullable|date',
            'notes' => 'nullable|string|max:2000',
            'file' => ['nullable', 'file', 'max:20480', SecureFile::documents()],
        ]);

        $validated['invoice_type'] = $validated['invoice_type'] ?? ($invoice->invoice_type ?: 'full');

        $this->adminService->updateInvoice($invoice, $validated, $request->file('file'));

        return redirect()->route('admin.invoices.index')
            ->with('success', __('Invoice updated successfully.'));
    }

    public function invoicesDestroy(Invoice $invoice)
    {
        $this->adminService->deleteInvoice($invoice);

        return redirect()->route('admin.invoices.index')
            ->with('success', __('Invoice deleted successfully.'));
    }

    // ========== PURCHASE ORDER ==========

    public function posIndex(Request $request)
    {
        $pos = $this->adminService->getPurchaseOrders($request->only(['search', 'status']));

        return view('admin.pos.index', compact('pos'));
    }

    public function posCreate(Request $request)
    {
        $customers = Customer::orderBy('name')->get(['id', 'name']);
        $projects = Project::orderBy('project_name')->get(['id', 'project_name', 'customer_id']);
        $statuses = ['draft' => __('Draft'), 'diproses' => __('In Process'), 'selesai' => __('Completed'), 'dibatalkan' => __('Cancelled')];
        $customerId = $request->integer('customer_id') ?: null;
        $projectId = $request->integer('project_id') ?: null;
        $requestHargaId = $request->integer('request_harga_id') ?: null;
        $itemsText = null;

        if ($requestHargaId) {
            $rh = \App\Models\RequestHarga::with('project')->find($requestHargaId);
            if ($rh) {
                $projectId = $projectId ?: $rh->project_id;
                if ($rh->project) {
                    $customerId = $customerId ?: $rh->project->customer_id;
                }
                if (is_array($rh->items)) {
                    $lines = [];
                    foreach ($rh->items as $item) {
                        $dev = $item['device'] ?? '';
                        $qty = $item['quantity'] ?? 1;
                        $spec = !empty($item['specification']) ? ' (' . $item['specification'] . ')' : '';
                        $lines[] = "{$qty}x {$dev}{$spec}";
                    }
                    $itemsText = implode("\n", $lines);
                }
            }
        } elseif ($projectId && ! $customerId) {
            $proj = Project::find($projectId);
            if ($proj) {
                $customerId = $proj->customer_id;
            }
        }

        return view('admin.pos.create', compact('customers', 'projects', 'statuses', 'customerId', 'projectId', 'itemsText'));
    }

    public function posStore(Request $request)
    {
        abort(403, __('Legacy Customer PO is read-only. Use the Quotation handoff for new Customer PO.'));

        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'project_id' => 'nullable|exists:projects,id',
            'items' => 'required|string',
            'amount' => 'required|numeric|min:0',
            'status' => 'required|in:draft,diproses,selesai,dibatalkan',
            'issue_date' => 'required|date',
            'notes' => 'nullable|string|max:2000',
            'file' => ['nullable', 'file', 'max:20480', SecureFile::documents()],
        ]);

        $purchaseOrder = $this->adminService->createPurchaseOrder($validated, $request->file('file'));

        return redirect()->route('admin.pos.show', $purchaseOrder)
            ->with('success', __('Purchase Order created successfully.'));
    }

    public function posShow(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->load(['customer', 'project', 'creator', 'documents']);

        return view('admin.pos.show', compact('purchaseOrder'));
    }

    public function posEdit(PurchaseOrder $purchaseOrder)
    {
        $customers = Customer::orderBy('name')->get(['id', 'name']);
        $projects = Project::orderBy('project_name')->get(['id', 'project_name', 'customer_id']);
        $statuses = ['draft' => __('Draft'), 'diproses' => __('In Process'), 'selesai' => __('Completed'), 'dibatalkan' => __('Cancelled')];

        return view('admin.pos.edit', compact('purchaseOrder', 'customers', 'projects', 'statuses'));
    }

    public function posUpdate(Request $request, PurchaseOrder $purchaseOrder)
    {
        abort(403, __('Legacy Customer PO is read-only. Use the Quotation handoff for new Customer PO.'));

        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'project_id' => 'nullable|exists:projects,id',
            'items' => 'required|string',
            'amount' => 'required|numeric|min:0',
            'status' => 'required|in:draft,diproses,selesai,dibatalkan',
            'issue_date' => 'required|date',
            'notes' => 'nullable|string|max:2000',
            'file' => ['nullable', 'file', 'max:20480', SecureFile::documents()],
        ]);

        $this->adminService->updatePurchaseOrder($purchaseOrder, $validated, $request->file('file'));

        return redirect()->route('admin.pos.index')
            ->with('success', __('Purchase Order updated successfully.'));
    }

    public function posDestroy(PurchaseOrder $purchaseOrder)
    {
        abort(403, __('Legacy Customer PO is read-only. Use the Quotation handoff for new Customer PO.'));
    }

    // ========== PAYMENT ==========

    public function paymentsIndex(Request $request)
    {
        $payments = $this->adminService->getPayments($request->only(['search']));

        return view('admin.payments.index', compact('payments'));
    }

    public function paymentsCreate(Request $request)
    {
        $invoiceId = $request->integer('invoice_id') ?: null;
        $idempotencyKey = (string) \Illuminate\Support\Str::uuid();

        $invoices = Invoice::with('customer')
            ->withSum('payments', 'amount')
            ->where('status', '!=', 'cancelled')
            ->orderByDesc('issue_date')
            ->get();

        $remaining = $invoices->mapWithKeys(fn ($invoice) => [
            $invoice->id => $invoice->remainingAmount(),
        ])->all();

        $defaultAmount = $invoiceId
            ? ($invoices->firstWhere('id', $invoiceId)?->remainingAmount())
            : null;

        if ($request->filled('amount')) {
            $defaultAmount = $request->input('amount');
        }

        $invoiceOptions = $invoices->mapWithKeys(fn ($invoice) => [
            $invoice->id => $invoice->invoice_number
                .' — '.($invoice->customer?->name ?? '-')
                .' ('.__('Remaining').': Rp '.number_format($invoice->remainingAmount(), 0, ',', '.').')',
        ])->all();

        return view('admin.payments.create', compact(
            'invoices',
            'invoiceId',
            'remaining',
            'defaultAmount',
            'invoiceOptions',
            'idempotencyKey'
        ));
    }

    public function paymentsStore(Request $request)
    {
        $validated = $request->validate([
            'invoice_id' => 'required|exists:invoices,id',
            'idempotency_key' => 'nullable|string|max:64',
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'payment_method' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:2000',
            'proof_file' => ['nullable', 'file', 'max:5120', SecureFile::paymentProof()],
        ]);

        $invoice = Invoice::findOrFail($validated['invoice_id']);

        if ($invoice->status === 'cancelled') {
            throw ValidationException::withMessages([
                'invoice_id' => __('Cancelled invoices cannot receive payments.'),
            ]);
        }

        $remaining = $invoice->remainingAmount();
        if ((float) $validated['amount'] > $remaining + 0.0001) {
            throw ValidationException::withMessages([
                'amount' => __('Payment exceeds the remaining invoice amount (:remaining).', ['remaining' => number_format($remaining, 2)]),
            ]);
        }

        $proof = $request->file('proof_file');
        unset($validated['proof_file']);

        $payment = $this->adminService->createPayment($validated, $proof);

        return redirect()->route('admin.payments.show', $payment)
            ->with('success', __('Payment recorded successfully.'));
    }

    public function paymentsShow(Payment $payment)
    {
        $payment->load(['invoice.customer', 'creator', 'documents']);

        return view('admin.payments.show', compact('payment'));
    }

    public function paymentsProof(Payment $payment)
    {
        // Bukti transfer memuat rekening customer — hanya role yang punya
        // izin khusus proof (Admin/Finance/Management/CEO/Super Admin).
        abort_unless(auth()->user()?->can('view-payment-proofs'), 403, __('Akses ditolak.'));

        if (! $payment->proof_file) {
            abort(404, __('Proof file not found.'));
        }

        // MIME dari isi file + nosniff (bukti hanya jpg/png/pdf).
        return SecureFile::fileResponse('private', $payment->proof_file, basename($payment->proof_file));
    }

    public function paymentsDestroy(Payment $payment)
    {
        $this->adminService->deletePayment($payment);

        return redirect()->route('admin.payments.index')
            ->with('success', __('Payment deleted successfully.'));
    }
}

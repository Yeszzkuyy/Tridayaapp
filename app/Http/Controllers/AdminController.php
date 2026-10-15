<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\Payment;
use App\Models\Project;
use App\Services\AdminService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    public function __construct(private AdminService $adminService) {}

    // ========== INVOICE ==========

    public function invoicesIndex(Request $request)
    {
        $invoices = $this->adminService->getInvoices($request->only(['search', 'status']));
        return view('admin.invoices.index', compact('invoices'));
    }

    public function invoicesCreate()
    {
        $customers = Customer::orderBy('name')->get(['id', 'name']);
        $projects = Project::orderBy('project_name')->get(['id', 'project_name', 'customer_id']);
        $statuses = ['unpaid' => __('Belum Bayar'), 'paid' => __('Lunas'), 'cancelled' => __('Dibatalkan')];
        return view('admin.invoices.create', compact('customers', 'projects', 'statuses'));
    }

    public function invoicesStore(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'project_id' => 'nullable|exists:projects,id',
            'amount' => 'required|numeric|min:0',
            'status' => 'required|in:unpaid,paid,cancelled',
            'issue_date' => 'required|date',
            'due_date' => 'nullable|date',
            'notes' => 'nullable|string|max:2000',
        ]);

        $this->adminService->createInvoice($validated);

        return redirect()->route('admin.invoices.index')
            ->with('success', __('Invoice berhasil dibuat.'));
    }

    public function invoicesShow(Invoice $invoice)
    {
        $invoice->load(['customer', 'project', 'payments.creator', 'creator']);
        return view('admin.invoices.show', compact('invoice'));
    }

    public function invoicesEdit(Invoice $invoice)
    {
        $customers = Customer::orderBy('name')->get(['id', 'name']);
        $projects = Project::orderBy('project_name')->get(['id', 'project_name', 'customer_id']);
        $statuses = ['unpaid' => __('Belum Bayar'), 'paid' => __('Lunas'), 'cancelled' => __('Dibatalkan')];
        return view('admin.invoices.edit', compact('invoice', 'customers', 'projects', 'statuses'));
    }

    public function invoicesUpdate(Request $request, Invoice $invoice)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'project_id' => 'nullable|exists:projects,id',
            'amount' => 'required|numeric|min:0',
            'status' => 'required|in:unpaid,paid,cancelled',
            'issue_date' => 'required|date',
            'due_date' => 'nullable|date',
            'notes' => 'nullable|string|max:2000',
        ]);

        $this->adminService->updateInvoice($invoice, $validated);

        return redirect()->route('admin.invoices.index')
            ->with('success', __('Invoice berhasil diupdate.'));
    }

    public function invoicesDestroy(Invoice $invoice)
    {
        $this->adminService->deleteInvoice($invoice);
        return redirect()->route('admin.invoices.index')
            ->with('success', __('Invoice berhasil dihapus.'));
    }

    // ========== PURCHASE ORDER ==========

    public function posIndex(Request $request)
    {
        $pos = $this->adminService->getPurchaseOrders($request->only(['search', 'status']));
        return view('admin.pos.index', compact('pos'));
    }

    public function posCreate()
    {
        $customers = Customer::orderBy('name')->get(['id', 'name']);
        $projects = Project::orderBy('project_name')->get(['id', 'project_name', 'customer_id']);
        $statuses = ['draft' => __('Draft'), 'diproses' => __('Diproses'), 'selesai' => __('Selesai'), 'dibatalkan' => __('Dibatalkan')];
        return view('admin.pos.create', compact('customers', 'projects', 'statuses'));
    }

    public function posStore(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'project_id' => 'nullable|exists:projects,id',
            'items' => 'required|string',
            'amount' => 'required|numeric|min:0',
            'status' => 'required|in:draft,diproses,selesai,dibatalkan',
            'issue_date' => 'required|date',
            'notes' => 'nullable|string|max:2000',
        ]);

        $this->adminService->createPurchaseOrder($validated);

        return redirect()->route('admin.pos.index')
            ->with('success', __('Purchase Order berhasil dibuat.'));
    }

    public function posShow(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->load(['customer', 'project', 'creator']);
        return view('admin.pos.show', compact('purchaseOrder'));
    }

    public function posEdit(PurchaseOrder $purchaseOrder)
    {
        $customers = Customer::orderBy('name')->get(['id', 'name']);
        $projects = Project::orderBy('project_name')->get(['id', 'project_name', 'customer_id']);
        $statuses = ['draft' => __('Draft'), 'diproses' => __('Diproses'), 'selesai' => __('Selesai'), 'dibatalkan' => __('Dibatalkan')];
        return view('admin.pos.edit', compact('purchaseOrder', 'customers', 'projects', 'statuses'));
    }

    public function posUpdate(Request $request, PurchaseOrder $purchaseOrder)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'project_id' => 'nullable|exists:projects,id',
            'items' => 'required|string',
            'amount' => 'required|numeric|min:0',
            'status' => 'required|in:draft,diproses,selesai,dibatalkan',
            'issue_date' => 'required|date',
            'notes' => 'nullable|string|max:2000',
        ]);

        $this->adminService->updatePurchaseOrder($purchaseOrder, $validated);

        return redirect()->route('admin.pos.index')
            ->with('success', __('Purchase Order berhasil diupdate.'));
    }

    public function posDestroy(PurchaseOrder $purchaseOrder)
    {
        $this->adminService->deletePurchaseOrder($purchaseOrder);
        return redirect()->route('admin.pos.index')
            ->with('success', __('Purchase Order berhasil dihapus.'));
    }

    // ========== PAYMENT ==========

    public function paymentsIndex(Request $request)
    {
        $payments = $this->adminService->getPayments($request->only(['search']));
        return view('admin.payments.index', compact('payments'));
    }

    public function paymentsCreate(Request $request)
    {
        $invoiceId = $request->get('invoice_id');
        $invoices = Invoice::with('customer')
            ->where('status', '!=', 'cancelled')
            ->orderBy('issue_date', 'desc')
            ->get(['id', 'invoice_number', 'customer_id', 'amount', 'status']);

        return view('admin.payments.create', compact('invoices', 'invoiceId'));
    }

    public function paymentsStore(Request $request)
    {
        $validated = $request->validate([
            'invoice_id' => 'required|exists:invoices,id',
            'amount' => 'required|numeric|min:0',
            'payment_date' => 'required|date',
            'payment_method' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:2000',
            'proof_file' => ['nullable', 'file', 'max:5120', \App\Rules\SecureFile::paymentProof()],
        ]);

        if ($request->hasFile('proof_file')) {
            $validated['proof_file'] = $request->file('proof_file')
                ->storeAs('payments', Str::uuid() . '.' . strtolower($request->file('proof_file')->getClientOriginalExtension()), 'private');
        }

        $this->adminService->createPayment($validated);

        return redirect()->route('admin.payments.index')
            ->with('success', __('Pembayaran berhasil dicatat.'));
    }

    public function paymentsShow(Payment $payment)
    {
        $payment->load(['invoice.customer', 'creator']);
        return view('admin.payments.show', compact('payment'));
    }

    public function paymentsProof(Payment $payment)
    {
        if (!$payment->proof_file) {
            abort(404, __('File bukti tidak ditemukan.'));
        }

        // MIME dari isi file + nosniff (bukti hanya jpg/png/pdf).
        return \App\Rules\SecureFile::fileResponse('private', $payment->proof_file, basename($payment->proof_file));
    }

    public function paymentsDestroy(Payment $payment)
    {
        if ($payment->proof_file) {
            Storage::disk('private')->delete($payment->proof_file);
        }
        $this->adminService->deletePayment($payment);
        return redirect()->route('admin.payments.index')
            ->with('success', __('Pembayaran berhasil dihapus.'));
    }
}

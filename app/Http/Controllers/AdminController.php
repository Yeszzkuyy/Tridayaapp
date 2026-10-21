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

class AdminController extends Controller
{
    public function __construct(private AdminService $adminService) {}

    // ========== INVOICE ==========

    public function invoicesIndex(Request $request)
    {
        $invoices = $this->adminService->getInvoices($request->only(['search', 'status']));
        return view('admin.invoices.index', compact('invoices'));
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
        $statuses = ['unpaid' => __('Unpaid'), 'paid' => __('Paid'), 'cancelled' => __('Cancelled')];
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

    public function posShow(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->load(['customer', 'project', 'creator']);
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
            ->with('success', __('Purchase Order updated successfully.'));
    }

    public function posDestroy(PurchaseOrder $purchaseOrder)
    {
        $this->adminService->deletePurchaseOrder($purchaseOrder);
        return redirect()->route('admin.pos.index')
            ->with('success', __('Purchase Order deleted successfully.'));
    }

    // ========== PAYMENT ==========

    public function paymentsIndex(Request $request)
    {
        $payments = $this->adminService->getPayments($request->only(['search']));
        return view('admin.payments.index', compact('payments'));
    }

    public function paymentsShow(Payment $payment)
    {
        $payment->load(['invoice.customer', 'creator']);
        return view('admin.payments.show', compact('payment'));
    }

    public function paymentsProof(Payment $payment)
    {
        if (!$payment->proof_file) {
            abort(404, __('Proof file not found.'));
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
            ->with('success', __('Payment deleted successfully.'));
    }
}

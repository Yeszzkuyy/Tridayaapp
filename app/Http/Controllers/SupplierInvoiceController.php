<?php

namespace App\Http\Controllers;

use App\Models\GoodsReceipt;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierPurchaseOrder;
use App\Rules\SecureFile;
use App\Services\ProcurementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Tahap 4 — Invoice Supplier (AP). Bukan `invoices` pelanggan (AR).
 */
class SupplierInvoiceController extends Controller
{
    public function __construct(private ProcurementService $procurement) {}

    public function index(Request $request)
    {
        $invoices = SupplierInvoice::query()
            ->with(['supplier', 'purchaseOrder', 'project', 'goodsReceipt'])
            ->withSum('payments', 'amount')
            ->when($request->filled('q'), fn ($q) => $q->where(function ($w) use ($request) {
                $w->whereLike('internal_ref_number', "%{$request->q}%")
                    ->orWhereLike('supplier_invoice_number', "%{$request->q}%")
                    ->orWhereHas('supplier', fn ($s) => $s->whereLike('name', "%{$request->q}%"))
                    ->orWhereHas('purchaseOrder', fn ($po) => $po->whereLike('po_number', "%{$request->q}%"));
            }))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('supplier_id'), fn ($q) => $q->where('supplier_id', $request->integer('supplier_id')))
            ->latest('issue_date')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.supplier-invoices.index', [
            'invoices' => $invoices,
            'statuses' => SupplierInvoice::STATUSES,
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(Request $request)
    {
        $orders = SupplierPurchaseOrder::query()
            ->with(['supplier', 'project'])
            ->whereIn('status', ['ordered', 'partially_received', 'received'])
            ->latest('issue_date')
            ->get();

        $selectedOrder = $request->integer('supplier_purchase_order_id')
            ? $orders->firstWhere('id', $request->integer('supplier_purchase_order_id'))
            : null;

        $receipts = $selectedOrder
            ? GoodsReceipt::where('supplier_purchase_order_id', $selectedOrder->id)
                ->where('status', 'verified')
                ->latest('received_date')
                ->get()
            : collect();

        return view('admin.supplier-invoices.create', [
            'orders' => $orders,
            'receipts' => $receipts,
            'selectedOrder' => $selectedOrder,
            'selectedReceiptId' => $request->integer('goods_receipt_id') ?: null,
            'types' => SupplierInvoice::TYPES,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'supplier_purchase_order_id' => 'required|exists:supplier_purchase_orders,id',
            'goods_receipt_id' => 'nullable|exists:goods_receipts,id',
            'supplier_invoice_number' => 'required|string|max:100',
            'invoice_type' => 'required|in:dp,partial,full',
            'amount' => 'required|numeric|min:0.01|max:999999999999',
            'issue_date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:issue_date',
            'notes' => 'nullable|string|max:2000',
            'file' => ['nullable', 'file', 'max:20480', SecureFile::documents()],
        ]);

        $order = SupplierPurchaseOrder::findOrFail($validated['supplier_purchase_order_id']);
        abort_unless(in_array($order->status, ['ordered', 'partially_received', 'received'], true), 422,
            __('Supplier invoices can only be recorded against an active purchase order.'));

        if (! empty($validated['goods_receipt_id'])) {
            $receipt = GoodsReceipt::where('supplier_purchase_order_id', $order->id)
                ->where('status', 'verified')
                ->find($validated['goods_receipt_id']);

            if (! $receipt) {
                throw ValidationException::withMessages([
                    'goods_receipt_id' => __('The selected receipt is not a verified receipt for this purchase order.'),
                ]);
            }
        }

        $duplicate = SupplierInvoice::where('supplier_id', $order->supplier_id)
            ->where('supplier_invoice_number', trim($validated['supplier_invoice_number']))
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'supplier_invoice_number' => __('This supplier invoice number has already been recorded.'),
            ]);
        }

        $filePath = $request->file('file')
            ? $request->file('file')->store('supplier-invoices', 'private')
            : null;

        $invoice = DB::transaction(function () use ($validated, $order, $filePath) {
            return SupplierInvoice::create([
                'internal_ref_number' => $this->procurement->generateSupplierInvoiceRef(),
                'supplier_invoice_number' => trim($validated['supplier_invoice_number']),
                'supplier_id' => $order->supplier_id,
                'supplier_purchase_order_id' => $order->id,
                'goods_receipt_id' => $validated['goods_receipt_id'] ?? null,
                'project_id' => $order->project_id,
                'invoice_type' => $validated['invoice_type'],
                'amount' => round((float) $validated['amount'], 2),
                'paid_amount' => 0,
                'status' => 'unpaid',
                'issue_date' => $validated['issue_date'],
                'due_date' => $validated['due_date'] ?? null,
                'invoice_file_path' => $filePath,
                'notes' => $validated['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);
        });

        return redirect()->route('admin.supplier-invoices.show', $invoice)
            ->with('success', __('Supplier invoice recorded.'));
    }

    public function show(SupplierInvoice $supplierInvoice)
    {
        $supplierInvoice->load([
            'supplier', 'purchaseOrder.items', 'goodsReceipt', 'project',
            'payments.creator', 'creator',
        ])->loadSum('payments', 'amount');

        return view('admin.supplier-invoices.show', compact('supplierInvoice'));
    }

    public function cancel(SupplierInvoice $supplierInvoice)
    {
        if ($supplierInvoice->status === 'cancelled') {
            return back()->with('success', __('This invoice is already cancelled.'));
        }

        if ($supplierInvoice->payments()->exists()) {
            throw ValidationException::withMessages([
                'status' => __('An invoice that already has payments cannot be cancelled.'),
            ]);
        }

        $supplierInvoice->update(['status' => 'cancelled']);

        return back()->with('success', __('Supplier invoice cancelled.'));
    }

    public function file(SupplierInvoice $supplierInvoice)
    {
        abort_unless($supplierInvoice->invoice_file_path, 404, __('Invoice file not found.'));

        return SecureFile::fileResponse('private', $supplierInvoice->invoice_file_path, basename($supplierInvoice->invoice_file_path));
    }
}

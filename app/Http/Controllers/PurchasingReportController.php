<?php

namespace App\Http\Controllers;

use App\Models\GoodsReceipt;
use App\Models\SupplierInvoice;
use App\Models\SupplierPurchaseOrder;
use Illuminate\Http\Request;

/**
 * Tahap 4 — Laporan pengadaan:
 * - terima barang verified yang belum ditagih supplier (unbilled);
 * - Supplier PO yang belum diterima lengkap;
 * - invoice supplier yang belum lunas (AP aging ringkas).
 */
class PurchasingReportController extends Controller
{
    public function index(Request $request)
    {
        $ptGroup = $request->input('pt_group') ?: null;

        $unbilled = GoodsReceipt::query()
            ->with(['purchaseOrder.supplier', 'project.customer', 'items'])
            ->where('status', 'verified')
            ->whereDoesntHave('supplierInvoices', fn ($q) => $q->where('status', '!=', 'cancelled'))
            ->when($ptGroup, fn ($q) => $q->whereHas('project.customer', fn ($c) => $c->where('pt_group', $ptGroup)))
            ->latest('received_date')
            ->get();

        $pendingOrders = SupplierPurchaseOrder::query()
            ->with(['supplier', 'project.customer', 'items'])
            ->whereIn('status', ['ordered', 'partially_received'])
            ->whereHas('items', fn ($q) => $q->whereColumn('quantity_received', '<', 'quantity_ordered'))
            ->when($ptGroup, fn ($q) => $q->whereHas('project.customer', fn ($c) => $c->where('pt_group', $ptGroup)))
            ->latest('issue_date')
            ->get();

        $outstandingInvoices = SupplierInvoice::query()
            ->with(['supplier', 'purchaseOrder', 'project'])
            ->withSum('payments', 'amount')
            ->whereIn('status', ['unpaid', 'partial'])
            ->when($ptGroup, fn ($q) => $q->whereHas('project.customer', fn ($c) => $c->where('pt_group', $ptGroup)))
            ->orderBy('due_date')
            ->get();

        $ptGroups = \App\Models\Customer::query()
            ->whereNotNull('pt_group')
            ->distinct()
            ->orderBy('pt_group')
            ->pluck('pt_group');

        return view('admin.purchasing.reports', [
            'unbilled' => $unbilled,
            'pendingOrders' => $pendingOrders,
            'outstandingInvoices' => $outstandingInvoices,
            'ptGroup' => $ptGroup,
            'ptGroups' => $ptGroups,
        ]);
    }
}

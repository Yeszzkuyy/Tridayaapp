<?php

namespace App\Services;

use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\GoodsDelivery;
use App\Models\SupplierInvoice;
use App\Models\SupplierPurchaseOrder;
use App\Models\SupplierPurchaseOrderItem;

/**
 * Penomoran & rekalkulasi dokumen pengadaan (Tahap 3–4).
 *
 * Semua agregat dihitung dari dokumen sumber, bukan dari input user:
 * - quantity_received item PO = total receipt verified;
 * - status PO = perbandingan total diterima vs total dipesan;
 * - paid_amount invoice supplier = total supplier_payments.
 */
class ProcurementService
{
    public function generateReceiptNumber(): string
    {
        return $this->generateNumber('GR', GoodsReceipt::withTrashed());
    }

    public function generateDeliveryNumber(): string
    {
        return $this->generateNumber('DO', GoodsDelivery::withTrashed());
    }

    public function generateSupplierInvoiceRef(): string
    {
        return $this->generateNumber('SINV', SupplierInvoice::withTrashed());
    }

    private function generateNumber(string $prefix, $query): string
    {
        $year = date('Y');
        $last = $query->whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->value($prefix === 'GR' ? 'receipt_number' : ($prefix === 'DO' ? 'delivery_number' : 'internal_ref_number'));

        $seq = $last ? (int) substr($last, strrpos($last, '-') + 1) + 1 : 1;

        return $prefix . '-' . $year . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Hitung ulang quantity_received item PO dari receipt terverifikasi,
     * lalu sinkronkan status PO (ordered/partially_received/received).
     */
    public function recalculatePurchaseOrderReceipts(SupplierPurchaseOrder $order): void
    {
        $order->loadMissing('items');

        foreach ($order->items as $item) {
            $received = (float) GoodsReceiptItem::query()
                ->where('supplier_purchase_order_item_id', $item->id)
                ->whereHas('receipt', fn ($q) => $q->where('status', 'verified'))
                ->sum('quantity_received');

            $item->forceFill(['quantity_received' => round($received, 2)])->save();
        }

        $order->refresh()->load('items');

        if (! in_array($order->status, ['ordered', 'partially_received', 'received'], true)) {
            return;
        }

        $ordered = (float) $order->items->sum('quantity_ordered');
        $received = (float) $order->items->sum('quantity_received');

        $status = match (true) {
            $received <= 0 => 'ordered',
            $received >= $ordered => 'received',
            default => 'partially_received',
        };

        $order->update(['status' => $status]);
    }

    /**
     * Sisa kuantitas yang masih boleh diterima untuk satu item PO
     * (dihitung dari receipt verified; draft belum mengurangi).
     */
    public function remainingQuantity(SupplierPurchaseOrderItem $item): float
    {
        $verified = (float) GoodsReceiptItem::query()
            ->where('supplier_purchase_order_item_id', $item->id)
            ->whereHas('receipt', fn ($q) => $q->where('status', 'verified'))
            ->sum('quantity_received');

        return round((float) $item->quantity_ordered - $verified, 2);
    }

    /**
     * Sinkronkan paid_amount & status invoice supplier dari pembayaran.
     */
    public function recalculateSupplierInvoice(SupplierInvoice $invoice): void
    {
        if ($invoice->status === 'cancelled') {
            $invoice->update(['paid_amount' => (float) $invoice->payments()->sum('amount')]);

            return;
        }

        $paid = round((float) $invoice->payments()->sum('amount'), 2);
        $amount = (float) $invoice->amount;

        $status = match (true) {
            $paid <= 0 => 'unpaid',
            $paid >= $amount => 'paid',
            default => 'partial',
        };

        $invoice->update(['paid_amount' => $paid, 'status' => $status]);
    }
}

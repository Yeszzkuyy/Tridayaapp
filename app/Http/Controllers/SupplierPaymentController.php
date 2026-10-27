<?php

namespace App\Http\Controllers;

use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Rules\SecureFile;
use App\Services\ProcurementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Tahap 4 — Pembayaran ke Supplier. Boleh dicicil (partial).
 * Status invoice dihitung ulang dari total pembayaran.
 */
class SupplierPaymentController extends Controller
{
    public function __construct(private ProcurementService $procurement) {}

    public function store(Request $request, SupplierInvoice $supplierInvoice)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01|max:999999999999',
            'payment_date' => 'required|date',
            'payment_method' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:2000',
            'idempotency_key' => 'nullable|string|max:64',
            'proof_file' => ['nullable', 'file', 'max:5120', SecureFile::paymentProof()],
        ]);

        $key = $validated['idempotency_key'] ?? null;
        if ($key && SupplierPayment::where('idempotency_key', $key)->exists()) {
            return back()->with('success', __('This supplier payment has already been recorded.'));
        }

        if ($supplierInvoice->status === 'cancelled') {
            throw ValidationException::withMessages([
                'amount' => __('A cancelled invoice cannot receive payments.'),
            ]);
        }

        $remaining = $supplierInvoice->remainingAmount();
        if ((float) $validated['amount'] > $remaining + 0.0001) {
            throw ValidationException::withMessages([
                'amount' => __('Payment exceeds the remaining invoice amount (:remaining).', [
                    'remaining' => number_format($remaining, 2),
                ]),
            ]);
        }

        $proofPath = $request->file('proof_file')
            ? $request->file('proof_file')->store('supplier-payments', 'private')
            : null;

        DB::transaction(function () use ($validated, $supplierInvoice, $proofPath) {
            $supplierInvoice->payments()->create([
                'supplier_purchase_order_id' => $supplierInvoice->supplier_purchase_order_id,
                'amount' => round((float) $validated['amount'], 2),
                'payment_date' => $validated['payment_date'],
                'payment_method' => $validated['payment_method'] ?? null,
                'proof_file' => $proofPath,
                'notes' => $validated['notes'] ?? null,
                'idempotency_key' => $validated['idempotency_key'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $this->procurement->recalculateSupplierInvoice($supplierInvoice);
        });

        return back()->with('success', __('Supplier payment recorded.'));
    }

    public function destroy(SupplierPayment $supplierPayment)
    {
        $invoice = $supplierPayment->invoice;

        DB::transaction(function () use ($supplierPayment, $invoice) {
            $supplierPayment->delete();
            $this->procurement->recalculateSupplierInvoice($invoice);
        });

        return back()->with('success', __('Supplier payment deleted.'));
    }

    public function proof(SupplierPayment $supplierPayment)
    {
        abort_unless($supplierPayment->proof_file, 404, __('Proof file not found.'));

        return SecureFile::fileResponse('private', $supplierPayment->proof_file, basename($supplierPayment->proof_file));
    }
}

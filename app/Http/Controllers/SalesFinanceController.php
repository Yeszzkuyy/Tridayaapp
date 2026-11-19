<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\User;

/**
 * Tampilan read-only Invoice/PI untuk Sales (verifikasi nomor & rincian).
 * Sales hanya boleh melihat invoice milik quotation/project/customer-nya.
 */
class SalesFinanceController extends Controller
{
    private function ownsInvoice(User $user, Invoice $invoice): bool
    {
        $proposal = $invoice->proposal;
        if ($proposal) {
            if ((int) $proposal->created_by === (int) $user->id) {
                return true;
            }
            if ($proposal->lead && (int) $proposal->lead->assigned_to === (int) $user->id) {
                return true;
            }
        }

        if ($invoice->project && (int) $invoice->project->sales_user_id === (int) $user->id) {
            return true;
        }

        if ($invoice->customer) {
            return $invoice->customer->leads()
                ->where('assigned_to', $user->id)
                ->exists();
        }

        return false;
    }

    public function show(Invoice $invoice)
    {
        $user = auth()->user();

        abort_unless(
            $user && ($user->hasRole('super-admin') || $user->can('manage-sales-leads') || $this->ownsInvoice($user, $invoice)),
            403
        );

        $invoice->load(['customer', 'project', 'proposal', 'items', 'payments', 'creator', 'documents']);

        return view('sales.invoices.show', ['invoice' => $invoice]);
    }
}

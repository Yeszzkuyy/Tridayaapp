<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\Proposal;
use Illuminate\Http\Request;

/**
 * Antrean handoff Sales → Admin/Purchasing.
 *
 * Hanya baca (read-only). Antrean DITURUNKAN dari data quotation yang sudah
 * ada (accepted + customer PO terunggah) sehingga membuka halaman berulang
 * kali tidak pernah membuat proyek/entri handoff duplikat.
 *
 * Batas konsep:
 * - Customer PO = pesanan pelanggan kepada 3DY (menempel di quotation).
 * - Supplier Purchase Order (tabel purchase_orders) TIDAK dibuat di sini;
 *   modul itu di luar lingkup Paket 3.
 */
class PurchasingHandoffController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->only(['q', 'pt_group']);
        $user = $request->user();

        $base = Proposal::query()
            ->with(['lead.customer', 'lead.assignee', 'creator', 'items', 'project'])
            ->where('status', 'accepted');

        if (! empty($filters['q'])) {
            $q = trim((string) $filters['q']);
            $base->where(function ($query) use ($q) {
                $query->where('proposal_number', 'like', "%{$q}%")
                    ->orWhere('po_number', 'like', "%{$q}%")
                    ->orWhere('customer', 'like', "%{$q}%");
            });
        }

        if (! empty($filters['pt_group']) && in_array($filters['pt_group'], Lead::PT_GROUPS, true)) {
            $base->whereHas('lead', fn ($query) => $query->where('pt_group', $filters['pt_group']));
        }

        // Role read-only yang bukan Admin/Purchasing/Management/CEO (mis. sales
        // yang punya view-admin) hanya melihat quotation miliknya sendiri —
        // mencegah data pipeline sales lain terbuka lewat antrean ini.
        $fullAccess = $user->can('manage-admin')
            || $user->can('manage-sales-leads')
            || $user->hasRole('ceo');

        if (! $fullAccess) {
            $base->where(function ($query) use ($user) {
                $query->where('created_by', $user->id)
                    ->orWhereHas('lead', fn ($lead) => $lead->where('assigned_to', $user->id));
            });
        }

        // Siap diproses Purchasing = accepted + customer PO sudah diunggah.
        $readyQuery = (clone $base)->whereNotNull('po_document_path');
        $waitingQuery = (clone $base)->whereNull('po_document_path');

        $readyCount = (clone $readyQuery)->count();
        $waitingCount = (clone $waitingQuery)->count();

        $proposals = $readyQuery
            ->orderByDesc('po_date')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.purchasing.handoff.index', [
            'proposals' => $proposals,
            'filters' => $filters,
            'readyCount' => $readyCount,
            'waitingCount' => $waitingCount,
            'ptGroups' => Lead::PT_GROUPS,
        ]);
    }

    public function show(Request $request, Proposal $proposal)
    {
        abort_unless($proposal->isReadyForPurchasing(), 404);

        $user = $request->user();
        $fullAccess = $user->can('manage-admin')
            || $user->can('manage-sales-leads')
            || $user->hasRole('ceo');

        if (! $fullAccess) {
            abort_unless(
                (int) $proposal->created_by === (int) $user->id
                || (int) $proposal->lead?->assigned_to === (int) $user->id,
                403
            );
        }

        $proposal->load([
            'lead.customer', 'lead.assignee', 'creator', 'items', 'project',
            'supplierPurchaseOrders.supplier',
        ]);

        return view('admin.purchasing.handoff.show', compact('proposal'));
    }
}

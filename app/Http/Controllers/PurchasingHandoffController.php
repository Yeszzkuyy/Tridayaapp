<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\Proposal;
use App\Notifications\ProposalStatusNotification;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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
    /** Daftar tahap handoff yang bisa difilter lewat tab. */
    public const STAGE_FILTERS = ['all', 'waiting_po', 'po_received', 'in_purchasing', 'project_created', 'bast_closed'];

    public function index(Request $request)
    {
        $filters = $request->only(['q', 'pt_group', 'stage']);
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
            || $user->can('view-admin-hub')
            || $user->hasRole('ceo');

        if (! $fullAccess) {
            $base->where(function ($query) use ($user) {
                $query->where('created_by', $user->id)
                    ->orWhereHas('lead', fn ($lead) => $lead->where('assigned_to', $user->id));
            });
        }

        $stage = in_array($filters['stage'] ?? 'all', self::STAGE_FILTERS, true)
            ? $filters['stage']
            : 'all';

        // Jumlah per tab dihitung dari query yang sama (ikut filter q & PT)
        // supaya angka di tab selalu konsisten dengan isi tabel.
        $stageCounts = [];
        foreach (self::STAGE_FILTERS as $key) {
            $stageCounts[$key] = $key === 'all'
                ? (clone $base)->count()
                : self::applyStageFilter(clone $base, $key)->count();
        }

        $proposals = self::applyStageFilter(clone $base, $stage)
            ->orderByDesc('po_date')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.purchasing.handoff.index', [
            'proposals' => $proposals,
            'filters' => $filters,
            'stage' => $stage,
            'stageCounts' => $stageCounts,
            'ptGroups' => Lead::PT_GROUPS,
        ]);
    }

    /**
     * Persempit query quotation accepted ke satu tahap handoff.
     */
    private static function applyStageFilter($query, string $stage)
    {
        return match ($stage) {
            'waiting_po' => $query->whereNull('po_document_path'),
            'po_received' => $query->whereNotNull('po_document_path')
                ->whereDoesntHave('supplierPurchaseOrders')
                ->whereDoesntHave('project'),
            'in_purchasing' => $query->whereNotNull('po_document_path')
                ->whereHas('supplierPurchaseOrders')
                ->whereDoesntHave('project'),
            'project_created' => $query->whereHas('project', fn ($project) => $project->whereNull('bast_ready_at')),
            'bast_closed' => $query->whereHas('project', fn ($project) => $project->whereNotNull('bast_ready_at')),
            default => $query,
        };
    }

    public function show(Request $request, Proposal $proposal)
    {
        abort_unless($proposal->isReadyForPurchasing(), 404);

        $user = $request->user();
        $fullAccess = $user->can('manage-admin')
            || $user->can('manage-sales-leads')
            || $user->can('view-admin-hub')
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
            'supplierPurchaseOrders.supplier', 'attachments.uploader',
        ]);

        $workTypes = ! $proposal->project
            ? \App\Models\WorkType::orderBy('name')->get(['id', 'name'])
            : collect();

        return view('admin.purchasing.handoff.show', compact('proposal', 'workTypes'));
    }

    public function verifyPo(Request $request, Proposal $proposal)
    {
        $user = $request->user();
        abort_unless($user->can('manage-admin') || $user->hasRole('ceo'), 403);
        abort_unless($proposal->isReadyForPurchasing(), 422);
        abort_if($proposal->isPoReturned(), 422, __('This customer PO was returned to Sales. Wait for the revised PO before verifying.'));

        $missing = $proposal->poCompletenessErrors();
        if ($missing !== []) {
            throw ValidationException::withMessages([
                'po' => __('Customer PO is incomplete: :fields', ['fields' => implode(' ', $missing)]),
            ]);
        }

        $proposal->update([
            'po_verified_at' => now(),
            'po_verified_by' => $user->id,
            'po_returned_at' => null,
            'po_returned_by' => null,
            'po_return_reason' => null,
        ]);

        if ($proposal->lead) {
            LeadActivity::create([
                'lead_id' => $proposal->lead_id,
                'user_id' => $user->id,
                'action' => 'po_verified',
                'changes' => ['number' => $proposal->proposal_number, 'po_number' => $proposal->po_number],
            ]);
        }

        // Sales pemilik lead perlu tahu PO sudah lolos verifikasi dan
        // boleh lanjut convert.
        $assignee = $proposal->lead?->assignee ?: $proposal->creator;
        if ($assignee && (int) $assignee->id !== (int) $user->id) {
            $assignee->notify(new ProposalStatusNotification($proposal, 'po_verified'));
        }

        return back()->with('success', __('Customer PO verified successfully.'));
    }

    public function returnPo(Request $request, Proposal $proposal)
    {
        $user = $request->user();
        abort_unless($user->can('manage-admin') || $user->hasRole('ceo'), 403);
        abort_unless($proposal->isReadyForPurchasing(), 422);

        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $proposal->update([
            'po_returned_at' => now(),
            'po_returned_by' => $user->id,
            'po_return_reason' => $validated['reason'],
            'po_verified_at' => null,
            'po_verified_by' => null,
        ]);

        if ($proposal->lead) {
            LeadActivity::create([
                'lead_id' => $proposal->lead_id,
                'user_id' => $user->id,
                'action' => 'po_returned',
                'changes' => [
                    'number' => $proposal->proposal_number,
                    'po_number' => $proposal->po_number,
                    'reason' => $validated['reason'],
                ],
            ]);
        }

        $assignee = $proposal->lead?->assignee ?: $proposal->creator;
        if ($assignee && (int) $assignee->id !== (int) $user->id) {
            $assignee->notify(new ProposalStatusNotification($proposal, 'po_returned'));
        }

        return back()->with('success', __('Customer PO returned to Sales with revision notes.'));
    }
}

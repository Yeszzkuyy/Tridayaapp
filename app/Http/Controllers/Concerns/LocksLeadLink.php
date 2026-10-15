<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Lead;
use App\Support\PtAccess;

trait LocksLeadLink
{
    /**
     * Kunci relasi ke lead untuk sales biasa (own-lead scope,
     * sama seperti SalesService::scopeToOwnLeads):
     * - lead milik sales lain -> 403
     * - tanpa lead tapi punya tepat 1 lead aktif di customer itu -> otomatis tautkan
     * - tanpa lead tapi punya >1 lead aktif -> 422 (minta pilih lead)
     * Management & co dibiarkan fleksibel seperti sekarang.
     */
    private function lockLeadLink(array &$validated): void
    {
        $user = auth()->user();
        if (!$user || !PtAccess::isSalesLike($user) || $user->can('manage-marketing') || $user->can('manage-sales-leads')) {
            return;
        }

        if (!empty($validated['lead_id'])
            && (int) Lead::whereKey($validated['lead_id'])->value('assigned_to') !== (int) $user->id) {
            abort(403);
        }

        if (empty($validated['lead_id'])) {
            $ownActive = Lead::where('customer_id', $validated['customer_id'])
                ->where('assigned_to', $user->id)
                ->whereNotIn('status', ['won', 'lost'])
                ->pluck('id');

            if ($ownActive->count() === 1) {
                $validated['lead_id'] = $ownActive->first();
            } elseif ($ownActive->count() > 1) {
                abort(422, __('Pilih lead terkait: customer ini punya lebih dari satu lead aktif.'));
            }
        }
    }
}

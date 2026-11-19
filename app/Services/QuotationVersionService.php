<?php

namespace App\Services;

use App\Models\Proposal;
use App\Models\ProposalItem;

/**
 * Simpan snapshot versi quotation agar riwayat revisi bisa dibaca Sales,
 * Inside Sales, dan Management tanpa bergantung pada item yang sudah diubah.
 */
class QuotationVersionService
{
    public function record(
        Proposal $proposal,
        ?float $totalBefore = null,
        ?string $summary = null,
        ?int $changedBy = null,
    ): void {
        $items = $proposal->items()->get();
        $next = ((int) $proposal->versions()->max('version')) + 1;

        $proposal->versions()->create([
            'version' => $next,
            'label' => 'v'.$next,
            'snapshot' => $items->map(fn (ProposalItem $item) => [
                'description' => $item->description,
                'quantity' => (float) $item->quantity,
                'unit' => $item->unit,
                'unit_price' => (float) $item->unit_price,
                'unit_cost' => $item->unit_cost !== null ? (float) $item->unit_cost : null,
                'subtotal' => (float) $item->subtotal,
            ])->values()->all(),
            'total_before' => $totalBefore,
            'total_after' => (float) $proposal->grand_total,
            'change_summary' => $summary,
            'changed_by' => $changedBy ?? auth()->id(),
        ]);
    }
}

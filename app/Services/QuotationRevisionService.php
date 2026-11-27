<?php

namespace App\Services;

use App\Models\Proposal;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Membuat revisi quotation yang immutable.
 *
 * Versi lama TIDAK diubah isinya: hanya ditandai `is_latest = false`.
 * Versi baru dibuat sebagai baris proposal terpisah dengan nomor berakhiran
 * R1, R2, dst., menyalin isi + item, lalu ditautkan ke dokumen induk lewat
 * `parent_quotation_id`.
 */
class QuotationRevisionService
{
    public function __construct(private QuotationNumberService $numbers) {}

    public function revise(Proposal $parent): Proposal
    {
        return DB::transaction(function () use ($parent) {
            $locked = Proposal::query()
                ->whereKey($parent->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->is_latest) {
                throw new RuntimeException(__('This quotation is not the latest version.'));
            }

            if (! $locked->canBeRevised()) {
                throw new RuntimeException(__('This quotation cannot be revised in its current status.'));
            }

            $number = $this->numbers->nextRevision($locked);

            if (Proposal::withTrashed()->where('proposal_number', $number['proposal_number'])->exists()) {
                throw new RuntimeException(__('The revision number already exists. Please try again.'));
            }

            $rootId = (int) ($locked->parent_quotation_id ?: $locked->id);

            // Versi lama tetap utuh sebagai arsip, hanya dilepas dari status aktif.
            Proposal::query()->whereKey($locked->getKey())->update(['is_latest' => false]);

            $revision = Proposal::create([
                'lead_id' => $locked->lead_id,
                'lead_task_id' => $locked->lead_task_id,
                'parent_quotation_id' => $rootId,
                'is_latest' => true,
                ...$number,
                'created_by' => $locked->created_by,
                'proposal_date' => $locked->proposal_date,
                'valid_until' => $locked->valid_until,
                'customer' => $locked->customer,
                'customer_address' => $locked->customer_address,
                'contact_name' => $locked->contact_name,
                'contact_email' => $locked->contact_email,
                'contact_phone' => $locked->contact_phone,
                'project_description' => $locked->project_description,
                'payment_terms_text' => $locked->payment_terms_text,
                'delivery_terms' => $locked->delivery_terms,
                'warranty_terms' => $locked->warranty_terms,
                'letterhead_pt' => $locked->letterhead_pt,
                'notes' => $locked->notes,
                'internal_notes' => $locked->internal_notes,
                'payment_scheme' => $locked->payment_scheme,
                'dp_percent' => $locked->dp_percent,
                'term_days' => $locked->term_days,
                'payment_terms' => $locked->payment_terms,
                'discount' => $locked->discount,
                'tax' => $locked->tax,
                'tax_rate' => $locked->tax_rate,
                'default_margin_percent' => $locked->default_margin_percent,
                'subtotal' => $locked->subtotal,
                'grand_total' => $locked->grand_total,
                'total_cost' => $locked->total_cost,
                'gross_profit' => $locked->gross_profit,
                'gross_margin' => $locked->gross_margin,
                'status' => 'draft',
            ]);

            foreach ($locked->items()->get() as $item) {
                $revision->items()->create([
                    'product_id' => $item->product_id,
                    'product_price_log_id' => $item->product_price_log_id,
                    'description' => $item->description,
                    'scope_of_supply' => $item->scope_of_supply,
                    'lead_time' => $item->lead_time,
                    'stock_status' => $item->stock_status,
                    'stock_eta' => $item->stock_eta,
                    'warranty_info' => $item->warranty_info,
                    'section' => $item->section,
                    'quantity' => $item->quantity,
                    'unit' => $item->unit,
                    'unit_price' => $item->unit_price,
                    'unit_cost' => $item->unit_cost,
                    'target_margin_percent' => $item->target_margin_percent,
                    'discount' => $item->discount,
                    'subtotal' => $item->subtotal,
                ]);
            }

            $revision->recalculate();

            return $revision->refresh();
        });
    }
}

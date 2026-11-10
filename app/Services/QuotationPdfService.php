<?php

namespace App\Services;

use App\Models\Proposal;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class QuotationPdfService
{
    /**
     * Render quotation ke PDF A4, simpan di disk privat, lalu tautkan ke proposal.
     */
    public function generate(Proposal $proposal): string
    {
        $proposal->loadMissing(['items.product', 'lead.customer', 'lead.assignee', 'creator']);

        $pt = strtoupper((string) ($proposal->letterhead_pt
            ?: $proposal->lead?->customer?->pt_group
            ?: 'WANI'));

        $company = config('companies.'.$pt) ?? config('companies.WANI');

        $pdf = Pdf::loadView('sales.proposals.pdf', [
            'proposal' => $proposal,
            'company' => $company,
        ])
            ->setPaper('a4')
            ->setOptions([
                'isRemoteEnabled' => true,
                'chroot' => base_path(),
                'defaultFont' => 'DejaVu Sans',
            ]);

        $filename = preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $proposal->proposal_number).'.pdf';
        $path = 'quotations/'.$filename;

        Storage::disk('private')->put($path, $pdf->output());

        $proposal->forceFill([
            'pdf_path' => $path,
            'pdf_generated_at' => now(),
        ])->save();

        return $path;
    }
}

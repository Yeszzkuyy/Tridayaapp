<?php

namespace App\Services;

use App\Models\Proposal;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class QuotationPdfService
{
    /** Template resmi per kode PT. */
    public const TEMPLATES = [
        'NTI' => 'nti',
        'MGK' => 'mgk',
        'TPS' => 'tps',
        'WANI' => 'wani',
    ];

    /**
     * Kode PT yang dipakai quotation: letterhead pilihan Sales, lalu PT
     * customer, lalu WANI sebagai cadangan.
     */
    public function companyCode(Proposal $proposal): string
    {
        $code = strtoupper((string) ($proposal->letterhead_pt
            ?: $proposal->lead?->customer?->pt_group
            ?: 'WANI'));

        return array_key_exists($code, self::TEMPLATES) ? $code : 'WANI';
    }

    public function templateKey(Proposal $proposal): string
    {
        return self::TEMPLATES[$this->companyCode($proposal)];
    }

    public function viewName(Proposal $proposal): string
    {
        return 'sales.proposals.pdf.'.$this->templateKey($proposal);
    }

    /**
     * Data yang dipakai bersama oleh keempat template resmi.
     *
     * @return array<string, mixed>
     */
    public function viewData(Proposal $proposal, bool $forPdf = true): array
    {
        $proposal->loadMissing([
            'items.product',
            'lead.customer',
            'lead.assignee',
            'creator',
        ]);

        return [
            'proposal' => $proposal,
            'company' => $this->company($proposal),
            'sales' => $this->salesOwner($proposal),
            'forPdf' => $forPdf,
            'previewMode' => false,
        ];
    }

    /**
     * Render quotation ke PDF A4 memakai template resmi PT, simpan di disk
     * privat, lalu tautkan ke proposal.
     */
    public function generate(Proposal $proposal): string
    {
        $this->ensureFontDirectories();

        $pdf = Pdf::loadView($this->viewName($proposal), $this->viewData($proposal, true))
            ->setPaper('a4')
            ->setOptions([
                'isRemoteEnabled' => true,
                'chroot' => base_path(),
                'defaultFont' => 'DejaVu Sans',
                'fontDir' => storage_path('fonts'),
                'fontCache' => storage_path('fonts'),
                'tempDir' => storage_path('app/dompdf'),
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

    /**
     * Isi awal email quotation: tujuan, subjek, dan body otomatis.
     *
     * @return array{to:string, subject:string, body:string}
     */
    public function emailDraft(Proposal $proposal): array
    {
        $proposal->loadMissing(['lead.customer', 'lead.assignee', 'creator']);
        $company = $this->company($proposal);
        $sales = $this->salesOwner($proposal);
        $number = $this->numberWithRevision($proposal);

        $to = (string) ($proposal->contact_email
            ?: $proposal->lead?->customer?->email
            ?: '');

        $subject = __('Quotation :number - :company', [
            'number' => $number,
            'company' => $company['name'] ?? 'PT Wiskira Adinata Indonesia',
        ]);

        $body = implode("\n", array_filter([
            __('Dear :name,', ['name' => $proposal->contact_name ?: __('Sir/Madam')]),
            '',
            __('Please find attached our official quotation :number from :company for :project.', [
                'number' => $number,
                'company' => $company['name'] ?? '',
                'project' => $proposal->project_description ?: ($proposal->customer ?: '-'),
            ]),
            '',
            __('Total: Rp :total', ['total' => number_format((float) $proposal->grand_total, 0, ',', '.')]),
            __('Valid until: :date', ['date' => $proposal->valid_until?->format('d F Y') ?: '-']),
            '',
            __('The quotation PDF is attached to this email. Please let us know if you need any clarification.'),
            '',
            __('Best regards,'),
            $sales?->name ?: ($company['signatory']['name'] ?? ''),
            $sales?->phone ?: ($company['contact_phone'] ?? ''),
            $sales?->email ?: ($company['email'] ?? ''),
        ], fn ($line) => $line !== null));

        return ['to' => $to, 'subject' => $subject, 'body' => $body];
    }

    /**
     * Nomor quotation + penanda revisi (R1, R2, ...) bila ada.
     * Nomor tipe baru sudah memuat R1/R2, jadi tidak digandakan.
     */
    public function numberWithRevision(Proposal $proposal): string
    {
        $number = (string) $proposal->proposal_number;
        $revision = (int) ($proposal->revision_number ?? 0);

        if ($revision > 0 && ! preg_match('/R\d+/i', $number)) {
            return $number.' R'.$revision;
        }

        return $number;
    }

    public function salesOwner(Proposal $proposal): ?User
    {
        return $proposal->lead?->assignee ?: $proposal->creator;
    }

    /** @return array<string, mixed> */
    private function company(Proposal $proposal): array
    {
        return config('companies.'.$this->companyCode($proposal))
            ?: config('companies.WANI')
            ?: [];
    }

    private function ensureFontDirectories(): void
    {
        foreach ([storage_path('fonts'), storage_path('app/dompdf')] as $directory) {
            if (! is_dir($directory)) {
                @mkdir($directory, 0775, true);
            }
        }
    }
}

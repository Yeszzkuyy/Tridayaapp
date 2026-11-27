<?php

namespace App\Services;

use App\Models\Proposal;
use App\Models\User;
use RuntimeException;

/**
 * Generator nomor quotation.
 *
 * Pola: `{urut}{R-versi}/{PT}-{INISIAL}/{tahun}`
 *   awal   : 109/NTI-DN/2026
 *   revisi : 109R1/NTI-DN/2026, 109R2/NTI-DN/2026, ...
 *
 * Nomor urut berjalan per PT per tahun. Inisial adalah milik pembuat
 * quotation dan disimpan eksplisit di proposal agar revisi tetap memakai
 * identitas dokumen yang sama.
 */
class QuotationNumberService
{
    /** Nomor untuk quotation baru (revisi = 0). */
    public function generate(string $pt, ?User $creator = null, ?int $year = null): string
    {
        return $this->next($pt, $creator, $year)['proposal_number'];
    }

    /**
     * Nomor + metadata pelacakan untuk quotation baru.
     *
     * @return array{proposal_number:string,sequence_number:int,revision_number:int,company_code:string,quotation_year:int,user_initials:string}
     */
    public function next(string $pt, ?User $creator = null, ?int $year = null): array
    {
        $company = $this->companyCode($pt);
        $year = $year ?? (int) now()->year;
        $initials = $this->initialsFor($creator);

        for ($attempt = 0; $attempt < 25; $attempt++) {
            $sequence = $this->nextSequence($company, $year) + $attempt;
            $number = $this->makeNumber($sequence, $company, $initials, $year);

            if (! Proposal::withTrashed()->where('proposal_number', $number)->exists()) {
                return [
                    'proposal_number' => $number,
                    'sequence_number' => $sequence,
                    'revision_number' => 0,
                    'company_code' => $company,
                    'quotation_year' => $year,
                    'user_initials' => $initials,
                ];
            }
        }

        throw new RuntimeException('Failed to generate a unique quotation number.');
    }

    /**
     * Nomor revisi berikutnya untuk satu dokumen. Inisial, PT, tahun, dan
     * nomor urut diambil dari quotation induk supaya nomor tetap satu
     * identitas.
     *
     * @return array{proposal_number:string,sequence_number:int,revision_number:int,company_code:string,quotation_year:int,user_initials:string}
     */
    public function nextRevision(Proposal $parent): array
    {
        $meta = $this->metadata($parent);
        $revision = (int) $parent->revision_number + 1;
        $number = $this->makeNumber(
            $meta['sequence_number'],
            $meta['company_code'],
            $meta['user_initials'],
            $meta['quotation_year'],
            $revision,
        );

        return array_merge($meta, [
            'proposal_number' => $number,
            'revision_number' => $revision,
        ]);
    }

    /**
     * Metadata dokumen dari record lama (sebelum kolom revisi terisi).
     *
     * @return array{sequence_number:int,revision_number:int,company_code:string,quotation_year:int,user_initials:string}
     */
    public function metadata(Proposal $proposal): array
    {
        $company = strtoupper((string) ($proposal->company_code
            ?: $proposal->letterhead_pt
            ?: $proposal->lead?->pt_group
            ?: $proposal->lead?->customer?->pt_group
            ?: 'WANI'));
        $company = $this->companyCode($company);

        $year = (int) ($proposal->quotation_year
            ?: $proposal->proposal_date?->year
            ?: $proposal->created_at?->year
            ?: now()->year);

        $sequence = (int) ($proposal->sequence_number
            ?: $this->sequenceFromNumber((string) $proposal->proposal_number)
            ?: $this->nextSequence($company, $year));

        return [
            'sequence_number' => $sequence,
            'revision_number' => (int) $proposal->revision_number,
            'company_code' => $company,
            'quotation_year' => $year,
            'user_initials' => $proposal->user_initials
                ?: $this->initialsFor($proposal->creator),
        ];
    }

    public function makeNumber(
        int $sequence,
        string $company,
        string $initials,
        int $year,
        int $revision = 0,
    ): string {
        $sequence = str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
        $suffix = $revision > 0 ? 'R'.$revision : '';

        return $sequence.$suffix.'/'.$this->companyCode($company).'-'.$this->initials($initials).'/'.$year;
    }

    public function nextSequence(string $company, int $year): int
    {
        $max = (int) Proposal::withTrashed()
            ->where('company_code', $this->companyCode($company))
            ->where('quotation_year', $year)
            ->max('sequence_number');

        if ($max > 0) {
            return $max + 1;
        }

        // Cadangan bila migrasi backfill belum sempat berjalan.
        $fallback = Proposal::withTrashed()
            ->whereYear('created_at', $year)
            ->pluck('proposal_number')
            ->map(fn ($number) => $this->sequenceFromNumber((string) $number))
            ->filter()
            ->max();

        return ((int) $fallback) + 1;
    }

    public function companyCode(string $pt): string
    {
        $pt = strtoupper(trim($pt));
        $companies = array_keys((array) config('companies', []));

        if ($pt !== '' && in_array($pt, $companies, true)) {
            return $pt;
        }

        return in_array('WANI', $companies, true) ? 'WANI' : ($companies[0] ?? 'WANI');
    }

    public function initialsFor(?User $creator): string
    {
        return $this->initials($creator?->quotationInitials() ?: 'X');
    }

    private function initials(string $initials): string
    {
        $initials = strtoupper(preg_replace('/[^A-Za-z]/', '', $initials) ?? '');

        return $initials !== '' ? substr($initials, 0, 10) : 'X';
    }

    private function sequenceFromNumber(string $number): ?int
    {
        if (preg_match('#^(\d{1,6})(?:R\d+)?/[A-Z]{2,10}-[A-Z]+/\d{4}$#', trim($number), $m)) {
            return (int) $m[1];
        }

        if (preg_match('#^[A-Z]{2,10}/(\d{1,6})(?:R\d+)?/[A-Z]+/\d{4}$#', trim($number), $m)) {
            return (int) $m[1];
        }

        if (preg_match('#^[A-Z]+/\d{4}/[^/]+/(\d{1,6})$#i', trim($number), $m)) {
            return (int) $m[1];
        }

        return null;
    }
}

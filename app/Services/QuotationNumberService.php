<?php

namespace App\Services;

use App\Models\Proposal;
use App\Models\User;

/**
 * Generator nomor quotation baku (wawancara Adi Santosa):
 *   `[PT]/[NomorUrut]/[InisialSales]/[Tahun]`  — contoh `MGK/015/AS/2026`.
 *
 * Nomor urut berjalan per PT (global per PT per tahun), inisial sales hanya
 * label. Dibuat idempoten: cek ulang kolom unik `proposal_number` bila tabrakan.
 */
class QuotationNumberService
{
    public function generate(string $pt, ?User $creator = null, ?int $year = null): string
    {
        $pt = strtoupper(trim($pt)) ?: 'WANI';
        $year = $year ?? (int) now()->year;
        $initials = $this->initialsFor($creator);

        // Prefix pencocokan nomor yang sudah terbit untuk PT + tahun ini.
        $like = $pt.'/%/%/'.$year;

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $max = (int) Proposal::query()
                ->withTrashed()
                ->where('proposal_number', 'like', $like)
                ->get()
                ->map(fn (Proposal $p) => (int) (explode('/', (string) $p->proposal_number)[1] ?? 0))
                ->max();

            $sequence = ($max > 0 ? $max : 0) + 1 + $attempt;
            $number = $pt.'/'.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT).'/'.$initials.'/'.$year;

            if (! Proposal::withTrashed()->where('proposal_number', $number)->exists()) {
                return $number;
            }
        }

        return $pt.'/'.str_pad((string) random_int(1, 999), 3, '0', STR_PAD_LEFT).'/'.$initials.'/'.$year;
    }

    private function initialsFor(?User $creator): string
    {
        return $creator ? $creator->quotationInitials() : 'X';
    }
}

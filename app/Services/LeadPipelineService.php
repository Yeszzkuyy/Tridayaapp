<?php

namespace App\Services;

use App\Models\Lead;
use Illuminate\Validation\ValidationException;

/**
 * Aturan transisi status lead di pipeline Sales (wawancara Adi Santosa).
 *
 * - Naik ke Warm/Hot/Won wajib punya minimal satu catatan follow-up (DAR).
 * - Menandai Lost wajib memilih alasan dan mengisi catatan singkat.
 */
class LeadPipelineService
{
    /** Tahap yang mensyaratkan minimal satu DAR sebelum dimasuki. */
    public const STAGES_REQUIRING_DAR = ['warm', 'hot', 'won'];

    /**
     * @throws ValidationException bila syarat transisi belum terpenuhi.
     */
    public function assertCanTransition(
        Lead $lead,
        string $newStatus,
        ?string $lostReason = null,
        ?string $lostNote = null,
    ): void {
        if (in_array($newStatus, self::STAGES_REQUIRING_DAR, true) && ! $lead->followUps()->exists()) {
            throw ValidationException::withMessages([
                'status' => __('Record at least one follow-up before moving the lead to this stage.'),
            ]);
        }

        if ($newStatus === 'lost') {
            $reason = trim((string) ($lostReason ?? $lead->lost_reason ?? ''));
            $note = trim((string) ($lostNote ?? $lead->lost_note ?? ''));

            if ($reason === '' || $note === '') {
                throw ValidationException::withMessages([
                    'status' => __('Choose a loss reason and add a short note before marking the lead as lost.'),
                ]);
            }
        }

        // Won harus punya dasar transaksi: quotation yang sudah Accepted.
        // Perubahan ke Won dari quotation tetap lewat ProposalController.
        if ($newStatus === 'won' && $lead->status !== 'won'
            && ! $lead->proposals()->where('status', 'accepted')->exists()) {
            throw ValidationException::withMessages([
                'status' => __('A lead can only be WON from an accepted quotation.'),
            ]);
        }
    }
}

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

        // Identitas klien wajib lengkap sebelum lead masuk pipeline aktif.
        // Lead lama yang sudah Warm/Hot tidak diblokir saat mundur tahap.
        if (in_array($newStatus, self::STAGES_REQUIRING_DAR, true)
            && ! in_array($lead->status, self::STAGES_REQUIRING_DAR, true)) {
            $missing = $this->identityIssues($lead);

            if ($missing !== []) {
                throw ValidationException::withMessages([
                    'status' => __('Complete the client identity before moving the lead to this stage: :fields.', [
                        'fields' => implode(', ', $missing),
                    ]),
                ]);
            }
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

    /**
     * Daftar identitas klien yang belum lengkap. Kosong = siap masuk pipeline.
     *
     * @return array<int, string>
     */
    private function identityIssues(Lead $lead): array
    {
        $customer = $lead->customer;

        if (! $customer) {
            return [__('Company Name'), __('Address'), __('PIC'), __('PIC WhatsApp'), __('Email'), __('Category')];
        }

        $missing = [];

        if (! filled($customer->company) && ! filled($customer->name)) {
            $missing[] = __('Company Name');
        }
        if (! filled($customer->address)) {
            $missing[] = __('Address');
        }
        if (! filled($customer->picName())) {
            $missing[] = __('PIC');
        }
        if (! filled($customer->picWhatsapp())) {
            $missing[] = __('PIC WhatsApp');
        }
        if (! filled($customer->picEmail())) {
            $missing[] = __('Email');
        }
        if (! filled($lead->segment)) {
            $missing[] = __('Category');
        }

        return $missing;
    }
}

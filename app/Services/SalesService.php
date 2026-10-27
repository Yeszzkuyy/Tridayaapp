<?php

namespace App\Services;

use App\Models\FollowUp;
use App\Models\Customer;
use App\Models\LeadActivity;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class SalesService
{
    public function getFollowUps(array $filters = [])
    {
        $query = FollowUp::with(['customer', 'creator', 'lead'])
            ->nonMeetings();

        if (!empty($filters['search'])) {
            $query->whereHas('customer', function ($q) use ($filters) {
                $q->whereLike('name', $filters['search']);
            });
        }

        if (!empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        if (!empty($filters['lead_id'])) {
            $query->where('lead_id', $filters['lead_id']);
        }

        // overdue: 1 = jatuh tempo (< hari ini, kompatibel link lama),
        // today = hari ini, upcoming = setelah hari ini.
        // Selesai tidak ikut hitungan jatuh tempo / hari ini / mendatang.
        if (!empty($filters['overdue'])) {
            $query->whereNull('completed_at')
                ->whereNotNull('follow_up_date');
            if ($filters['overdue'] === 'today') {
                $query->whereDate('follow_up_date', today());
            } elseif ($filters['overdue'] === 'upcoming') {
                $query->whereDate('follow_up_date', '>', today());
            } else {
                $query->whereDate('follow_up_date', '<', today());
            }
        }

        $this->scopeToOwnLeads($query);

        // Customer terbaru selalu di atas, lalu tanggal follow-up terbaru.
        return $query->orderBy(
            Customer::select('created_at')->whereColumn('customers.id', 'follow_ups.customer_id'),
            'desc'
        )->latest('follow_up_date')->paginate(15);
    }

    public function createFollowUp(array $data): FollowUp
    {
        return DB::transaction(function () use ($data) {
            $data['created_by'] = auth()->id();
            $followUp = FollowUp::create($data);
            if (!empty($followUp->lead_id)) {
                LeadActivity::create([
                    'lead_id' => $followUp->lead_id,
                    'user_id' => auth()->id(),
                    'action' => 'followup_created',
                ]);
            }

            return $followUp;
        });
    }

    /**
     * Pastikan user yang login adalah pemilik record FollowUp (created_by).
     * Super Admin bypass via Gate::before.
     * Sales dan sales-<pt> hanya boleh aksi pada record miliknya sendiri.
     * Kesamaan PT saja TIDAK cukup — harus pembuat record.
     */
    private function assertFollowUpOwnership(FollowUp $followUp): void
    {
        $user = auth()->user();

        // Super Admin bypass.
        if ($user && $user->hasRole('super-admin')) {
            return;
        }

        // Non-pemilik (termasuk created_by null) = ditolak.
        abort_unless(
            $followUp->created_by !== null && (int) $followUp->created_by === (int) $user->id,
            403
        );
    }

    public function updateFollowUp(FollowUp $followUp, array $data): FollowUp
    {
        $this->assertFollowUpOwnership($followUp);
        $followUp->update($data);
        return $followUp;
    }

    public function deleteFollowUp(FollowUp $followUp): void
    {
        $this->assertFollowUpOwnership($followUp);
        $followUp->delete();
    }

    public function completeFollowUp(FollowUp $followUp): FollowUp
    {
        $this->assertFollowUpOwnership($followUp);
        $followUp->forceFill(['completed_at' => now()])->save();
        $this->logFollowUpActivity($followUp, 'followup_completed');

        return $followUp;
    }

    public function completeMeetingReport(FollowUp $followUp, array $data): FollowUp
    {
        $this->assertFollowUpOwnership($followUp);
        $photoPaths = [];
        if (!empty($data['meeting_photos'])) {
            foreach ($data['meeting_photos'] as $file) {
                if ($file instanceof \Illuminate\Http\UploadedFile) {
                    $photoPaths[] = $file->store('meeting-reports', 'public');
                }
            }
        }

        $followUp->forceFill([
            'completed_at' => now(),
            'meeting_photos' => $photoPaths,
            'meeting_notes' => $data['meeting_notes'] ?? null,
            'meeting_outcome' => $data['meeting_outcome'] ?? null,
            'meeting_next_action_date' => !empty($data['meeting_next_action_date']) ? \Carbon\Carbon::parse($data['meeting_next_action_date']) : null,
        ])->save();

        $this->logFollowUpActivity($followUp, 'meeting_report_submitted', [
            'outcome' => $data['meeting_outcome'] ?? null,
            'photos_count' => count($photoPaths),
            'next_action_date' => $data['meeting_next_action_date'] ?? null,
        ]);

        return $followUp;
    }

    

    public function reopenFollowUp(FollowUp $followUp): FollowUp
    {
        $this->assertFollowUpOwnership($followUp);
        $followUp->forceFill(['completed_at' => null])->save();

        return $followUp;
    }

    public function snoozeFollowUp(FollowUp $followUp, int $days): FollowUp
    {
        $this->assertFollowUpOwnership($followUp);
        $base = $followUp->follow_up_date && $followUp->follow_up_date->isFuture()
            ? $followUp->follow_up_date
            : today();

        $followUp->forceFill([
            'follow_up_date' => $base->copy()->addDays($days),
            'reminder_sent_at' => null, // Ingatkan lagi setelah ditunda.
        ])->save();
        $this->logFollowUpActivity($followUp, 'followup_snoozed', ['days' => $days]);

        return $followUp;
    }

    public function rescheduleFollowUp(FollowUp $followUp, Carbon $date): FollowUp
    {
        $this->assertFollowUpOwnership($followUp);
        $followUp->forceFill([
            'follow_up_date' => $date->copy()->startOfDay(),
            'reminder_sent_at' => null, // Ingatkan lagi setelah dijadwal ulang.
        ])->save();
        $this->logFollowUpActivity($followUp, 'followup_snoozed', ['date' => $date->toDateString()]);

        return $followUp;
    }

    private function logFollowUpActivity(FollowUp $followUp, string $action, ?array $changes = null): void
    {
        if (empty($followUp->lead_id)) {
            return;
        }

        LeadActivity::create([
            'lead_id' => $followUp->lead_id,
            'user_id' => auth()->id(),
            'action' => $action,
            'changes' => $changes,
        ]);
    }

    public function getCustomerFollowUps(Customer $customer)
    {
        return $customer->followUps()->with(['creator'])->latest('follow_up_date')->get();
    }

    /**
     * Sales biasa hanya melihat follow-up dari lead miliknya
     * (atau yang ia buat sendiri untuk data lama tanpa lead_id).
     */
    private function scopeToOwnLeads($query): void
    {
        $user = auth()->user();
        if (!$user || !$user->hasRole('sales')) {
            return;
        }
        if ($user->can('manage-marketing') || $user->can('manage-sales-leads')) {
            return;
        }

        $userId = $user->id;
        $query->where(function ($q) use ($userId) {
            $q->whereHas('lead', fn ($l) => $l->where('assigned_to', $userId))
                ->orWhere(fn ($w) => $w->whereNull('lead_id')->where('created_by', $userId));
        });
    }
}

<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Support\PtAccess;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SalesService
{
    /**
     * Antrean Follow Up: hanya pekerjaan yang masih butuh tindakan, dengan
     * tab Due Today / Upcoming / Overdue / Completed. Sales hanya melihat
     * lead miliknya; management melihat semua.
     */
    public function getFollowUps(array $filters = [])
    {
        $query = $this->followUpBaseQuery($filters);

        $tab = $filters['tab'] ?? null;
        $legacy = $filters['overdue'] ?? null;

        if (! $tab && $legacy) {
            $tab = match ((string) $legacy) {
                'today' => 'due_today',
                'upcoming' => 'upcoming',
                'completed' => 'completed',
                default => 'overdue',
            };
        }

        if ($tab === 'completed') {
            $query->completed()->orderByDesc('completed_at');
        } else {
            $query->active();

            if ($tab === 'due_today') {
                $query->whereDate('follow_up_date', today());
            } elseif ($tab === 'upcoming') {
                $query->whereDate('follow_up_date', '>', today());
            } elseif ($tab === 'overdue') {
                $query->whereNotNull('follow_up_date')
                    ->whereDate('follow_up_date', '<', today());
            }

            $query->orderBy('follow_up_date')->orderBy('activity_time');
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $this->scopeToOwnLeads($query);

        return $query->paginate(15)->withQueryString();
    }

    /**
     * Jumlah per tab untuk badge. Filter pencarian/stage tetap dihitung,
     * tab dan status tidak (supaya badge selalu menunjukkan isi antrean).
     */
    public function followUpCounts(array $filters = []): array
    {
        $base = $this->followUpBaseQuery($filters, withTabFilters: false);
        $this->scopeToOwnLeads($base);

        return [
            'all' => (clone $base)->active()->count(),
            'due_today' => (clone $base)->active()->whereDate('follow_up_date', today())->count(),
            'upcoming' => (clone $base)->active()->whereDate('follow_up_date', '>', today())->count(),
            'overdue' => (clone $base)->active()->whereNotNull('follow_up_date')->whereDate('follow_up_date', '<', today())->count(),
            'completed' => (clone $base)->completed()->count(),
        ];
    }

    private function followUpBaseQuery(array $filters, bool $withTabFilters = true)
    {
        $query = FollowUp::with(['customer.contacts', 'creator', 'lead.latestNoteFollowUp', 'lead.activeFollowUp'])
            ->nonMeetings();

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($q) use ($like) {
                $q->whereHas('customer', fn ($c) => $c->whereLike('name', $like))
                    ->orWhereHas('lead', fn ($l) => $l->whereLike('project_name', $like));
            });
        }

        $customer = trim((string) ($filters['customer'] ?? ''));
        if ($customer !== '') {
            $query->whereHas('customer', fn ($c) => $c->whereLike('name', '%'.$customer.'%'));
        }

        if (! empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        if (! empty($filters['lead_id'])) {
            $query->where('lead_id', $filters['lead_id']);
        }

        if (! empty($filters['stage'])) {
            $query->whereHas('lead', fn ($l) => $l->where('status', $filters['stage']));
        }

        if (! empty($filters['activity_type'])) {
            $query->where('type', $filters['activity_type']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('follow_up_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('follow_up_date', '<=', $filters['date_to']);
        }

        return $query;
    }

    public function createFollowUp(array $data): FollowUp
    {
        return DB::transaction(function () use ($data) {
            $data['created_by'] = auth()->id();
            $data['source'] = $data['source'] ?? 'follow_up';

            // Tombol ganda / submit berulang dalam 10 detik tidak boleh
            // membuat catatan aktivitas baru.
            if (! empty($data['lead_id'])) {
                $recent = FollowUp::where('lead_id', $data['lead_id'])
                    ->where('created_by', $data['created_by'])
                    ->where('type', $data['type'] ?? null)
                    ->where('description', $data['description'] ?? '')
                    ->where('created_at', '>=', now()->subSeconds(10))
                    ->first();

                if ($recent) {
                    return $recent;
                }
            }

            if (empty($data['status'])) {
                $date = $data['follow_up_date'] ?? null;
                $data['status'] = $date && Carbon::parse($date)->lt(today()) ? 'completed' : 'pending';
            }

            if ($data['status'] === 'completed') {
                $data['completed_at'] = $data['completed_at'] ?? now();
            } else {
                $data['status'] = 'pending';
                $data['completed_at'] = null;
            }

            $followUp = FollowUp::create($data);

            if (! empty($followUp->lead_id)) {
                LeadActivity::create([
                    'lead_id' => $followUp->lead_id,
                    'user_id' => auth()->id(),
                    'action' => 'followup_created',
                    'changes' => ['follow_up_id' => $followUp->id, 'type' => $followUp->type],
                ]);
            }

            $nextDate = $data['next_follow_up_date'] ?? null;
            if ($followUp->isCompleted() && $nextDate) {
                $this->scheduleNextFollowUp($followUp, Carbon::parse($nextDate), $data['next_follow_up_time'] ?? null);
            }

            return $followUp->refresh();
        });
    }

    /**
     * Catatan singkat dari tabel My Leads. Append-only: catatan lama tetap
     * tersimpan sebagai riwayat, yang baru menjadi Latest Note.
     */
    public function appendNote(Lead $lead, string $note): FollowUp
    {
        return $this->createFollowUp([
            'customer_id' => $lead->customer_id,
            'lead_id' => $lead->id,
            'type' => 'note',
            'description' => $note,
            'follow_up_date' => today()->toDateString(),
            'activity_time' => now()->format('H:i'),
            'status' => 'completed',
            'source' => 'manual',
        ]);
    }

    /**
     * Satu follow-up aktif per (lead, owner). Bila antrean sudah ada, tanggal
     * dijadwal ulang, bukan membuat baris baru.
     */
    public function setNextFollowUp(Lead $lead, string $date, ?string $time = null, ?int $ownerId = null): FollowUp
    {
        $ownerId = $ownerId ?? auth()->id();

        return DB::transaction(function () use ($lead, $date, $time, $ownerId) {
            $existing = FollowUp::where('lead_id', $lead->id)
                ->where('created_by', $ownerId)
                ->active()
                ->nonMeetings()
                ->lockForUpdate()
                ->orderBy('follow_up_date')
                ->first();

            if ($existing) {
                $existing->forceFill([
                    'follow_up_date' => $date,
                    'activity_time' => $time,
                    'status' => $existing->status === 'process' ? 'process' : 'pending',
                    'reminder_sent_at' => null,
                ])->save();

                return $existing->refresh();
            }

            return FollowUp::create([
                'customer_id' => $lead->customer_id,
                'lead_id' => $lead->id,
                'description' => '',
                'type' => null,
                'follow_up_date' => $date,
                'activity_time' => $time,
                'status' => 'pending',
                'source' => 'system',
                'created_by' => $ownerId,
            ]);
        });
    }

    private function scheduleNextFollowUp(FollowUp $completed, Carbon $date, ?string $time = null, ?int $ownerId = null): FollowUp
    {
        $ownerId = $ownerId ?? $completed->created_by ?? auth()->id();

        $lead = $completed->lead;
        if ($lead) {
            return $this->setNextFollowUp($lead, $date->toDateString(), $time, $ownerId);
        }

        // Follow-up tanpa lead (catatan customer umum) tetap dibuat sebagai
        // baris baru supaya histori tidak tertimpa.
        return FollowUp::create([
            'customer_id' => $completed->customer_id,
            'description' => '',
            'follow_up_date' => $date,
            'activity_time' => $time,
            'status' => 'pending',
            'source' => 'system',
            'created_by' => $ownerId,
        ]);
    }

    public function completeFollowUp(FollowUp $followUp, array $data = []): FollowUp
    {
        $this->assertFollowUpOwnership($followUp);

        return DB::transaction(function () use ($followUp, $data) {
            $locked = FollowUp::whereKey($followUp->id)->lockForUpdate()->firstOrFail();

            if ($locked->isCompleted()) {
                abort(422, __('This follow up is already completed.'));
            }

            $outcome = trim((string) ($data['outcome'] ?? ''));
            $nextDate = $data['next_follow_up_date'] ?? null;

            $locked->forceFill([
                'status' => 'completed',
                'completed_at' => now(),
                'outcome' => $outcome !== '' ? $outcome : $locked->outcome,
                'next_follow_up_date' => $nextDate ?: $locked->next_follow_up_date,
            ])->save();

            $this->logFollowUpActivity($locked, 'followup_completed', [
                'follow_up_id' => $locked->id,
                'outcome' => $outcome !== '' ? $outcome : null,
            ]);

            if ($nextDate) {
                $this->scheduleNextFollowUp(
                    $locked,
                    Carbon::parse($nextDate),
                    $data['next_follow_up_time'] ?? null,
                    $locked->created_by
                );
            }

            return $locked;
        });
    }

    /**
     * Catat bahwa Sales MEMBUKA kanal kontak (WA/Email/Call). Bukan bukti
     * pesan terkirim. Dedupe 60 detik supaya double-click tidak menggandakan.
     */
    public function logContactInitiated(Lead $lead, string $channel, ?FollowUp $followUp = null): array
    {
        return DB::transaction(function () use ($lead, $channel, $followUp) {
            $recent = LeadActivity::where('lead_id', $lead->id)
                ->where('user_id', auth()->id())
                ->where('action', 'contact_initiated')
                ->where('created_at', '>=', now()->subSeconds(60))
                ->latest()
                ->get()
                ->first(fn (LeadActivity $activity) => ($activity->changes['channel'] ?? null) === $channel);

            if ($recent) {
                return ['activity' => $recent, 'duplicate' => true];
            }

            $activity = LeadActivity::create([
                'lead_id' => $lead->id,
                'user_id' => auth()->id(),
                'action' => 'contact_initiated',
                'changes' => ['channel' => $channel, 'at' => now()->toIso8601String()],
            ]);

            if ($followUp && ! $followUp->isCompleted() && $followUp->status !== 'process') {
                $followUp->forceFill(['status' => 'process'])->save();
            }

            return ['activity' => $activity, 'duplicate' => false];
        });
    }

    /**
     * Pastikan user yang login adalah pemilik record FollowUp (created_by).
     * Super Admin bypass via Gate::before.
     * Sales dan sales-<pt> hanya boleh aksi pada record miliknya sendiri.
     */
    private function assertFollowUpOwnership(FollowUp $followUp): void
    {
        $user = auth()->user();

        if ($user && $user->hasRole('super-admin')) {
            return;
        }

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

    public function completeMeetingReport(FollowUp $followUp): FollowUp
    {
        $this->assertFollowUpOwnership($followUp);

        $followUp->forceFill([
            'completed_at' => $followUp->completed_at ?? now(),
            'status' => 'completed',
            'daily_report_submitted_at' => now(),
            'meeting_next_action_date' => $followUp->meeting_next_action_date ?? $followUp->next_follow_up_date,
        ])->save();

        $this->logFollowUpActivity($followUp, 'daily_report_submitted', [
            'outcome' => $followUp->meeting_outcome,
            'photos_count' => count($followUp->meeting_photos ?? []),
        ]);

        return $followUp;
    }

    public function reopenFollowUp(FollowUp $followUp): FollowUp
    {
        $this->assertFollowUpOwnership($followUp);

        if ($followUp->lead_id) {
            $hasActive = FollowUp::where('lead_id', $followUp->lead_id)
                ->where('created_by', $followUp->created_by)
                ->whereKeyNot($followUp->id)
                ->active()
                ->nonMeetings()
                ->exists();

            abort_if($hasActive, 422, __('There is already an active follow up for this lead.'));
        }

        $followUp->forceFill([
            'completed_at' => null,
            'status' => 'pending',
            'daily_report_submitted_at' => null,
        ])->save();

        return $followUp;
    }

    public function snoozeFollowUp(FollowUp $followUp, int $days): FollowUp
    {
        $this->assertFollowUpOwnership($followUp);
        abort_if($followUp->isCompleted(), 422, __('Completed follow ups cannot be postponed.'));

        $base = $followUp->follow_up_date && $followUp->follow_up_date->isFuture()
            ? $followUp->follow_up_date
            : today();

        $followUp->forceFill([
            'follow_up_date' => $base->copy()->addDays($days),
            'status' => 'pending',
            'reminder_sent_at' => null,
        ])->save();
        $this->logFollowUpActivity($followUp, 'followup_snoozed', ['days' => $days]);

        return $followUp;
    }

    public function rescheduleFollowUp(FollowUp $followUp, Carbon $date): FollowUp
    {
        $this->assertFollowUpOwnership($followUp);
        abort_if($followUp->isCompleted(), 422, __('Completed follow ups cannot be rescheduled.'));

        $followUp->forceFill([
            'follow_up_date' => $date->copy()->startOfDay(),
            'status' => 'pending',
            'reminder_sent_at' => null,
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
     * Sales biasa (termasuk sales per PT) hanya melihat follow-up dari lead
     * miliknya atau catatan yang ia buat sendiri. Management dan role baca
     * lintas divisi tetap melihat semua.
     */
    private function scopeToOwnLeads($query): void
    {
        $user = auth()->user();

        if (! $user || ! PtAccess::isSalesLike($user)) {
            return;
        }

        if ($user->can('manage-marketing') || $user->can('manage-sales-leads')) {
            return;
        }

        $userId = $user->id;
        $query->where(function ($q) use ($userId) {
            $q->whereHas('lead', fn ($l) => $l->where('assigned_to', $userId))
                ->orWhere(fn ($w) => $w->whereNull('lead_id')->where('created_by', $userId))
                ->orWhere('created_by', $userId);
        });
    }
}

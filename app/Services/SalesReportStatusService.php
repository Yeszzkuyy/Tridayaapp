<?php

namespace App\Services;

use App\Models\FollowUp;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Status laporan harian Sales dari data Meeting.
 *
 * Satu meeting dianggap "sudah report" bila `daily_report_submitted_at`
 * terisi (di-set saat Sales mengirim report meeting).
 */
class SalesReportStatusService
{
    /**
     * Ringkasan report per sales pada satu tanggal.
     *
     * @return Collection<int, array{user: User, total: int, sent: int, pending: int, status: string, last_report_at: Carbon|null}>
     */
    public function daily(Carbon|string|null $date = null, ?string $ptGroup = null): Collection
    {
        $day = $date instanceof Carbon
            ? $date->toDateString()
            : ($date ? Carbon::parse($date)->toDateString() : today()->toDateString());

        $salesRoles = \Spatie\Permission\Models\Role::query()
            ->where('name', 'like', 'sales%')
            ->pluck('name')
            ->all();

        $salesUsers = User::role($salesRoles)->orderBy('name')->get(['id', 'name']);

        $meetings = FollowUp::query()
            ->meetings()
            ->with('lead:id,assigned_to')
            ->whereDate('follow_up_date', $day)
            ->when($ptGroup, fn ($query) => $query->whereHas(
                'lead',
                fn ($lead) => $lead->where('pt_group', $ptGroup)
            ))
            ->get(['id', 'created_by', 'lead_id', 'completed_at', 'daily_report_submitted_at']);

        return $salesUsers->map(function (User $user) use ($meetings) {
            $own = $meetings->filter(fn (FollowUp $meeting) => (int) $meeting->created_by === (int) $user->id
                || (int) ($meeting->lead?->assigned_to ?? 0) === (int) $user->id);

            $total = $own->count();
            $sent = $own->filter(fn (FollowUp $meeting) => $meeting->daily_report_submitted_at !== null)->count();
            $pending = max(0, $total - $sent);

            $status = match (true) {
                $total === 0 => 'done',
                $sent === 0 => 'missing',
                $sent < $total => 'partial',
                default => 'done',
            };

            return [
                'user' => $user,
                'total' => $total,
                'sent' => $sent,
                'pending' => $pending,
                'status' => $status,
                'last_report_at' => $own->max('daily_report_submitted_at'),
            ];
        });
    }

    /**
     * Ringkasan satu user (dipakai dashboard Sales).
     *
     * @return array{user: User, total: int, sent: int, pending: int, status: string, last_report_at: Carbon|null}
     */
    public function summaryFor(User $user, Carbon|string|null $date = null): array
    {
        return $this->daily($date)->firstWhere(
            fn (array $row) => (int) $row['user']->id === (int) $user->id
        ) ?? [
            'user' => $user,
            'total' => 0,
            'sent' => 0,
            'pending' => 0,
            'status' => 'done',
            'last_report_at' => null,
        ];
    }
}

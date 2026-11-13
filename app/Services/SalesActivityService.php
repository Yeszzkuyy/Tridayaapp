<?php

namespace App\Services;

use App\Models\FollowUp;
use App\Models\LeadActivity;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Sumber data DAR: aktivitas follow-up (sudah selesai) digabung dengan
 * kejadian sistem yang relevan dari lead_activities. Tidak ada tabel baru
 * dan tidak ada aktivitas yang ditulis ulang.
 */
class SalesActivityService
{
    /** Kejadian sistem yang layak tampil sebagai aktivitas Sales. */
    public const SYSTEM_ACTIONS = [
        'status_changed',
        'proposal_sent',
        'proposal_accepted',
        'proposal_rejected',
        'proposal_revised',
    ];

    public function canViewAll(User $user): bool
    {
        return $user->hasRole('super-admin')
            || $user->can('manage-sales-leads')
            || $user->can('view-admin');
    }

    public function paginate(array $filters, User $user, int $perPage = 25): LengthAwarePaginator
    {
        $type = $filters['type'] ?? null;
        $onlySystem = $type === 'pipeline';
        $includeSystem = ! $type || $type === 'pipeline' || $type === 'proposal';

        $followUps = $this->followUpBranch($filters, $user);
        $activities = $this->activityBranch($filters, $user);

        $total = 0;
        if (! $onlySystem) {
            $total += (clone $followUps)->count();
        }
        if ($includeSystem) {
            $total += (clone $activities)->count();
        }

        $page = LengthAwarePaginator::resolveCurrentPage();
        $rows = collect();

        if ($total > 0) {
            if ($onlySystem) {
                $rows = $activities->orderByDesc('sort_at')->forPage($page, $perPage)->get();
            } elseif (! $includeSystem) {
                $rows = $followUps->orderByDesc('sort_at')->forPage($page, $perPage)->get();
            } else {
                $rows = $followUps->unionAll($activities)
                    ->orderByDesc('sort_at')
                    ->forPage($page, $perPage)
                    ->get();
            }
        }

        return $this->hydrate($rows, $total, $perPage, $page);
    }

    private function followUpBranch(array $filters, User $user)
    {
        $query = DB::table('follow_ups as f')
            ->join('customers as c', 'c.id', '=', 'f.customer_id')
            ->leftJoin('leads as l', 'l.id', '=', 'f.lead_id')
            ->whereNull('f.deleted_at')
            ->where(function ($q) {
                $q->where('f.status', 'completed')->orWhereNotNull('f.completed_at');
            });

        $this->applyCommonFilters($query, $filters, $user, 'f', 'c', 'l');

        if (($filters['type'] ?? null) && $filters['type'] !== 'pipeline') {
            $query->where('f.type', $filters['type']);
        }

        if (! empty($filters['date'])) {
            $query->whereDate('f.follow_up_date', $filters['date']);
        }
        if (! empty($filters['date_from'])) {
            $query->whereDate('f.follow_up_date', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('f.follow_up_date', '<=', $filters['date_to']);
        }

        return $query->select([
            'f.id as ref_id',
            DB::raw("'follow_up' as event_source"),
            DB::raw("SUBSTR(CAST(f.follow_up_date AS TEXT), 1, 10) || ' ' || COALESCE(CAST(f.activity_time AS TEXT), '00:00:00') as sort_at"),
            'f.created_by as user_id',
            'f.customer_id as customer_id',
            'c.name as customer_name',
            'l.project_name as project_name',
            'f.lead_id as lead_id',
            'f.type as activity_type',
            'f.description as description',
            'f.outcome as outcome',
            'f.status as status',
            'f.source as source',
        ]);
    }

    private function activityBranch(array $filters, User $user)
    {
        $query = DB::table('lead_activities as a')
            ->join('leads as l', 'l.id', '=', 'a.lead_id')
            ->join('customers as c', 'c.id', '=', 'l.customer_id')
            ->whereIn('a.action', self::SYSTEM_ACTIONS);

        $this->applyCommonFilters($query, $filters, $user, 'a', 'c', 'l', activityAlias: true);

        if (($filters['type'] ?? null) === 'pipeline') {
            $query->where('a.action', 'status_changed');
        } elseif (($filters['type'] ?? null) === 'proposal') {
            $query->whereIn('a.action', ['proposal_sent', 'proposal_accepted', 'proposal_rejected', 'proposal_revised']);
        }

        if (! empty($filters['date'])) {
            $query->whereDate('a.created_at', $filters['date']);
        }
        if (! empty($filters['date_from'])) {
            $query->whereDate('a.created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('a.created_at', '<=', $filters['date_to']);
        }

        return $query->select([
            'a.id as ref_id',
            DB::raw("'lead_activity' as event_source"),
            DB::raw('CAST(a.created_at AS TEXT) as sort_at'),
            'a.user_id as user_id',
            'c.id as customer_id',
            'c.name as customer_name',
            'l.project_name as project_name',
            'a.lead_id as lead_id',
            DB::raw("CASE WHEN a.action = 'status_changed' THEN 'pipeline' ELSE 'proposal' END as activity_type"),
            DB::raw('NULL as description'),
            DB::raw('NULL as outcome'),
            DB::raw("'completed' as status"),
            DB::raw("'system' as source"),
        ]);
    }

    private function applyCommonFilters($query, array $filters, User $user, string $ownerCol, string $customerAlias, string $leadAlias, bool $activityAlias = false): void
    {
        if (! $this->canViewAll($user)) {
            $userId = $user->id;
            $query->where(function ($q) use ($userId, $ownerCol, $leadAlias, $activityAlias) {
                if ($activityAlias) {
                    $q->where($ownerCol.'.user_id', $userId);
                } else {
                    $q->where($ownerCol.'.created_by', $userId)
                        ->orWhere($leadAlias.'.assigned_to', $userId);
                }
            });
        }

        if (! empty($filters['user_id']) && $this->canViewAll($user)) {
            $column = $activityAlias ? $ownerCol.'.user_id' : $ownerCol.'.created_by';
            $query->where($column, $filters['user_id']);
        }

        $customer = trim((string) ($filters['customer'] ?? ''));
        if ($customer !== '') {
            $query->whereLike($customerAlias.'.name', '%'.$customer.'%');
        }

        if (! empty($filters['lead_id'])) {
            $query->where($ownerCol.'.lead_id', $filters['lead_id']);
        }

        if (! empty($filters['stage'])) {
            $query->where($leadAlias.'.status', $filters['stage']);
        }
    }

    private function hydrate($rows, int $total, int $perPage, int $page): LengthAwarePaginator
    {
        $userIds = $rows->pluck('user_id')->filter()->unique()->values();
        $users = $userIds->isEmpty() ? collect() : User::whereIn('id', $userIds)->get()->keyBy('id');

        $activityIds = $rows->where('event_source', 'lead_activity')->pluck('ref_id');
        $systemActivities = $activityIds->isEmpty()
            ? collect()
            : LeadActivity::whereIn('id', $activityIds)->get()->keyBy('id');

        $items = $rows->map(function ($row) use ($users, $systemActivities) {
            $eventAt = Carbon::parse($row->sort_at);
            $system = $row->event_source === 'lead_activity'
                ? $systemActivities->get($row->ref_id)
                : null;

            $typeLabel = $row->activity_type === 'pipeline'
                ? __('Pipeline Stage')
                : FollowUp::typeLabel($row->activity_type);

            $description = $row->description;
            if ($system) {
                $description = $system->summary() ?? $system->actionLabel();
            }

            return (object) [
                'date' => $eventAt->copy()->timezone('Asia/Jakarta')->format('d M Y'),
                'time' => $eventAt->copy()->timezone('Asia/Jakarta')->format('H:i'),
                'user' => $users->get($row->user_id),
                'customer' => $row->customer_name,
                'project' => $row->project_name,
                'activity_type' => $typeLabel,
                'description' => $description,
                'outcome' => $row->outcome,
                'lead_id' => $row->lead_id,
                'source' => $row->event_source === 'lead_activity' ? __('System') : FollowUp::sourceLabel($row->source),
                'status' => FollowUp::statusLabel($row->status),
                'follow_up_id' => $row->event_source === 'follow_up' ? $row->ref_id : null,
            ];
        });

        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath(), 'query' => request()->query()]
        );
    }
}

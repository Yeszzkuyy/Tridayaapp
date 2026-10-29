<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\FollowUp;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\SalesTarget;
use App\Models\User;
use App\Notifications\LeadAssignedNotification;
use App\Notifications\NewLeadNotification;
use App\Services\SalesKpiService;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class ManageSalesController extends Controller
{
    public function index(Request $request)
    {
        $query = Lead::with(['customer.contacts', 'assignee', 'partner'])
            ->withCount(['followUps'])
            ->withMax('followUps as last_follow_up_at', 'follow_up_date')
            ->when($request->filled('search'), fn ($q) => $q->whereHas('customer',
                fn ($c) => $c->whereLike('name', $request->search)))
            ->when($request->filled('assignment'), fn ($q) => $request->assignment === 'assigned'
                ? $q->whereNotNull('assigned_to')
                : $q->whereNull('assigned_to'))
            ->when($request->filled('touched'), fn ($q) => $request->touched === 'yes'
                ? $q->whereHas('followUps')
                : $q->whereDoesntHave('followUps'));

        $leads = $query->latest()->paginate(15)->withQueryString();

        $salesUsers = User::role('sales')->orderBy('name')->get(['id', 'name']);

        // Agregat global (tidak ikut filter tabel).
        $stats = [
            'total' => Lead::count(),
            'unassigned' => Lead::whereNull('assigned_to')->count(),
            'won_month' => Lead::where('status', 'won')
                ->whereMonth('updated_at', now()->month)
                ->whereYear('updated_at', now()->year)->count(),
            'lost_month' => Lead::where('status', 'lost')
                ->whereMonth('updated_at', now()->month)
                ->whereYear('updated_at', now()->year)->count(),
            'overdue' => FollowUp::whereNotNull('follow_up_date')
                ->whereDate('follow_up_date', '<', today())->count(),
        ];
        $bySales = Lead::selectRaw('assigned_to, count(*) as total')
            ->whereNotNull('assigned_to')
            ->groupBy('assigned_to')
            ->orderByDesc('total')
            ->limit(5)->with('assignee:id,name')->get();

        return view('manage-sales.index', compact('leads', 'salesUsers', 'stats', 'bySales'));
    }

    public function edit(Lead $lead)
    {
        $lead->load(['customer.contacts', 'assignee']);

        $salesUsers = User::role('sales')->orderBy('name')->get(['id', 'name']);

        return view('manage-sales.edit', compact('lead', 'salesUsers'));
    }

    public function update(Request $request, Lead $lead)
    {
        $validated = $request->validate([
            'kebutuhan' => 'nullable|string|max:2000',
            'solusi' => 'nullable|string|max:2000',
            'progress_notes' => 'nullable|string|max:2000',
            'notes' => 'nullable|string|max:5000',
            'pt_group' => 'nullable|in:NTI,MGK,TPS,WANI',
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        if (!empty($validated['assigned_to'])) {
            $salesUser = User::find($validated['assigned_to']);
            if (!$salesUser?->hasRole('sales')) {
                abort(422, __('Assignment target must be a user with the Sales role.'));
            }
        }

        $lead->fill($validated);

        if ($request->has('assigned_to')) {
            $this->trackAssignment($lead, $validated['assigned_to']);
        }

        $changes = [];
        foreach ($validated as $field => $value) {
            if ($lead->isDirty($field)) {
                $changes[$field] = ['old' => $lead->getOriginal($field), 'new' => $value];
            }
        }

        $previousAssignee = $lead->getOriginal('assigned_to');
        $lead->save();

        $this->notifyAssignee($lead, $previousAssignee);

        if ($changes) {
            $this->logActivity($lead, 'updated', $changes);
        }

        // Perubahan assignee via form edit dicatat eksplisit sebagai reassign.
        if (array_key_exists('assigned_to', $changes)
            && !empty($changes['assigned_to']['old'])
            && (int) $changes['assigned_to']['old'] !== (int) $changes['assigned_to']['new']) {
            $this->logActivity($lead, 'reassigned', [
                'assigned_to' => $changes['assigned_to'],
            ]);
        }

        if (!empty($validated['assigned_to'])) {
            $this->clearLeadNotifications($lead);
        }

        return redirect()
            ->route('manage-sales.index')
            ->with('success', __('Lead berhasil diperbarui'))
            ->with('success_card', true);
    }

    public function assign(Request $request, Lead $lead)
    {
        $validated = $request->validate([
            'assigned_to' => 'required|exists:users,id',
        ]);

        $salesUser = User::find($validated['assigned_to']);
        if (!$salesUser?->hasRole('sales')) {
            abort(422, 'Target assignment harus user dengan role Sales.');
        }

        $previousAssignee = $lead->getOriginal('assigned_to');
        $lead->assigned_to = $salesUser->id;
        $this->trackAssignment($lead, $salesUser->id);
        $lead->save();

        $this->notifyAssignee($lead, $previousAssignee);

        // Assign pertama vs reassign dibedakan; old diambil sebelum save.
        $this->logActivity($lead, $previousAssignee ? 'reassigned' : 'assigned', [
            'assigned_to' => ['old' => $previousAssignee, 'new' => $salesUser->id],
        ]);

        $this->clearLeadNotifications($lead);

        return redirect()
            ->route('manage-sales.index')
            ->with('success', __('Lead assigned to') . ' ' . $salesUser->name)
            ->with('success_card', true);
    }

    public function dashboard(SalesKpiService $salesKpiService)
    {
        $ownLead = fn ($q) => $q->where('assigned_to', auth()->id());
        // Follow Up tidak menghitung Meeting (menu Meeting terpisah).
        $ownActivity = function ($q) use ($ownLead) {
            $q->where(fn ($t) => $t->whereNull('type')->orWhere('type', '!=', 'meeting'))
                ->where(function ($w) use ($ownLead) {
                    $w->whereHas('lead', $ownLead)
                        ->orWhere(fn ($x) => $x->whereNull('lead_id')->where('created_by', auth()->id()));
                });
        };
        $mine = fn () => Lead::where('assigned_to', auth()->id());
        $kpi = [
            'active' => $mine()->whereNotIn('status', ['won', 'lost'])->count(),
            'won_month' => $mine()->where('status', 'won')
                ->whereMonth('updated_at', now()->month)
                ->whereYear('updated_at', now()->year)->count(),
            'overdue' => FollowUp::where($ownActivity)
                ->whereNull('completed_at')
                ->whereNotNull('follow_up_date')
                ->whereDate('follow_up_date', '<', today())->count(),
            'followups_today' => FollowUp::where($ownActivity)
                ->whereNull('completed_at')
                ->whereDate('follow_up_date', today())->count(),
            'followups_upcoming' => FollowUp::where($ownActivity)
                ->whereNull('completed_at')
                ->whereNotNull('follow_up_date')
                ->whereDate('follow_up_date', '>', today())->count(),
        ];

        $meetingBase = \App\Models\FollowUp::query()->meetings()->where(function ($w) {
            $w->where('created_by', auth()->id())
                ->orWhereHas('lead', fn ($l) => $l->where('assigned_to', auth()->id()));
        });
        $meetingsToday = (clone $meetingBase)->whereDate('follow_up_date', today())->count();
        $meetingsPending = (clone $meetingBase)->whereNull('completed_at')
            ->whereDate('follow_up_date', '<=', today())->count();

        // KPI Personal & Leaderboard Tim Sales
        $myKpi = $salesKpiService->getUserKpi(auth()->user(), now()->month, now()->year);
        $salesLeaderboard = $salesKpiService->getLeaderboard(now()->month, now()->year);

        $dueFollowUps = FollowUp::with('customer')
            ->where($ownActivity)
            ->whereNull('completed_at')
            ->whereNotNull('follow_up_date')
            ->whereDate('follow_up_date', '<=', today()->addWeek())
            ->orderBy('follow_up_date')
            ->limit(5)->get();
        $myTasks = \App\Models\LeadTask::with(['lead.customer', 'assignee'])
            ->whereHas('lead', $ownLead)
            ->whereNotIn('status', ['done'])
            ->orderBy('due_date')
            ->limit(5)->get();

        // Donat status lead milik sendiri (palet = warna badge status).
        $statusCounts = $mine()->selectRaw('status, count(*) as total')
            ->groupBy('status')->pluck('total', 'status');
        $statusPalette = [
            'cool' => '#3b82f6',
            'warm' => '#eab308',
            'hot' => '#f97316',
            'won' => '#22c55e',
            'lost' => '#ef4444',
        ];
        $donutSales = collect(LeadController::STATUSES)->map(fn ($status) => [
            'label' => ucfirst($status),
            'value' => (int) ($statusCounts[$status] ?? 0),
            'key' => $status,
            'color' => $statusPalette[$status] ?? '#64748b',
        ])->values();
        $funnelTotal = $donutSales->sum('value');

        // Rentang minggu berjalan untuk drill-down kartu follow-up.
        $weekStart = now()->startOfWeek()->toDateString();
        $weekEnd = now()->endOfWeek()->toDateString();

        // Income bulan berjalan dari invoice admin, dirinci per PT/company.
        // Sumber sementara (opsi A); diganti rekap admin bila modulnya lengkap.
        $incomeRows = Invoice::selectRaw('customers.pt_group as pt_group, SUM(invoices.amount) as total')
            ->join('customers', 'customers.id', '=', 'invoices.customer_id')
            ->whereMonth('invoices.issue_date', now()->month)
            ->whereYear('invoices.issue_date', now()->year)
            ->whereIn('customers.pt_group', Lead::PT_GROUPS)
            ->groupBy('customers.pt_group')
            ->pluck('total', 'pt_group');
        $incomeMonth = collect(Lead::PT_GROUPS)
            ->mapWithKeys(fn ($pt) => [$pt => (float) ($incomeRows[$pt] ?? 0)]);
        $incomeTotal = $incomeMonth->sum();

        // Ringkasan agenda sales milik sendiri (jadwal saya: PIC/creator/lead saya).
        $ownSchedules = fn ($q) => $q->where(function ($w) {
            $w->where('sales_schedules.assigned_to', auth()->id())
                ->orWhere('sales_schedules.created_by', auth()->id())
                ->orWhereHas('lead', fn ($l) => $l->where('assigned_to', auth()->id()));
        });
        $scheduleSummary = [
            'today' => \App\Models\SalesSchedule::where($ownSchedules)
                ->whereDate('start_at', today())->whereNotIn('status', ['completed', 'cancelled'])->count(),
            'upcoming' => \App\Models\SalesSchedule::where($ownSchedules)
                ->where('start_at', '>=', now()->startOfDay())->whereNotIn('status', ['completed', 'cancelled'])->count(),
            'completed' => \App\Models\SalesSchedule::where($ownSchedules)->where('status', 'completed')->count(),
            'cancelled' => \App\Models\SalesSchedule::where($ownSchedules)->where('status', 'cancelled')->count(),
        ];
        $scheduleTypes = \App\Models\SalesSchedule::where($ownSchedules)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->selectRaw('type, count(*) as total')->groupBy('type')->pluck('total', 'type');

        return view('sales.dashboard', compact('kpi', 'myKpi', 'salesLeaderboard', 'dueFollowUps', 'myTasks', 'donutSales', 'funnelTotal', 'weekStart', 'weekEnd', 'incomeMonth', 'incomeTotal', 'scheduleSummary', 'scheduleTypes', 'meetingsToday', 'meetingsPending'));
    }

    public function kpi(Request $request, SalesKpiService $salesKpiService)
    {
        $month = (int) $request->input('month', now()->month);
        $year = (int) $request->input('year', now()->year);

        $teamSummary = $salesKpiService->getTeamSummary($month, $year);
        $leaderboard = $salesKpiService->getLeaderboard($month, $year);
        $salesUsers = User::role('sales')->orderBy('name')->get(['id', 'name', 'email']);

        return view('manage-sales.kpi', compact('teamSummary', 'leaderboard', 'salesUsers', 'month', 'year'));
    }

    public function updateTarget(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'period_month' => 'required|integer|between:1,12',
            'period_year' => 'required|integer|min:2020|max:2100',
            'target_revenue' => 'required|numeric|min:0',
            'target_deals' => 'required|integer|min:0',
            'notes' => 'nullable|string|max:1000',
        ]);

        $salesUser = User::findOrFail($validated['user_id']);
        if (!$salesUser->hasRole('sales')) {
            abort(422, __('Target can only be set for users with the Sales role.'));
        }

        SalesTarget::updateOrCreate(
            [
                'user_id' => $validated['user_id'],
                'period_month' => $validated['period_month'],
                'period_year' => $validated['period_year'],
            ],
            [
                'target_revenue' => $validated['target_revenue'],
                'target_deals' => $validated['target_deals'],
                'notes' => $validated['notes'] ?? null,
                'created_by' => auth()->id(),
            ]
        );

        return back()->with('success', __('Sales target for :name updated successfully.', ['name' => $salesUser->name]));
    }

    public function myLeads(Request $request)
    {
        $leads = $this->myLeadsQuery($request)
            ->paginate(15)
            ->withQueryString();

        $statuses = LeadController::STATUSES;

        return view('sales.my-leads', compact('leads', 'statuses'));
    }

    public function exportMyLeads(Request $request)
    {
        $filename = 'my-leads-'.now()->format('Ymd-Hi').'.csv';

        // Streaming per 1000 baris (chunkById + eager per chunk):
        // memori tetap datar berapa pun jumlah lead.
        return response()->streamDownload(function () use ($request) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF"); // BOM agar Excel baca UTF-8.
            fputcsv($handle, ['Customer', 'PT', 'PIC', 'Telepon', 'Status', 'Kebutuhan', 'Solusi', 'Progres', 'Tgl Masuk', 'Jml Follow Up', 'Follow Up Terakhir']);
            $this->myLeadsQuery($request)
                ->with(['customer.contacts'])
                ->withMax('followUps as last_follow_up_at', 'follow_up_date')
                ->chunk(1000, function ($leads) use ($handle) {
                    foreach ($leads as $lead) {
                        // Cegah CSV formula injection: sel yang diawali = + - @ dinetralkan.
                        $safe = fn ($value) => is_string($value) && preg_match('/^[=+\-@]/', $value) ? "'".$value : $value;
                        fputcsv($handle, [
                            $safe($lead->customer?->name ?? '-'),
                            $safe($lead->pt_group ?? '-'),
                            $safe($lead->customer?->picName() ?? '-'),
                            $safe($lead->customer?->phone ?? $lead->customer?->whatsapp ?? '-'),
                            $safe(ucfirst($lead->status)),
                            $safe($lead->kebutuhan ?? '-'),
                            $safe($lead->solusi ?? '-'),
                            $safe($lead->progress_notes ?? '-'),
                            $lead->incoming_date?->format('Y-m-d') ?? '-',
                            $lead->follow_ups_count,
                            $lead->last_follow_up_at ?? '-',
                        ]);
                    }
                });
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function myLeadsQuery(Request $request)
    {
        $query = Lead::with(['customer.contacts', 'partner'])
            ->withCount(['followUps'])
            ->withExists(['followUps as has_done_fu' => fn ($q) => $q->whereNotNull('completed_at')])
            ->where('assigned_to', auth()->id())
            ->when($request->filled('search'), fn ($q) => $q->whereHas('customer',
                fn ($c) => $c->whereLike('name', $request->search)))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('active'), fn ($q) => $q->whereNotIn('status', ['won', 'lost']))
            ->when($request->filled('won_month'), fn ($q) => $q->where('status', 'won')
                ->whereMonth('updated_at', now()->month)
                ->whereYear('updated_at', now()->year))
            ->when($request->filled('touched'), fn ($q) => $request->touched === 'yes'
                ? $q->whereHas('followUps')
                : $q->whereDoesntHave('followUps'));

        // Urutan: terbaru (default), terlama, atau nama customer A-Z.
        return match ($request->input('sort')) {
            'oldest' => $query->oldest(),
            'customer' => $query->orderBy(
                Customer::select('name')->whereColumn('customers.id', 'leads.customer_id')
            )->latest('leads.id'),
            default => $query->latest(),
        };
    }

    public function activityLog(Request $request)
    {
        return view('manage-sales.activity-log', array_merge($this->activityLogData($request), [
            'customerNames' => Customer::pluck('name', 'id'),
            'userNames' => User::pluck('name', 'id'),
            'managementUsers' => User::permission('manage-sales-leads')->orderBy('name')->get(['id', 'name']),
        ]));
    }

    /**
     * Endpoint poll daftar Activity Log: hanya potongan daftar (tanpa layout),
     * supaya aktivitas baru muncul tanpa reload halaman.
     */
    public function activityLogFeed(Request $request)
    {
        return response()->view('manage-sales.partials.activity-log-body', array_merge($this->activityLogData($request), [
            'customerNames' => Customer::pluck('name', 'id'),
            'userNames' => User::pluck('name', 'id'),
        ]));
    }

    /** Query + grouping dipakai halaman Activity Log dan endpoint poll-nya. */
    private function activityLogData(Request $request): array
    {
        $managementIds = User::permission('manage-sales-leads')->pluck('id');

        $activities = LeadActivity::with(['lead.customer', 'user.roles'])
            ->whereIn('user_id', $managementIds)
            ->when($request->filled('user'), fn ($q) => $q->where('user_id', $request->user))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date_to))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $grouped = $activities->getCollection()
            ->groupBy(fn ($a) => $a->created_at->toDateString())
            ->sortKeysDesc();

        return [
            'activities' => $activities,
            'filterUser' => $request->filled('user') ? User::find($request->user) : null,
            'grouped' => $grouped,
        ];
    }

    private function trackAssignment(Lead $lead, ?int $assignedTo): void
    {
        $lead->assigned_to = $assignedTo;
        $lead->assigned_by = $assignedTo ? auth()->id() : null;
        $lead->assigned_at = $assignedTo ? now() : null;
        // Assign (baru/reassign) = belum ditangani -> kembali ke kolom New pipeline.
        $lead->acknowledged_at = $assignedTo ? null : $lead->acknowledged_at;
    }

    /**
     * Notifikasi ke sales yang baru di-assign (hanya bila ganti orang,
     * bukan assign ulang ke orang yang sama atau ke diri sendiri).
     */
    private function notifyAssignee(Lead $lead, mixed $previousAssignee): void
    {
        if (empty($lead->assigned_to)
            || (int) $lead->assigned_to === (int) $previousAssignee
            || (int) $lead->assigned_to === (int) auth()->id()) {
            return;
        }

        try {
            User::find($lead->assigned_to)?->notify(new LeadAssignedNotification($lead));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function clearLeadNotifications(Lead $lead): void
    {
        DatabaseNotification::where('type', NewLeadNotification::class)
            ->get()
            ->filter(fn ($n) => ($n->data['lead_id'] ?? null) == $lead->id)
            ->each->delete();

        // Bersihkan notif assign basi milik sales lain (mis. reassign):
        // bell tiap sales hanya berisi lead miliknya sendiri.
        DatabaseNotification::where('type', LeadAssignedNotification::class)
            ->get()
            ->filter(fn ($n) => ($n->data['lead_id'] ?? null) == $lead->id
                && (int) $n->notifiable_id !== (int) $lead->assigned_to)
            ->each->delete();
    }

    private function logActivity(Lead $lead, string $action, ?array $changes = null): void
    {
        LeadActivity::create([
            'lead_id' => $lead->id,
            'user_id' => auth()->id(),
            'action' => $action,
            'changes' => $changes,
        ]);
    }
}
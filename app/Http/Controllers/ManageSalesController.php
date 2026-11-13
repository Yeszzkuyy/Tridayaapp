<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\SalesSchedule;
use App\Models\SalesTarget;
use App\Models\User;
use App\Notifications\LeadAssignedNotification;
use App\Notifications\NewLeadNotification;
use App\Services\SalesKpiService;
use App\Services\SalesReportStatusService;
use App\Services\SalesService;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;

class ManageSalesController extends Controller
{
    public function __construct(private SalesService $salesService) {}

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
            'overdue' => Lead::assignmentOverdue()->count(),
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
            ->with('success', __('Lead berhasil diperbarui'));
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
            ->with('success', __('Lead assigned to') . ' ' . $salesUser->name);
    }

    public function dashboard(Request $request, SalesKpiService $salesKpiService, SalesReportStatusService $reportStatusService)
    {
        $user = auth()->user();
        $data = $this->dashboardData($request, $user, $salesKpiService, $reportStatusService);

        // Leaderboard hanya untuk supervisor. Sales murni tidak boleh
        // melihat performa dan nama sales lain.
        $data['salesLeaderboard'] = $this->canSupervise($user)
            ? $salesKpiService->getLeaderboard(now()->month, now()->year)
            : collect();

        return view('sales.dashboard', $data);
    }

    /**
     * Snapshot dashboard + customer milik satu sales untuk supervisi
     * Lead of Sales (Bu Yanita). Sales murni ditolak oleh middleware rute.
     */
    public function showSalesRep(Request $request, User $user, SalesKpiService $salesKpiService, SalesReportStatusService $reportStatusService)
    {
        abort_unless($this->isSalesRep($user), 404);

        $data = $this->dashboardData($request, $user, $salesKpiService, $reportStatusService);
        $data['rep'] = $user;
        $data['salesLeaderboard'] = $salesKpiService->getLeaderboard(now()->month, now()->year);
        $data['repActivities'] = FollowUp::with('customer')
            ->where(function ($q) use ($user) {
                $q->whereHas('lead', fn ($l) => $l->where('assigned_to', $user->id))
                    ->orWhere('created_by', $user->id);
            })
            ->latest('created_at')
            ->limit(10)
            ->get();
        $data['repLeads'] = Lead::with('customer')
            ->where('assigned_to', $user->id)
            ->latest('updated_at')
            ->limit(5)
            ->get();

        return view('manage-sales.sales-rep', $data);
    }

    private function canSupervise(User $user): bool
    {
        return $user->hasAnyRole(['management', 'ceo', 'super-admin'])
            || $user->can('manage-sales-leads');
    }

    private function isSalesRep(User $user): bool
    {
        return $user->hasRole('sales')
            || $user->roles->contains(fn ($role) => str_starts_with($role->name, 'sales-'));
    }

    /**
     * @return array<string, mixed>
     */
    private function dashboardData(Request $request, User $user, SalesKpiService $salesKpiService, SalesReportStatusService $reportStatusService): array
    {
        $ownLead = fn ($q) => $q->where('assigned_to', $user->id);
        // Follow Up tidak menghitung Meeting (menu Meeting terpisah).
        $ownActivity = function ($q) use ($ownLead, $user) {
            $q->where(fn ($t) => $t->whereNull('type')->orWhere('type', '!=', 'meeting'))
                ->where(function ($w) use ($ownLead, $user) {
                    $w->whereHas('lead', $ownLead)
                        ->orWhere(fn ($x) => $x->whereNull('lead_id')->where('created_by', $user->id));
                });
        };
        $mine = fn () => Lead::where('assigned_to', $user->id);
        $kpi = [
            'active' => $mine()->whereNotIn('status', ['won', 'lost'])->count(),
            'won_month' => $mine()->where('status', 'won')
                ->whereMonth('updated_at', now()->month)
                ->whereYear('updated_at', now()->year)->count(),
            'overdue' => $mine()->assignmentOverdue()->count(),
            'followups_today' => FollowUp::where($ownActivity)
                ->whereNull('completed_at')
                ->whereDate('follow_up_date', today())->count(),
            'followups_upcoming' => FollowUp::where($ownActivity)
                ->whereNull('completed_at')
                ->whereNotNull('follow_up_date')
                ->whereDate('follow_up_date', '>', today())->count(),
        ];

        $meetingBase = \App\Models\FollowUp::query()->meetings()->where(function ($w) use ($user) {
            $w->where('created_by', $user->id)
                ->orWhereHas('lead', fn ($l) => $l->where('assigned_to', $user->id));
        });
        $meetingsToday = (clone $meetingBase)->whereDate('follow_up_date', today())->count();
        $meetingsPending = (clone $meetingBase)->whereNull('completed_at')
            ->whereDate('follow_up_date', '<=', today())->count();
        $dailyReport = $reportStatusService->summaryFor($user);

        // KPI Personal
        $myKpi = $salesKpiService->getUserKpi($user, now()->month, now()->year);

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

        // Deal PO bulan berjalan: milik Sales ini + total seluruh Sales per PT.
        $dealPoMonth = $salesKpiService->getVerifiedPoByPt(now()->month, now()->year);
        $dealPoTotal = (float) ($myKpi['verified_po_revenue'] ?? 0);
        $dealPoDeals = (int) ($myKpi['verified_po_deals'] ?? 0);

        // Ringkasan agenda sales milik sendiri (jadwal saya: PIC/creator/lead saya).
        $ownSchedules = fn ($q) => $q->where(function ($w) use ($user) {
            $w->where('sales_schedules.assigned_to', $user->id)
                ->orWhere('sales_schedules.created_by', $user->id)
                ->orWhereHas('lead', fn ($l) => $l->where('assigned_to', $user->id));
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

        // Customer-Centric Hub: pencarian cepat + filter tahap.
        $hubSearch = trim((string) $request->query('q', ''));
        $hubStage = trim((string) $request->query('stage', ''));
        $customerHub = $this->customerHubRows($user, $hubSearch, $hubStage);
        $hubStages = [
            'po_verified' => __('PO Verified'),
            'quotation_sent' => __('Quotation Sent'),
            'project_running' => __('Project Running'),
            'invoice_paid' => __('Invoice Paid'),
        ];

        return compact(
            'myKpi',
            'dueFollowUps',
            'myTasks',
            'donutSales',
            'funnelTotal',
            'weekStart',
            'weekEnd',
            'dealPoMonth',
            'dealPoTotal',
            'dealPoDeals',
            'scheduleSummary',
            'scheduleTypes',
            'meetingsToday',
            'meetingsPending',
            'dailyReport',
            'kpi',
            'customerHub',
            'hubSearch',
            'hubStage',
            'hubStages',
        );
    }

    /**
     * Ambil customer yang benar-benar dipegang sales: lead yang di-assign,
     * proposal yang dia buat, atau proyek dengan sales_user_id miliknya.
     */
    private function customerHubRows(User $user, string $search, string $stage): \Illuminate\Support\Collection
    {
        $customers = Customer::query()
            ->where(function ($q) use ($user) {
                $q->whereHas('leads', fn ($l) => $l->where('assigned_to', $user->id))
                    ->orWhereHas('leads.proposals', fn ($p) => $p->where('created_by', $user->id))
                    ->orWhereHas('projects', fn ($p) => $p->where('sales_user_id', $user->id));
            })
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($w) use ($search) {
                    $w->where('name', 'like', '%'.$search.'%')
                        ->orWhere('company', 'like', '%'.$search.'%')
                        ->orWhere('contact_person', 'like', '%'.$search.'%');
                });
            })
            ->with([
                'leads' => fn ($q) => $q->with(['proposals' => fn ($p) => $p->orderByDesc('updated_at')])->orderByDesc('updated_at'),
                'projects' => fn ($q) => $q->with('status')->orderByDesc('updated_at'),
                'invoices',
                'followUps' => fn ($q) => $q->orderByDesc('id'),
            ])
            ->withCount('documents')
            ->orderByDesc('updated_at')
            ->get();

        return $customers->map(function (Customer $customer) {
            [$label, $tone, $key] = $this->customerStage($customer);
            $lastFollowUp = $customer->followUps->first();
            $poDocuments = $customer->leads
                ->flatMap(fn ($lead) => $lead->proposals)
                ->whereNotNull('po_document_path')
                ->count();

            // Quotation Accepted yang bisa dipilih saat upload PO resmi dari
            // Customer Hub (bukan dokumen umum).
            $acceptedQuotations = $customer->leads
                ->flatMap(fn ($lead) => $lead->proposals)
                ->where('status', 'accepted')
                ->sortByDesc('updated_at')
                ->map(fn ($proposal) => [
                    'id' => $proposal->id,
                    'number' => $proposal->proposal_number,
                    'total_label' => 'Rp '.number_format((float) $proposal->grand_total, 0, ',', '.'),
                    'po_number' => $proposal->po_number,
                    'has_po' => (bool) $proposal->po_document_path,
                    'delivery_address' => $proposal->delivery_address,
                    'delivery_deadline' => $proposal->delivery_deadline?->toDateString(),
                ])
                ->values();

            return [
                'customer' => $customer,
                'lead' => $customer->leads->first(),
                'stage' => $label,
                'stage_key' => $key,
                'tone' => $tone,
                'last_note' => $lastFollowUp?->description,
                'last_note_at' => $lastFollowUp?->created_at,
                'documents' => (int) $customer->documents_count + $poDocuments,
                'accepted_quotations' => $acceptedQuotations,
            ];
        })->when($stage !== '', fn ($rows) => $rows->where('stage_key', $stage)->values())
            ->take(15)
            ->values();
    }

    /** @return array{0: string, 1: string, 2: string} label, tone, key */
    private function customerStage(Customer $customer): array
    {
        $proposal = $customer->leads
            ->flatMap(fn ($lead) => $lead->proposals)
            ->sortByDesc('updated_at')
            ->first();
        $invoice = $customer->invoices->sortByDesc('issue_date')->first();
        $project = $customer->projects->sortByDesc('updated_at')->first();
        $lead = $customer->leads->first();
        $projectStatus = strtolower((string) $project?->status?->name);

        if ($invoice?->status === 'paid') {
            return [__('Invoice Paid'), 'emerald', 'invoice_paid'];
        }

        if ($project && ! in_array($projectStatus, ['done', 'cancelled', 'cancel'], true)) {
            return [__('Project Running'), 'violet', 'project_running'];
        }

        if ($proposal?->po_verified_at) {
            return [__('PO Verified'), 'indigo', 'po_verified'];
        }

        if ($proposal && in_array($proposal->status, ['sent', 'viewed'], true)) {
            return [__('Quotation Sent'), 'blue', 'quotation_sent'];
        }

        if ($proposal) {
            return [__('Quotation').' '.ucfirst((string) $proposal->status), 'sky', 'quotation_'.$proposal->status];
        }

        $status = $lead?->status ?? 'cool';

        return [Lead::salesStatusLabel($status), $status === 'hot' ? 'orange' : 'slate', 'lead_'.$status];
    }



    /**
     * Team Activity — pemantauan harian tim sales untuk Management:
     * kepatuhan jadwal hari ini, aktivitas harian, lead mengendap, dan funnel.
     */
    public function teamActivity(Request $request, SalesReportStatusService $reportStatusService)
    {
        $today = now()->toDateString();

        $date = today();
        if ($request->filled('date')) {
            try {
                $date = Carbon::createFromFormat('Y-m-d', $request->string('date')->toString());
            } catch (\Throwable) {
                $date = today();
            }
        }
        $dateString = $date->toDateString();

        $ptGroup = in_array($request->query('pt_group'), Lead::PT_GROUPS, true)
            ? $request->query('pt_group')
            : null;

        $reportRows = $reportStatusService->daily($dateString, $ptGroup);

        $salesRoleNames = \Spatie\Permission\Models\Role::query()
            ->where('name', 'like', 'sales%')
            ->pluck('name')
            ->all();

        $salesUsers = User::role($salesRoleNames)->orderBy('name')->get(['id', 'name']);

        $todaySchedules = SalesSchedule::with(['lead.customer', 'assignee'])
            ->whereDate('start_at', $today)
            ->where('status', '!=', 'cancelled')
            ->orderBy('start_at')
            ->get();

        $overdueSchedules = SalesSchedule::with(['lead.customer', 'assignee'])
            ->where('start_at', '<', now()->startOfDay())
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->orderBy('start_at')
            ->get();

        $followUpsToday = FollowUp::query()
            ->whereDate('created_at', $today)
            ->selectRaw('created_by, count(*) as total')
            ->groupBy('created_by')
            ->pluck('total', 'created_by');

        $leadActivitiesToday = LeadActivity::query()
            ->whereDate('created_at', $today)
            ->selectRaw('user_id, count(*) as total')
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        $rows = $salesUsers->map(function (User $user) use ($todaySchedules, $overdueSchedules, $followUpsToday, $leadActivitiesToday) {
            $schedules = $todaySchedules->where('assigned_to', $user->id);
            $done = $schedules->where('status', 'completed')->count();
            $pending = $schedules->filter(fn ($s) => $s->status !== 'completed' && $s->start_at && $s->start_at->isPast())->count();

            return [
                'user' => $user,
                'total' => $schedules->count(),
                'done' => $done,
                'pending' => $pending,
                'upcoming' => max(0, $schedules->count() - $done - $pending),
                'overdue' => $overdueSchedules->where('assigned_to', $user->id)->count(),
                'follow_ups' => (int) ($followUpsToday[$user->id] ?? 0),
                'lead_activities' => (int) ($leadActivitiesToday[$user->id] ?? 0),
            ];
        });

        // Lead Warm/Hot yang tidak disentuh follow-up lebih dari 7 hari.
        $staleLeads = Lead::with(['customer', 'assignee'])
            ->whereIn('status', ['warm', 'hot'])
            ->whereNotNull('assigned_to')
            ->whereDoesntHave('followUps', fn ($q) => $q->where('created_at', '>=', now()->subDays(7)))
            ->orderByDesc('updated_at')
            ->limit(12)
            ->get();

        $funnel = Lead::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('manage-sales.team-activity', compact(
            'rows',
            'staleLeads',
            'funnel',
            'todaySchedules',
            'overdueSchedules',
            'reportRows',
            'dateString',
            'ptGroup',
        ));
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
            'target_margin' => 'nullable|numeric|min:0',
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
                'target_margin' => $validated['target_margin'] ?? null,
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
            fputcsv($handle, ['Customer', 'PT', 'PIC', 'Telepon', 'Status', 'Kebutuhan', 'Solusi', 'Progres', 'Tgl Masuk', 'Jml Follow Up', 'Follow Up Terakhir', 'Proyek', 'Aktivitas Terakhir', 'Next Follow Up']);
            $this->myLeadsQuery($request)
                ->with(['customer.contacts', 'latestActivityFollowUp', 'activeFollowUp'])
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
                            $safe($lead->project_name ?? '-'),
                            $lead->latestActivityFollowUp?->follow_up_date?->format('Y-m-d') ?? '-',
                            $lead->activeFollowUp?->follow_up_date?->format('Y-m-d') ?? '-',
                        ]);
                    }
                });
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function myLeadsQuery(Request $request)
    {
        $activeQueue = fn ($q) => $q->whereNull('completed_at')
            ->where(fn ($s) => $s->whereNull('status')->orWhere('status', '!=', 'completed'))
            ->where(fn ($t) => $t->whereNull('type')->orWhere('type', '!=', 'meeting'));

        $query = Lead::with(['customer.contacts', 'partner', 'activeFollowUp', 'latestNoteFollowUp', 'latestActivityFollowUp'])
            ->withCount(['followUps'])
            ->withExists(['followUps as has_follow_up_since_assignment' => fn ($q) => $q->whereColumn('follow_ups.created_at', '>=', 'leads.assigned_at')
                ->where(fn ($x) => $x->where(fn ($d) => $d->whereNotNull('description')->where('description', '!=', ''))
                    ->orWhereNotNull('completed_at')
                    ->orWhereNotNull('outcome'))])
            ->withExists(['followUps as has_done_fu' => fn ($q) => $q->whereNotNull('completed_at')])
            ->withExists(['proposals as has_accepted_proposal' => fn ($q) => $q->where('status', 'accepted')])
            ->where('assigned_to', auth()->id())
            ->when($request->filled('search'), function ($q) use ($request) {
                $like = '%'.$request->search.'%';
                $q->where(function ($w) use ($like) {
                    $w->whereHas('customer', fn ($c) => $c->whereLike('name', $like)->orWhereLike('company', $like))
                        ->orWhereLike('project_name', $like);
                });
            })
            ->when($request->filled('company'), fn ($q) => $q->whereHas('customer',
                fn ($c) => $c->whereLike('name', '%'.$request->company.'%')))
            ->when($request->filled('pic'), fn ($q) => $q->whereHas('customer.contacts',
                fn ($c) => $c->whereLike('name', '%'.$request->pic.'%')))
            ->when($request->filled('project'), fn ($q) => $q->whereLike('project_name', '%'.$request->project.'%'))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('last_activity'), function ($q) use ($request) {
                match ($request->last_activity) {
                    'today' => $q->whereHas('followUps', fn ($f) => $f->whereDate('follow_up_date', today())),
                    'week' => $q->whereHas('followUps', fn ($f) => $f->whereDate('follow_up_date', '>=', now()->startOfWeek())),
                    'month' => $q->whereHas('followUps', fn ($f) => $f->whereDate('follow_up_date', '>=', now()->startOfMonth())),
                    'none' => $q->whereDoesntHave('followUps'),
                    default => $q,
                };
            })
            ->when($request->filled('next_follow_up'), function ($q) use ($request, $activeQueue) {
                match ($request->next_follow_up) {
                    'today' => $q->whereHas('followUps', fn ($f) => $activeQueue($f)->whereDate('follow_up_date', today())),
                    'upcoming' => $q->whereHas('followUps', fn ($f) => $activeQueue($f)->whereDate('follow_up_date', '>', today())),
                    'none' => $q->whereDoesntHave('followUps', $activeQueue),
                    default => $q,
                };
            })
            ->when($request->boolean('overdue'), fn ($q) => $q->assignmentOverdue())
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

    /**
     * Simpan Latest Note langsung dari tabel My Leads. Catatan lama tidak
     * dihapus: setiap simpanan menjadi baris riwayat baru.
     */
    public function updateLeadNote(Request $request, Lead $lead)
    {
        $this->authorize('update', $lead);

        $validated = $request->validate([
            'note' => 'required|string|max:2000',
        ]);

        $note = trim($validated['note']);
        $duplicate = FollowUp::where('lead_id', $lead->id)
            ->where('created_by', auth()->id())
            ->where('source', 'manual')
            ->where('type', 'note')
            ->where('description', $note)
            ->where('created_at', '>=', now()->subSeconds(10))
            ->latest()
            ->first();

        $followUp = $duplicate ?: $this->salesService->appendNote($lead, $note);

        return response()->json([
            'ok' => true,
            'message' => __('Note saved.'),
            'note' => $followUp?->description ?? $note,
            'time' => $followUp?->created_at?->timezone('Asia/Jakarta')->format('d M Y H:i'),
        ]);
    }

    public function updateLeadNextFollowUp(Request $request, Lead $lead)
    {
        $this->authorize('update', $lead);

        $validated = $request->validate([
            'next_follow_up_date' => 'required|date|after_or_equal:today',
            'next_follow_up_time' => 'nullable|date_format:H:i',
        ]);

        $followUp = $this->salesService->setNextFollowUp(
            $lead,
            $validated['next_follow_up_date'],
            $validated['next_follow_up_time'] ?? null
        );

        return response()->json([
            'ok' => true,
            'message' => __('Next follow up saved.'),
            'date' => $followUp->follow_up_date?->format('d M Y'),
            'time' => $followUp->activity_time ? substr((string) $followUp->activity_time, 0, 5) : null,
            'state' => $followUp->scheduleState(),
            'state_label' => FollowUp::scheduleStateLabel($followUp->scheduleState()),
        ]);
    }

    public function updateLeadColor(Request $request, Lead $lead)
    {
        $this->authorize('update', $lead);

        $validated = $request->validate([
            'color_tag' => 'nullable|in:'.implode(',', Lead::COLOR_TAGS),
        ]);

        $color = $validated['color_tag'] ?: null;
        $lead->update(['color_tag' => $color]);

        return response()->json([
            'ok' => true,
            'message' => __('Color saved.'),
            'color' => $color ?? '',
        ]);
    }

    /**
     * Catat klik kanal kontak. Bukan status "terkirim"; hanya audit bahwa
     * Sales mulai menghubungi customer.
     */
    public function logLeadContact(Request $request, Lead $lead)
    {
        $this->authorize('update', $lead);

        $validated = $request->validate([
            'channel' => 'required|in:whatsapp,email,call',
            'follow_up_id' => 'nullable|exists:follow_ups,id',
        ]);

        $followUp = null;
        if (! empty($validated['follow_up_id'])) {
            $followUp = FollowUp::findOrFail($validated['follow_up_id']);
            abort_unless((int) $followUp->lead_id === (int) $lead->id, 404);
        }

        $result = $this->salesService->logContactInitiated($lead, $validated['channel'], $followUp);

        return response()->json([
            'ok' => true,
            'duplicate' => $result['duplicate'],
            'message' => $result['duplicate'] ? __('Contact already recorded.') : __('Contact started.'),
        ]);
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
        $lead->assignment_overdue_notified_at = null;
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
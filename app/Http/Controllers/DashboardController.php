<?php

namespace App\Http\Controllers;

use App\Enums\KnowledgeBaseCategory;
use App\Enums\ProjectStatus as ProjectStatusEnum;
use App\Models\Customer;
use App\Models\FollowUp;
use App\Models\Instalasi;
use App\Models\KnowledgeBaseDocument;
use App\Models\LeadTask;
use App\Models\Product;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\ProjectDocument;
use App\Models\ProjectStatus;
use App\Models\ProjectTask;
use App\Models\SalesSchedule;
use App\Models\Survey;
use App\Models\TechnicalRequest;
use App\Models\TechnicianSchedule;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DashboardController extends Controller
{
    private const STATUS_STYLES = [
        'Open' => [
            'hex' => '#6366f1',
            'bar' => 'bg-indigo-500',
            'dot' => 'bg-indigo-500',
            'badge' => 'bg-indigo-50 text-indigo-700 border-indigo-200 dark:bg-indigo-500/15 dark:text-indigo-300 dark:border-indigo-500/30',
        ],
        'On Progress' => [
            'hex' => '#f59e0b',
            'bar' => 'bg-amber-500',
            'dot' => 'bg-amber-500',
            'badge' => 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:border-amber-500/30',
        ],
        'Pending' => [
            'hex' => '#f97316',
            'bar' => 'bg-orange-500',
            'dot' => 'bg-orange-500',
            'badge' => 'bg-orange-50 text-orange-700 border-orange-200 dark:bg-orange-500/15 dark:text-orange-300 dark:border-orange-500/30',
        ],
        'Hold' => [
            'hex' => '#f43f5e',
            'bar' => 'bg-rose-500',
            'dot' => 'bg-rose-500',
            'badge' => 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-500/15 dark:text-rose-300 dark:border-rose-500/30',
        ],
        'Done' => [
            'hex' => '#10b981',
            'bar' => 'bg-emerald-500',
            'dot' => 'bg-emerald-500',
            'badge' => 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:border-emerald-500/30',
        ],
        'Completed' => [
            'hex' => '#10b981',
            'bar' => 'bg-emerald-500',
            'dot' => 'bg-emerald-500',
            'badge' => 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:border-emerald-500/30',
        ],
        'Warranty' => [
            'hex' => '#06b6d4',
            'bar' => 'bg-cyan-500',
            'dot' => 'bg-cyan-500',
            'badge' => 'bg-cyan-50 text-cyan-700 border-cyan-200 dark:bg-cyan-500/15 dark:text-cyan-300 dark:border-cyan-500/30',
        ],
        'Maintenance' => [
            'hex' => '#8b5cf6',
            'bar' => 'bg-violet-500',
            'dot' => 'bg-violet-500',
            'badge' => 'bg-violet-50 text-violet-700 border-violet-200 dark:bg-violet-500/15 dark:text-violet-300 dark:border-violet-500/30',
        ],
        'Cancelled' => [
            'hex' => '#64748b',
            'bar' => 'bg-slate-400',
            'dot' => 'bg-slate-400',
            'badge' => 'bg-slate-100 text-slate-600 border-slate-200 dark:bg-slate-700 dark:text-slate-300 dark:border-slate-600',
        ],
    ];

    public function index()
    {
        $now = Carbon::now('Asia/Jakarta');
        $startOfMonth = $now->copy()->startOfMonth();
        $endOfMonth = $now->copy()->endOfMonth();
        $user = auth()->user();

        // 1. Agregasi Project Status (1 query efisien via withCount)
        $rawStatuses = ProjectStatus::query()
            ->withCount('projects')
            ->orderBy('sort_order')
            ->get();

        $statusMap = $rawStatuses->keyBy('name');
        $openProjectsCount = (int) ($statusMap->get(ProjectStatusEnum::Open->value)?->projects_count ?? 0);
        $onProgressProjectsCount = (int) ($statusMap->get(ProjectStatusEnum::OnProgress->value)?->projects_count ?? 0);
        $completedProjectsTotal = (int) (
            ($statusMap->get(ProjectStatusEnum::Done->value)?->projects_count ?? 0)
            + ($statusMap->get('Completed')?->projects_count ?? 0)
        );

        $totalProjects = (int) Project::count();
        $activeProjects = $openProjectsCount + $onProgressProjectsCount;

        // Fallback jika tabel project_statuses belum di-seed tetapi ada project
        if ($rawStatuses->isEmpty() && $totalProjects > 0) {
            $activeProjects = (int) Project::whereHas('status', fn ($q) => $q->whereIn('name', [
                ProjectStatusEnum::Open->value,
                ProjectStatusEnum::OnProgress->value,
            ]))->count();
        }

        // 2. Key Summary Metrics (Customers, Projects, Active Breakdown, Docs & KB)
        $customerCount = (int) Customer::count();
        $newCustomersThisMonth = (int) Customer::whereBetween('created_at', [$startOfMonth, $endOfMonth])->count();

        $completedProjectsThisMonth = (int) Project::query()
            ->where(function ($q) use ($startOfMonth, $endOfMonth) {
                $q->whereBetween('bast_date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
                    ->orWhereBetween('bast_ready_at', [$startOfMonth, $endOfMonth])
                    ->orWhere(function ($sub) use ($startOfMonth, $endOfMonth) {
                        $sub->whereHas('status', fn ($s) => $s->whereIn('name', ['Done', 'Completed']))
                            ->whereBetween('updated_at', [$startOfMonth, $endOfMonth]);
                    });
            })
            ->count();

        $documentCount = (int) ProjectDocument::count();
        $kbDocumentCount = (int) KnowledgeBaseDocument::count();
        $productKbCount = (int) Product::where('status', '!=', Product::STATUS_REJECTED)->count();
        $kbTotalCount = $kbDocumentCount + $productKbCount;
        $totalDocsAndKb = $documentCount + $kbTotalCount;
        $userCount = (int) User::count();

        // 3. Distribusi Project Status untuk Donut Chart & Legend
        $statusTotalBase = max($totalProjects, (int) $rawStatuses->sum('projects_count'));
        $projectStatusBreakdown = $rawStatuses->map(function (ProjectStatus $status) use ($statusTotalBase) {
            $count = (int) $status->projects_count;
            $pct = $statusTotalBase > 0 ? round(($count / $statusTotalBase) * 100, 1) : 0.0;
            $style = self::STATUS_STYLES[$status->name] ?? [
                'hex' => '#6366f1',
                'bar' => 'bg-indigo-500',
                'dot' => 'bg-indigo-500',
                'badge' => 'bg-indigo-50 text-indigo-700 border-indigo-200 dark:bg-indigo-500/15 dark:text-indigo-300 dark:border-indigo-500/30',
            ];

            return [
                'id' => $status->id,
                'name' => $status->name,
                'count' => $count,
                'percentage' => $pct,
                'hex_color' => $style['hex'],
                'bar_class' => $style['bar'],
                'dot_class' => $style['dot'],
                'badge_class' => $style['badge'],
            ];
        })->values();

        // 4. Role-Aware My Agenda & Tasks Today (maks 5 item)
        [$agendaItems, $agendaMeta] = $this->buildRoleAwareAgenda($user, $now);

        // 5. Company Board / Knowledge Base Highlights (3-4 item terbaru)
        $kbHighlights = $this->buildKnowledgeHighlights($user);

        // 6. Recent Activity (Eager loaded & deduplicated)
        $activities = ProjectActivity::with([
            'project:id,project_name,customer_id',
            'project.customer:id,name',
            'user:id,name,avatar',
        ])
            ->latest('activity_date')
            ->latest('id')
            ->take(25)
            ->get()
            ->unique(fn (ProjectActivity $activity) => $activity->dedupKey())
            ->take(5)
            ->values();

        return view('dashboard.index', compact(
            'customerCount',
            'newCustomersThisMonth',
            'documentCount',
            'kbDocumentCount',
            'productKbCount',
            'kbTotalCount',
            'totalDocsAndKb',
            'userCount',
            'totalProjects',
            'completedProjectsTotal',
            'completedProjectsThisMonth',
            'activeProjects',
            'openProjectsCount',
            'onProgressProjectsCount',
            'projectStatusBreakdown',
            'agendaItems',
            'agendaMeta',
            'kbHighlights',
            'activities',
            'now',
        ));
    }

    /**
     * Build up to 5 role-aware agenda/task items for the current user.
     *
     * @return array{0: Collection<int, array<string, mixed>>, 1: array<string, string>}
     */
    private function buildRoleAwareAgenda(?User $user, Carbon $now): array
    {
        $items = collect();

        if (! $user) {
            return [$items, [
                'subtitle' => __('Your scheduled activities and active tasks for today.'),
                'action_label' => __('View Projects'),
                'action_url' => route('customers.index'),
            ]];
        }

        $todayStart = $now->copy()->startOfDay();
        $todayEnd = $now->copy()->endOfDay();
        $userNameLower = mb_strtolower(trim((string) $user->name));

        // A. Teknisi / Lead Teknisi: Jadwal Teknisi, Tugas Teknisi, Survey & Instalasi
        if ($user->can('view-technician')) {
            $schedules = TechnicianSchedule::with(['project:id,project_name,customer_id', 'project.customer:id,name'])
                ->whereIn('status', ['scheduled', 'on_progress'])
                ->where('end_at', '>=', $todayStart)
                ->where(function ($q) use ($user) {
                    $q->where('technician_user_id', $user->id)
                        ->orWhere('user_id', $user->id)
                        ->orWhereHas('technicians', fn ($t) => $t->where('users.id', $user->id));
                })
                ->orderBy('start_at')
                ->take(4)
                ->get();

            foreach ($schedules as $schedule) {
                $dt = $schedule->start_at ? $schedule->start_at->setTimezone('Asia/Jakarta') : $now;
                $isToday = $schedule->start_at && $schedule->start_at->between($todayStart, $todayEnd);
                $items->push([
                    'title' => $schedule->title ?: __('Technical Schedule'),
                    'subtitle' => $schedule->project?->project_name
                        ?: ($schedule->location ?: ($schedule->project?->customer?->name ?? __('Field schedule'))),
                    'type_label' => __('Schedule'),
                    'badge_color' => 'violet',
                    'status_label' => $schedule->status === 'on_progress' ? __('On Progress') : __('Scheduled'),
                    'time_label' => $schedule->start_at
                        ? ($isToday ? $dt->format('H:i').' WIB' : $dt->format('d M, H:i'))
                        : __('Today'),
                    'day' => $dt->format('d'),
                    'month' => strtoupper($dt->format('M')),
                    'progress' => $schedule->status === 'on_progress' ? 65 : 25,
                    'url' => route('teknisi.jadwal'),
                ]);
            }

            $techRequests = TechnicalRequest::with(['lead.customer:id,name'])
                ->where('request_type', '!=', 'estimate')
                ->whereIn('status', ['pending', 'scheduled', 'on_progress'])
                ->where(function ($q) use ($user) {
                    $q->where('assigned_technician_id', $user->id);
                    if ($user->hasAnyRole(['lead-technician', 'super-admin'])) {
                        $q->orWhereNull('assigned_technician_id');
                    }
                })
                ->latest('id')
                ->take(4)
                ->get();

            foreach ($techRequests as $req) {
                $dt = $req->scheduled_at ? Carbon::parse($req->scheduled_at)->setTimezone('Asia/Jakarta') : ($req->created_at ?? $now);
                $items->push([
                    'title' => $req->title ?: __('Technical Request #:id', ['id' => $req->id]),
                    'subtitle' => $req->lead?->customer?->name ?: ($req->location ?: __('Technical Support')),
                    'type_label' => __('Tech Task'),
                    'badge_color' => 'cyan',
                    'status_label' => ucfirst(str_replace('_', ' ', (string) $req->status)),
                    'time_label' => $req->scheduled_at
                        ? $dt->format('d M Y')
                        : __('Active'),
                    'day' => $dt->format('d'),
                    'month' => strtoupper($dt->format('M')),
                    'progress' => $req->status === 'on_progress' ? 60 : ($req->status === 'scheduled' ? 35 : 15),
                    'url' => route('teknisi.technical-requests.show', $req),
                ]);
            }

            if ($items->count() < 4 && $userNameLower !== '') {
                $surveys = Survey::with(['customer:id,name', 'project:id,project_name'])
                    ->whereIn('status', ['draft', 'scheduled', 'on_survey'])
                    ->whereRaw('LOWER(pic) LIKE ?', ['%'.$userNameLower.'%'])
                    ->orderBy('survey_date')
                    ->take(3)
                    ->get();

                foreach ($surveys as $survey) {
                    $dt = $survey->survey_date ?? ($survey->created_at ?? $now);
                    $items->push([
                        'title' => $survey->title ?: __('Site Survey #:id', ['id' => $survey->id]),
                        'subtitle' => $survey->customer?->name ?: ($survey->project?->project_name ?: ($survey->location ?? '-')),
                        'type_label' => __('Survey'),
                        'badge_color' => 'emerald',
                        'status_label' => $survey->status === 'on_survey' ? __('In Progress') : __('Scheduled'),
                        'time_label' => $survey->survey_date ? $survey->survey_date->format('d M Y') : __('Active'),
                        'day' => $dt->format('d'),
                        'month' => strtoupper($dt->format('M')),
                        'progress' => $survey->status === 'on_survey' ? 65 : 30,
                        'url' => route('teknisi.surveys.show', $survey),
                    ]);
                }

                $instalasis = Instalasi::with(['project:id,project_name,customer_id', 'project.customer:id,name'])
                    ->whereIn('status', ['scheduled', 'on_progress', 'waiting'])
                    ->whereRaw('LOWER(technician_pic) LIKE ?', ['%'.$userNameLower.'%'])
                    ->orderBy('schedule_date')
                    ->take(3)
                    ->get();

                foreach ($instalasis as $instalasi) {
                    $dt = $instalasi->schedule_date ?? ($instalasi->created_at ?? $now);
                    $items->push([
                        'title' => $instalasi->project?->project_name ?: __('Installation #:id', ['id' => $instalasi->id]),
                        'subtitle' => $instalasi->project?->customer?->name ?: ($instalasi->location ?? '-'),
                        'type_label' => __('Installation'),
                        'badge_color' => 'blue',
                        'status_label' => $instalasi->status === 'on_progress' ? __('On Progress') : __('Scheduled'),
                        'time_label' => $instalasi->schedule_date ? $instalasi->schedule_date->format('d M Y') : __('Active'),
                        'day' => $dt->format('d'),
                        'month' => strtoupper($dt->format('M')),
                        'progress' => $instalasi->status === 'on_progress' ? 70 : 30,
                        'url' => route('teknisi.instalasis.show', $instalasi),
                    ]);
                }
            }
        }

        // B. Sales: Sales Schedule, Follow Up & Meeting
        if ($user->can('view-sales') && $items->count() < 4) {
            $salesSchedules = SalesSchedule::with(['lead:id,customer_id,kebutuhan', 'lead.customer:id,name'])
                ->whereIn('status', ['scheduled', 'rescheduled'])
                ->where('start_at', '>=', $todayStart)
                ->where(function ($q) use ($user) {
                    $q->where('assigned_to', $user->id)
                        ->orWhere('created_by', $user->id);
                })
                ->orderBy('start_at')
                ->take(4)
                ->get();

            foreach ($salesSchedules as $sched) {
                $dt = $sched->start_at ? $sched->start_at->setTimezone('Asia/Jakarta') : $now;
                $isToday = $sched->start_at && $sched->start_at->between($todayStart, $todayEnd);
                $items->push([
                    'title' => $sched->title ?: SalesSchedule::typeLabel((string) $sched->type),
                    'subtitle' => $sched->lead?->customer?->name ?: ($sched->lead?->kebutuhan ?: ($sched->location ?? __('Sales Agenda'))),
                    'type_label' => SalesSchedule::typeLabel((string) $sched->type),
                    'badge_color' => 'indigo',
                    'status_label' => SalesSchedule::statusLabel((string) $sched->status),
                    'time_label' => $sched->start_at
                        ? ($isToday ? $dt->format('H:i').' WIB' : $dt->format('d M, H:i'))
                        : __('Scheduled'),
                    'day' => $dt->format('d'),
                    'month' => strtoupper($dt->format('M')),
                    'progress' => $isToday ? 60 : 35,
                    'url' => route('sales.schedules.index'),
                ]);
            }

            $followUps = FollowUp::with(['customer:id,name', 'lead:id,customer_id,kebutuhan', 'lead.customer:id,name'])
                ->whereNull('completed_at')
                ->where(function ($q) use ($user) {
                    $q->where('created_by', $user->id)
                        ->orWhereHas('lead', fn ($l) => $l->where('assigned_to', $user->id));
                })
                ->where(function ($q) use ($todayEnd) {
                    $q->whereDate('follow_up_date', '<=', $todayEnd)
                        ->orWhereDate('next_follow_up_date', '<=', $todayEnd);
                })
                ->orderByDesc('follow_up_date')
                ->take(4)
                ->get();

            foreach ($followUps as $fu) {
                $isMeeting = $fu->type === 'meeting';
                $targetDate = $fu->next_follow_up_date ?? $fu->follow_up_date ?? $now;
                $items->push([
                    'title' => $fu->description ?: FollowUp::typeLabel($fu->type),
                    'subtitle' => $fu->customer?->name ?: ($fu->lead?->customer?->name ?: ($fu->lead?->kebutuhan ?? __('Customer Follow-up'))),
                    'type_label' => $isMeeting ? __('Meeting') : __('Follow Up'),
                    'badge_color' => $isMeeting ? 'rose' : 'amber',
                    'status_label' => $targetDate->isToday() ? __('Today') : __('Pending'),
                    'time_label' => $fu->activity_time
                        ? substr((string) $fu->activity_time, 0, 5).' WIB'
                        : $targetDate->format('d M Y'),
                    'day' => $targetDate->format('d'),
                    'month' => strtoupper($targetDate->format('M')),
                    'progress' => $targetDate->isToday() ? 55 : 40,
                    'url' => $isMeeting ? route('sales.meetings.index') : route('sales.follow-ups.index'),
                ]);
            }
        }

        // C. Inside Sales: Lead Tasks
        if ($user->can('manage-inside-sales') && $items->count() < 4) {
            $leadTasks = LeadTask::with(['lead:id,customer_id,kebutuhan', 'lead.customer:id,name'])
                ->where('assigned_to', $user->id)
                ->where('status', '!=', 'done')
                ->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END, due_date ASC')
                ->take(4)
                ->get();

            foreach ($leadTasks as $task) {
                $dt = $task->due_date ?? ($task->created_at ?? $now);
                $items->push([
                    'title' => $task->title ?: __('Task #:id', ['id' => $task->id]),
                    'subtitle' => $task->lead?->customer?->name ?: ($task->lead?->kebutuhan ?? __('Inside Sales Task')),
                    'type_label' => __('Inside Sales'),
                    'badge_color' => 'amber',
                    'status_label' => LeadTask::statusLabel($task->status),
                    'time_label' => $task->due_date ? __('Due :date', ['date' => $task->due_date->format('d M')]) : __('Active'),
                    'day' => $dt->format('d'),
                    'month' => strtoupper($dt->format('M')),
                    'progress' => $task->status === 'review' ? 80 : ($task->status === 'in_progress' ? 55 : 25),
                    'url' => route('lead-tasks.show', $task),
                ]);
            }
        }

        // D. Project Tasks atau Active Projects yang melibatkan user (atau proyek aktif kantor jika masih < 4)
        if ($items->count() < 4) {
            $projectTasks = ProjectTask::with(['project:id,project_name,customer_id', 'project.customer:id,name'])
                ->where('assigned_to', $user->id)
                ->whereNotIn('status', ['done', 'completed', 'Done', 'Completed'])
                ->orderBy('due_date')
                ->take(4)
                ->get();

            foreach ($projectTasks as $pt) {
                $dt = $pt->due_date ?? ($pt->created_at ?? $now);
                $items->push([
                    'title' => $pt->task_name ?: __('Project Task'),
                    'subtitle' => $pt->project?->project_name ?: ($pt->project?->customer?->name ?? '-'),
                    'type_label' => __('Task'),
                    'badge_color' => 'blue',
                    'status_label' => ucfirst((string) ($pt->status ?: 'Open')),
                    'time_label' => $pt->due_date ? __('Due :date', ['date' => $pt->due_date->format('d M')]) : __('Active'),
                    'day' => $dt->format('d'),
                    'month' => strtoupper($dt->format('M')),
                    'progress' => 50,
                    'url' => $pt->project_id && $user->canAny(['view-technician', 'manage-technician', 'view-sales', 'manage-sales', 'view-admin', 'manage-admin'])
                        ? route('projects.show', $pt->project_id)
                        : route('customers.index'),
                ]);
            }
        }

        if ($items->count() < 4) {
            $canOpenProject = $user->canAny(['view-technician', 'manage-technician', 'view-sales', 'manage-sales', 'view-admin', 'manage-admin']);
            $activeProjectQuery = Project::with(['customer:id,name', 'status:id,name'])
                ->whereHas('status', fn ($q) => $q->whereIn('name', [
                    ProjectStatusEnum::Open->value,
                    ProjectStatusEnum::OnProgress->value,
                ]));

            $userProjects = (clone $activeProjectQuery)
                ->where(function ($q) use ($user, $userNameLower) {
                    $q->where('sales_user_id', $user->id)
                        ->orWhere('teknisi_user_id', $user->id);
                    if ($userNameLower !== '') {
                        $q->orWhereRaw('LOWER(pic_engineer) LIKE ?', ['%'.$userNameLower.'%'])
                            ->orWhereRaw('LOWER(support_technicians) LIKE ?', ['%'.$userNameLower.'%']);
                    }
                })
                ->latest('updated_at')
                ->take(4 - $items->count())
                ->get();

            if ($userProjects->isEmpty() && $items->isEmpty()) {
                $userProjects = $activeProjectQuery
                    ->latest('updated_at')
                    ->take(4)
                    ->get();
            }

            foreach ($userProjects as $proj) {
                $dt = $proj->start_date ?? ($proj->updated_at ?? ($proj->created_at ?? $now));
                $isOnProgress = $proj->status?->name === 'On Progress';
                $rawProgress = (int) ($proj->progress ?? 0);
                $computedProgress = $rawProgress > 0 ? min(100, $rawProgress) : ($isOnProgress ? 60 : 25);

                $items->push([
                    'title' => $proj->project_name ?: __('Project #:id', ['id' => $proj->id]),
                    'subtitle' => $proj->customer?->name ?: ($proj->pic_engineer ? __('PIC: :name', ['name' => $proj->pic_engineer]) : __('Active Project')),
                    'type_label' => __('Project'),
                    'badge_color' => $isOnProgress ? 'amber' : 'blue',
                    'status_label' => $proj->status?->name ?? __('Active'),
                    'time_label' => $proj->delivery_deadline
                        ? __('Due :date', ['date' => $proj->delivery_deadline->format('d M')])
                        : ($proj->updated_at ? $proj->updated_at->setTimezone('Asia/Jakarta')->format('d M Y') : __('Ongoing')),
                    'day' => $dt->format('d'),
                    'month' => strtoupper($dt->format('M')),
                    'progress' => $computedProgress,
                    'url' => $canOpenProject ? route('projects.show', $proj) : route('customers.index'),
                ]);
            }
        }

        // Metadata header widget sesuai role utama user
        if ($user->hasAnyRole(['technician', 'lead-technician', 'prakerin-technician'])) {
            $meta = [
                'subtitle' => __('Your technical schedules, field work, and active engineering tasks.'),
                'action_label' => __('Open Schedule'),
                'action_url' => route('teknisi.jadwal'),
            ];
        } elseif ($user->hasRole('sales') || $user->can('manage-sales')) {
            $meta = [
                'subtitle' => __('Your scheduled meetings, customer follow-ups, and active deals.'),
                'action_label' => __('Sales Schedule'),
                'action_url' => route('sales.schedules.index'),
            ];
        } elseif ($user->can('manage-inside-sales')) {
            $meta = [
                'subtitle' => __('Your assigned quotation and inside sales tasks.'),
                'action_label' => __('Inside Sales'),
                'action_url' => route('lead-tasks.index'),
            ];
        } else {
            $canOpenProject = $user->canAny(['view-technician', 'manage-technician', 'view-sales', 'manage-sales', 'view-admin', 'manage-admin']);
            $meta = [
                'subtitle' => __('Active schedules, ongoing projects, and operational priorities today.'),
                'action_label' => $canOpenProject ? __('All Projects') : __('All Customers'),
                'action_url' => $canOpenProject ? route('projects.index') : route('customers.index'),
            ];
        }

        return [$items->take(4)->values(), $meta];
    }

    /**
     * Ambil 4 item terbaru gabungan dari Knowledge Base (SOP/Company Docs) & Product KB.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function buildKnowledgeHighlights(?User $user): Collection
    {
        $canManageAdmin = $user?->can('manage-admin') ?? false;

        $kbDocs = KnowledgeBaseDocument::with('uploader:id,name')
            ->where('status', '!=', 'failed')
            ->latest('updated_at')
            ->take(4)
            ->get()
            ->map(function (KnowledgeBaseDocument $doc) use ($canManageAdmin) {
                $catLabel = $doc->category instanceof KnowledgeBaseCategory
                    ? $doc->category->label()
                    : __('Company KB');
                $timestamp = $doc->updated_at ?? $doc->created_at;

                return [
                    'title' => $doc->original_name ?: __('Untitled Document'),
                    'category' => $catLabel,
                    'source' => __('Knowledge Base'),
                    'badge_color' => 'indigo',
                    'updated_at' => $timestamp,
                    'date_label' => $timestamp ? $timestamp->setTimezone('Asia/Jakarta')->format('d M Y') : '-',
                    'sort_ts' => $timestamp?->getTimestamp() ?? 0,
                    'url' => $canManageAdmin
                        ? route('knowledge-base.index', ['q' => $doc->original_name])
                        : route('ai.assistant.index'),
                ];
            });

        $products = Product::query()
            ->where('status', '!=', Product::STATUS_REJECTED)
            ->latest('updated_at')
            ->take(4)
            ->get()
            ->map(function (Product $product) use ($canManageAdmin) {
                $catLabel = $product->category
                    ? Product::saleCategoryLabel((string) $product->category)
                    : __('Product');
                $timestamp = $product->updated_at ?? $product->created_at;

                return [
                    'title' => $product->displayName() ?: ($product->name ?: __('Unnamed Product')),
                    'category' => ucfirst($catLabel),
                    'source' => __('Product KB'),
                    'badge_color' => 'amber',
                    'updated_at' => $timestamp,
                    'date_label' => $timestamp ? $timestamp->setTimezone('Asia/Jakarta')->format('d M Y') : '-',
                    'sort_ts' => $timestamp?->getTimestamp() ?? 0,
                    'url' => $canManageAdmin
                        ? route('product-knowledge.show', $product)
                        : route('ai.assistant.index'),
                ];
            });

        return $kbDocs
            ->concat($products)
            ->sortByDesc('sort_ts')
            ->take(4)
            ->values();
    }
}

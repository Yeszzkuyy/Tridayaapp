<?php

namespace App\Http\Controllers;

use App\Models\TechnicalRequest;
use App\Models\TechnicianSchedule;
use App\Models\User;
use App\Services\Project\FieldJobWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * "Tugas Saya" — daftar pekerjaan engineer. Sumbernya dua:
 * 1. Request Tim Engineer dari Sales (technical_requests).
 * 2. Jadwal teknisi manual yang tidak terhubung request (technician_schedules),
 *    supaya jadwal yang dibuat dari menu Jadwal tetap masuk daftar tugas.
 *
 * Super-admin dan Lead Technician melihat semua tugas beserta seluruh aksinya.
 */
class TechnicianTaskController extends Controller
{
    private const PER_PAGE = 15;

    public function index(Request $request)
    {
        $user = $request->user();
        $isSuperAdmin = $user?->hasRole('super-admin') ?? false;
        // Lead Technician ikut boleh melihat & memfilter tugas seluruh
        // engineer (koordinator teknis). Teknisi biasa tetap hanya melihat
        // tugas yang ditugaskan kepadanya.
        $canViewAllTasks = $isSuperAdmin || ($user?->hasRole('lead-technician') ?? false);

        abort_unless($canViewAllTasks || $user?->hasRole('technician'), 403);

        $technicianFilter = null;

        if ($canViewAllTasks) {
            // Koordinator melihat semua tugas. Filter teknisi opsional,
            // termasuk tugas yang belum di-assign.
            $technicianFilter = $request->query('technician_id');
            if (! is_string($technicianFilter)) {
                $technicianFilter = null;
            }
        }

        $statusFilter = $request->get('status');
        $allowedStatuses = array_merge(TechnicalRequest::STATUSES, array_keys(TechnicianSchedule::STATUSES));
        $statusFilter = is_string($statusFilter) && in_array($statusFilter, $allowedStatuses, true)
            ? $statusFilter
            : null;

        $search = trim((string) $request->get('q'));

        $rows = $this->requestRows($user, $canViewAllTasks, $technicianFilter, $statusFilter, $search)
            ->merge($this->scheduleRows($user, $canViewAllTasks, $technicianFilter, $statusFilter, $search))
            ->sortByDesc('sort_at')
            ->values();

        $page = LengthAwarePaginator::resolveCurrentPage();
        $tasks = new LengthAwarePaginator(
            $rows->forPage($page, self::PER_PAGE)->values(),
            $rows->count(),
            self::PER_PAGE,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        $technicians = $canViewAllTasks
            ? User::role('technician')->orderBy('name')->get(['id', 'name'])
            : collect();

        $requestStatuses = TechnicalRequest::STATUSES;
        $scheduleStatuses = collect(TechnicianSchedule::STATUSES)
            ->reject(fn (string $label, string $value) => in_array($value, TechnicalRequest::STATUSES, true))
            ->all();

        return view('teknisi.tasks.index', compact(
            'tasks',
            'canViewAllTasks',
            'technicians',
            'technicianFilter',
            'statusFilter',
            'requestStatuses',
            'scheduleStatuses',
        ));
    }

    /**
     * Baris tugas dari Request Tim Engineer (Sales).
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function requestRows(
        User $user,
        bool $canViewAllTasks,
        ?string $technicianFilter,
        ?string $statusFilter,
        string $search,
    ): Collection {
        $query = TechnicalRequest::with(['lead.customer', 'requester', 'technician', 'supportUsers', 'project'])
            ->where('request_type', '!=', 'estimate');

        if ($canViewAllTasks) {
            $this->applyTechnicianFilter($query, $technicianFilter);
        } else {
            // Engineer utama dan support sama-sama melihat tugasnya.
            $query->where(function ($query) use ($user) {
                $query->where('assigned_technician_id', $user->id)
                    ->orWhereHas('supportUsers', fn ($support) => $support->where('users.id', $user->id));
            });
        }

        if ($statusFilter !== null && in_array($statusFilter, TechnicalRequest::STATUSES, true)) {
            $query->where('status', $statusFilter);
        }

        if ($search !== '') {
            $query->where(function ($query) use ($search) {
                $query->where('title', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%")
                    ->orWhereHas('lead.customer', fn ($customer) => $customer->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('project', fn ($project) => $project->where('project_name', 'like', "%{$search}%"));
            });
        }

        return $query->get()->map(function (TechnicalRequest $task) {
            $customer = $task->lead?->customer;

            return [
                'key' => 'request-'.$task->id,
                'source' => 'request',
                'title' => $task->title,
                'type_label' => TechnicalRequest::typeLabel($task->request_type),
                'customer' => $customer?->name,
                'customer_company' => $customer?->company,
                'project' => $task->project?->project_name,
                'location' => $task->location ?: $customer?->address,
                'date' => $task->target_date,
                'time' => $task->target_time ? substr((string) $task->target_time, 0, 5) : null,
                'status' => $task->status,
                'status_label' => TechnicalRequest::statusLabel($task->status),
                'status_class' => $this->requestStatusClass($task->status),
                'technician' => $task->technician,
                'support_names' => $task->supportUsers->pluck('name')->implode(', '),
                'url' => route('teknisi.tasks.show', $task),
                'sort_at' => $task->created_at,
            ];
        })->toBase();
    }

    /**
     * Baris tugas dari jadwal manual. Jadwal yang lahir dari request tidak
     * diulang di sini karena sudah diwakili request induknya.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function scheduleRows(
        User $user,
        bool $canViewAllTasks,
        ?string $technicianFilter,
        ?string $statusFilter,
        string $search,
    ): Collection {
        $query = TechnicianSchedule::with(['customer', 'project.customer', 'technician', 'technicians'])
            ->whereNull('technical_request_id');

        if ($canViewAllTasks) {
            $this->applyTechnicianFilterToSchedules($query, $technicianFilter);
        } else {
            $query->where(function ($query) use ($user) {
                $query->where('technician_user_id', $user->id)
                    ->orWhereHas('technicians', fn ($technician) => $technician->where('users.id', $user->id));
            });
        }

        if ($statusFilter !== null && array_key_exists($statusFilter, TechnicianSchedule::STATUSES)) {
            $query->where('status', $statusFilter);
        }

        if ($search !== '') {
            $query->where(function ($query) use ($search) {
                $query->where('title', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('project', fn ($project) => $project->where('project_name', 'like', "%{$search}%"))
                    ->orWhereHas('project.customer', fn ($customer) => $customer->where('name', 'like', "%{$search}%"));
            });
        }

        return $query->get()->map(function (TechnicianSchedule $schedule) {
            $customer = $schedule->displayCustomer();
            $date = $schedule->start_at?->setTimezone('Asia/Jakarta');

            return [
                'key' => 'schedule-'.$schedule->id,
                'source' => 'schedule',
                'title' => $schedule->title,
                'type_label' => __('Jadwal'),
                'customer' => $customer?->name,
                'customer_company' => $customer?->company,
                'project' => $schedule->project?->project_name,
                'location' => $schedule->location ?: $customer?->address,
                'date' => $date,
                'time' => $date?->format('H:i'),
                'status' => $schedule->status,
                'status_label' => __(TechnicianSchedule::STATUSES[$schedule->status] ?? $schedule->status),
                'status_class' => $this->scheduleStatusClass($schedule->status),
                'technician' => $schedule->technician,
                'support_names' => $schedule->technicianList()
                    ->filter(fn ($technician) => (int) $technician->id !== (int) $schedule->technician_user_id)
                    ->pluck('name')
                    ->implode(', '),
                'url' => route('teknisi.jadwal', [
                    'date' => $date?->toDateString(),
                    'open' => $schedule->id,
                    'from' => 'tasks',
                ]),
                'sort_at' => $schedule->created_at,
            ];
        })->toBase();
    }

    /**
     * Filter engineer untuk request: id engineer, "unassigned", atau kosong.
     */
    private function applyTechnicianFilter($query, ?string $technicianFilter): void
    {
        if ($technicianFilter === 'unassigned') {
            $query->whereNull('assigned_technician_id');

            return;
        }

        if ($technicianFilter !== null && $technicianFilter !== '' && ctype_digit($technicianFilter)) {
            $query->where(function ($query) use ($technicianFilter) {
                $query->where('assigned_technician_id', (int) $technicianFilter)
                    ->orWhereHas('supportUsers', fn ($support) => $support->where('users.id', (int) $technicianFilter));
            });
        }
    }

    /**
     * Filter engineer untuk jadwal manual. "Unassigned" berarti belum ada
     * engineer utama maupun support.
     */
    private function applyTechnicianFilterToSchedules($query, ?string $technicianFilter): void
    {
        if ($technicianFilter === 'unassigned') {
            $query->whereNull('technician_user_id')
                ->whereDoesntHave('technicians');

            return;
        }

        if ($technicianFilter !== null && $technicianFilter !== '' && ctype_digit($technicianFilter)) {
            $query->where(function ($query) use ($technicianFilter) {
                $query->where('technician_user_id', (int) $technicianFilter)
                    ->orWhereHas('technicians', fn ($technician) => $technician->where('users.id', (int) $technicianFilter));
            });
        }
    }

    private function requestStatusClass(string $status): string
    {
        return match ($status) {
            'waiting_lead' => 'bg-slate-200 text-slate-700 dark:bg-slate-600 dark:text-slate-200',
            'accepted' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
            'rejected' => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
            'assigned' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300',
            'in_progress' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
            'completed' => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
            'cancelled' => 'bg-slate-200 text-slate-500 dark:bg-slate-600 dark:text-slate-300',
            default => 'bg-slate-200 text-slate-700 dark:bg-slate-600 dark:text-slate-200',
        };
    }

    private function scheduleStatusClass(string $status): string
    {
        return match ($status) {
            'scheduled' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
            'on_progress' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
            'completed' => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
            'cancelled' => 'bg-slate-200 text-slate-500 dark:bg-slate-600 dark:text-slate-300',
            default => 'bg-slate-200 text-slate-700 dark:bg-slate-600 dark:text-slate-200',
        };
    }

    public function show(TechnicalRequest $technicalRequest)
    {
        $user = auth()->user();
        $isSuperAdmin = $user?->hasRole('super-admin') ?? false;
        $canViewAllTasks = $isSuperAdmin || ($user?->hasRole('lead-technician') ?? false);

        // Engineer utama dan support hanya boleh membuka tugas yang
        // BENAR-BENAR sedang ditugaskan kepadanya. Kalau tugas dipindahkan
        // ke engineer lain, pemegang lama otomatis kehilangan akses.
        // Koordinator teknis (super-admin/lead-technician) melihat semua.
        abort_unless(
            $user && ($canViewAllTasks || $technicalRequest->isAssignedTo($user)),
            403
        );

        // 'estimate' sudah punya alur Request Harga sendiri, jadi tidak boleh
        // dibuka lewat Tugas Saya.
        abort_if($technicalRequest->request_type === 'estimate', 404);

        $technicalRequest->load(['lead.customer', 'requester', 'technician', 'supportUsers', 'project', 'survey', 'installation', 'schedules']);

        $technicians = $canViewAllTasks
            ? User::role('technician')->orderBy('name')->get(['id', 'name'])
            : collect();
        $fromTasks = true;
        // Engineer utama & support sama-sama boleh mengerjakan tugasnya
        // langsung dari halaman Tugas Saya.
        $readOnly = false;
        $workflow = app(FieldJobWorkflowService::class)->evaluate($technicalRequest);

        return view('sales.technical-requests.show', compact('technicalRequest', 'technicians', 'fromTasks', 'readOnly', 'workflow'));
    }
}

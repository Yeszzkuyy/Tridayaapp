<?php

namespace App\Http\Controllers;

use App\Models\Instalasi;
use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\Survey;
use App\Models\TechnicalRequest;
use App\Models\TechnicianSchedule;
use App\Models\User;
use App\Support\RecentActivityFeed;
use App\Support\SparklineBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TechnicianDashboardController extends Controller
{
    private const ACTIVE_STATUSES = ['Pending', 'Open', 'On Progress'];

    private const STATUS_BAR_COLORS = [
        'Open' => '#3b82f6',
        'On Progress' => '#eab308',
        'Pending' => '#f97316',
        'Hold' => '#dc2626',
        'Done' => '#22c55e',
        'Cancelled' => '#64748b',
        'Warranty' => '#06b6d4',
        'Maintenance' => '#a855f7',
    ];

    public function index()
    {
        $now = Carbon::now('Asia/Jakarta');

        $technicians = User::whereHas('roles', fn ($q) => $q->whereIn('name', ['technician', 'lead-technician', 'prakerin-technician']))
            ->orderBy('name')
            ->get(['id', 'name', 'avatar']);

        $runningProjects = Project::with(['customer', 'status', 'supportUsers'])
            ->whereHas('status', fn ($q) => $q->whereIn('name', self::ACTIVE_STATUSES))
            ->get();

        $doneProjects = Project::whereHas('status', fn ($q) => $q->where('name', 'Done'))->count();

        [$fieldJobs, $fieldJobCounts] = $this->activeFieldJobs($now);

        [$activeTechnicians, $idleTechnicians, $projectsByTechnician] = $this->splitTechniciansByActivity($technicians, $runningProjects, $fieldJobCounts);

        $myFieldJobs = $fieldJobs
            ->filter(fn (array $job) => in_array((int) auth()->id(), $job['technician_ids'], true))
            ->sortBy('date_sort')
            ->values();

        $fieldJobsByTechnician = [];
        foreach ($fieldJobs as $job) {
            foreach ($job['technician_ids'] as $technicianId) {
                $fieldJobsByTechnician[$technicianId][] = $job;
            }
        }
        foreach ($fieldJobsByTechnician as $technicianId => $jobs) {
            usort($jobs, fn (array $a, array $b) => $a['date_sort'] <=> $b['date_sort']);
            $fieldJobsByTechnician[$technicianId] = $jobs;
        }

        // Hitungan per status berubah pelan: cache 5 menit.
        // Simpan angka/teks biasa (aturan keamanan cache melarang simpan bentuk lain).
        // Cache bisa tidak tersedia (permission storage) — fallback query langsung
        // supaya dashboard tetap tampil, bukan 500.
        $countStatuses = fn () => ProjectStatus::withCount('projects')
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($status) => [
                'name' => $status->name,
                'count' => $status->projects_count,
                'color' => ProjectStatus::BADGE_COLORS[$status->name] ?? 'slate',
            ])
            ->all();

        try {
            $statusCounts = collect(Cache::remember('tek:status-counts', 300, $countStatuses));
        } catch (\Throwable $e) {
            Log::warning('Cache status teknisi tidak tersedia, memakai query langsung.', [
                'exception' => $e->getMessage(),
            ]);
            $statusCounts = collect($countStatuses());
        }

        $totalProjects = $statusCounts->sum('count');
        $statusBadgeColors = ProjectStatus::BADGE_COLORS;
        $statusBarColors = self::STATUS_BAR_COLORS;

        $recentProjects = Project::with(['customer', 'status'])
            ->latest('created_at')
            ->take(5)
            ->get();

        $activities = RecentActivityFeed::build(5);

        $kpiSparklines = [
            'technicians' => SparklineBuilder::fromSeries(
                SparklineBuilder::monthlyCounts(User::role('technician'), 'created_at', 6, $now),
                $technicians->count()
            ),
            'active_technicians' => SparklineBuilder::fromSeries(
                $this->activeTechnicianTrend($technicians, $now),
                count($activeTechnicians)
            ),
            'completed_projects' => SparklineBuilder::fromSeries(
                SparklineBuilder::monthlyCounts(
                    Project::whereHas('status', fn ($q) => $q->where('name', 'Done')),
                    'updated_at',
                    6,
                    $now
                ),
                $doneProjects
            ),
            'ongoing_projects' => SparklineBuilder::fromSeries(
                SparklineBuilder::monthlyCounts(
                    Project::whereHas('status', fn ($q) => $q->whereIn('name', self::ACTIVE_STATUSES)),
                    'created_at',
                    6,
                    $now
                ),
                $runningProjects->count()
            ),
        ];

        return view('teknisi.dashboard', compact(
            'technicians',
            'runningProjects',
            'doneProjects',
            'activeTechnicians',
            'idleTechnicians',
            'projectsByTechnician',
            'fieldJobCounts',
            'fieldJobsByTechnician',
            'myFieldJobs',
            'statusCounts',
            'totalProjects',
            'statusBadgeColors',
            'statusBarColors',
            'recentProjects',
            'activities',
            'kpiSparklines',
            'now',
        ));
    }

    /**
     * Kumpulkan pekerjaan lapangan aktif (request, survey, instalasi) per engineer.
     *
     * @return array{0: Collection<int, array<string, mixed>>, 1: array<int, int>}
     */
    private function activeFieldJobs(Carbon $now): array
    {
        $jobs = collect();

        // Request Tim Engineer. Jadwal otomatis yang lahir dari request
        // sengaja tidak diambil lagi (lihat query jadwal) supaya satu
        // penugasan tidak dihitung dua kali.
        TechnicalRequest::with(['lead.customer', 'project', 'supportUsers'])
            ->whereNotIn('request_type', ['survey', 'installation'])
            ->whereIn('status', ['assigned', 'in_progress'])
            ->whereNotNull('assigned_technician_id')
            ->latest('target_date')
            ->get()
            ->each(function (TechnicalRequest $request) use (&$jobs) {
                $jobs->push($this->fieldJob(
                    type: 'request',
                    typeLabel: TechnicalRequest::typeLabel($request->request_type),
                    title: $request->title,
                    customer: $request->lead?->customer?->name,
                    project: $request->project?->project_name,
                    status: TechnicalRequest::statusLabel($request->status),
                    date: $request->target_date,
                    technicianIds: $request->assignedEngineerIds(),
                    url: route('sales.technical-requests.show', $request),
                ));
            });

        // Survey/instalasi adalah pelaksanaan request bertipe sama. Parent
        // request-nya tidak masuk daftar di atas, jadi tetap satu aktivitas.
        Survey::with(['customer', 'project', 'technicalRequest.supportUsers'])
            ->whereIn('status', ['scheduled', 'on_survey'])
            ->whereNotNull('technician_user_id')
            ->latest('survey_date')
            ->get()
            ->each(function (Survey $survey) use (&$jobs) {
                $date = $survey->survey_date ?? $survey->created_at;
                $supportIds = $survey->technicalRequest?->supportUsers->pluck('id')->all() ?? [];

                $jobs->push($this->fieldJob(
                    type: 'survey',
                    typeLabel: __('Survey'),
                    title: $survey->title ?: __('Survey #:id', ['id' => $survey->id]),
                    customer: $survey->customer?->name ?? $survey->project?->customer?->name,
                    project: $survey->project?->project_name,
                    status: ucfirst(str_replace('_', ' ', (string) $survey->status)),
                    date: $date,
                    technicianIds: array_values(array_unique(array_merge([(int) $survey->technician_user_id], $supportIds))),
                    url: route('teknisi.surveys.show', $survey),
                ));
            });

        Instalasi::with(['customer', 'project', 'technicalRequest.supportUsers'])
            ->whereIn('status', ['scheduled', 'on_progress', 'waiting'])
            ->whereNotNull('technician_user_id')
            ->latest('schedule_date')
            ->get()
            ->each(function (Instalasi $instalasi) use (&$jobs) {
                $date = $instalasi->schedule_date ?? $instalasi->created_at;
                $supportIds = $instalasi->technicalRequest?->supportUsers->pluck('id')->all() ?? [];

                $jobs->push($this->fieldJob(
                    type: 'installation',
                    typeLabel: __('Installation'),
                    title: $instalasi->technicalRequest?->title
                        ?? $instalasi->project?->project_name
                        ?? __('Installation #:id', ['id' => $instalasi->id]),
                    customer: $instalasi->customer?->name ?? $instalasi->project?->customer?->name,
                    project: $instalasi->project?->project_name,
                    status: ucfirst(str_replace('_', ' ', (string) $instalasi->status)),
                    date: $date,
                    technicianIds: array_values(array_unique(array_merge([(int) $instalasi->technician_user_id], $supportIds))),
                    url: route('teknisi.instalasis.show', $instalasi),
                ));
            });

        // Jadwal teknisi manual (dibuat dari menu Jadwal Teknisi, bukan
        // turunan Request Tim Engineer). Jadwal yang sudah lewat atau
        // selesai/dibatalkan tidak dianggap aktivitas berjalan.
        TechnicianSchedule::with(['customer', 'project.customer', 'technicians', 'technician'])
            ->whereNull('technical_request_id')
            ->whereIn('status', ['scheduled', 'on_progress'])
            ->where('end_at', '>=', $now)
            ->orderBy('start_at')
            ->get()
            ->each(function (TechnicianSchedule $schedule) use (&$jobs) {
                $technicianIds = $schedule->technicianList()
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values()
                    ->all();

                if ($technicianIds === []) {
                    return;
                }

                $jobs->push($this->fieldJob(
                    type: 'schedule',
                    typeLabel: __('Jadwal'),
                    title: $schedule->title,
                    customer: $schedule->displayCustomer()?->name,
                    project: $schedule->project?->project_name,
                    status: TechnicianSchedule::STATUSES[$schedule->status] ?? ucfirst(str_replace('_', ' ', $schedule->status)),
                    date: $schedule->start_at,
                    technicianIds: $technicianIds,
                    url: route('teknisi.jadwal', [
                        'date' => $schedule->start_at->setTimezone('Asia/Jakarta')->toDateString(),
                        'open' => $schedule->id,
                    ]),
                ));
            });

        $counts = [];
        foreach ($jobs as $job) {
            foreach ($job['technician_ids'] as $technicianId) {
                $counts[$technicianId] = ($counts[$technicianId] ?? 0) + 1;
            }
        }

        return [$jobs->values(), $counts];
    }

    /**
     * Satu baris pekerjaan lapangan dengan daftar engineer yang bertugas.
     *
     * @param  array<int, int>  $technicianIds
     * @return array<string, mixed>
     */
    private function fieldJob(
        string $type,
        string $typeLabel,
        ?string $title,
        ?string $customer,
        ?string $project,
        string $status,
        mixed $date,
        array $technicianIds,
        string $url
    ): array {
        return [
            'type' => $type,
            'type_label' => $typeLabel,
            'title' => $title ?: '',
            'customer' => $customer,
            'project' => $project,
            'status' => $status,
            'date' => $date,
            'date_sort' => $date?->timestamp ?? PHP_INT_MAX,
            'technician_ids' => $technicianIds,
            'url' => $url,
        ];
    }

    /**
     * Tren jumlah engineer aktif per bulan dari sumber penugasan yang sama
     * dengan kartu Active Technicians (project, request, jadwal, survey,
     * instalasi). Satu orang dihitung sekali per bulan.
     *
     * @param  Collection<int, User>  $technicians
     * @return array<int, int>
     */
    private function activeTechnicianTrend(Collection $technicians, Carbon $now): array
    {
        $months = 6;
        $start = $now->copy()->subMonths($months - 1)->startOfMonth();

        $buckets = [];
        for ($i = 0; $i < $months; $i++) {
            $buckets[$start->copy()->addMonths($i)->format('Y-m')] = [];
        }

        $knownIds = $technicians->pluck('id')->map(fn ($id) => (int) $id)->all();

        $add = function (mixed $date, array $ids) use (&$buckets, $knownIds): void {
            if (! $date) {
                return;
            }

            $key = Carbon::parse($date)->format('Y-m');
            if (! array_key_exists($key, $buckets)) {
                return;
            }

            foreach (array_intersect(array_map('intval', $ids), $knownIds) as $id) {
                $buckets[$key][$id] = true;
            }
        };

        Project::with('supportUsers')
            ->whereHas('status', fn ($q) => $q->whereIn('name', self::ACTIVE_STATUSES))
            ->where('updated_at', '>=', $start)
            ->get()
            ->each(fn (Project $project) => $add(
                $project->updated_at,
                $this->projectTechnicianIds($project, $technicians)
            ));

        TechnicalRequest::with('supportUsers')
            ->whereNotIn('request_type', ['survey', 'installation'])
            ->whereIn('status', ['assigned', 'in_progress'])
            ->whereNotNull('assigned_technician_id')
            ->whereNotNull('target_date')
            ->where('target_date', '>=', $start->toDateString())
            ->get(['id', 'assigned_technician_id', 'target_date'])
            ->each(fn (TechnicalRequest $request) => $add(
                $request->target_date,
                $request->assignedEngineerIds()
            ));

        Survey::with('technicalRequest.supportUsers')
            ->whereIn('status', ['scheduled', 'on_survey'])
            ->whereNotNull('technician_user_id')
            ->whereNotNull('survey_date')
            ->where('survey_date', '>=', $start->toDateString())
            ->get(['id', 'technician_user_id', 'survey_date', 'technical_request_id'])
            ->each(fn (Survey $survey) => $add(
                $survey->survey_date,
                array_values(array_unique(array_merge(
                    [(int) $survey->technician_user_id],
                    $survey->technicalRequest?->supportUsers->pluck('id')->all() ?? []
                )))
            ));

        Instalasi::with('technicalRequest.supportUsers')
            ->whereIn('status', ['scheduled', 'on_progress', 'waiting'])
            ->whereNotNull('technician_user_id')
            ->whereNotNull('schedule_date')
            ->where('schedule_date', '>=', $start->toDateString())
            ->get(['id', 'technician_user_id', 'schedule_date', 'technical_request_id'])
            ->each(fn (Instalasi $instalasi) => $add(
                $instalasi->schedule_date,
                array_values(array_unique(array_merge(
                    [(int) $instalasi->technician_user_id],
                    $instalasi->technicalRequest?->supportUsers->pluck('id')->all() ?? []
                )))
            ));

        TechnicianSchedule::with(['technicians', 'technician'])
            ->whereNull('technical_request_id')
            ->whereIn('status', ['scheduled', 'on_progress'])
            ->where('start_at', '>=', $start)
            ->get()
            ->each(fn (TechnicianSchedule $schedule) => $add(
                $schedule->start_at,
                $schedule->technicianList()->pluck('id')->all()
            ));

        return array_values(array_map('count', $buckets));
    }

    private function splitTechniciansByActivity($technicians, $runningProjects, array $fieldJobCounts = []): array
    {
        $active = collect();
        $idle = collect();
        $projects = [];

        $projectsByTechnician = [];
        foreach ($runningProjects as $project) {
            foreach ($this->projectTechnicianIds($project, $technicians) as $technicianId) {
                $projectsByTechnician[$technicianId][] = $project;
            }
        }

        foreach ($technicians as $technician) {
            $matched = collect($projectsByTechnician[$technician->id] ?? []);

            $projects[$technician->id] = $matched;

            if ($matched->isNotEmpty() || ($fieldJobCounts[$technician->id] ?? 0) > 0) {
                $active->push($technician);
            } else {
                $idle->push($technician);
            }
        }

        return [$active, $idle, $projects];
    }

    /**
     * Engineer yang terhubung ke project: kolom akun (PIC/support) dulu,
     * lalu cadangan nama untuk data lama yang belum punya akun engineer.
     *
     * @return array<int, int>
     */
    private function projectTechnicianIds(Project $project, Collection $technicians): array
    {
        $ids = [];

        foreach ($technicians as $technician) {
            if ((int) $project->teknisi_user_id === (int) $technician->id
                || $project->supportUsers->contains('id', $technician->id)) {
                $ids[] = (int) $technician->id;

                continue;
            }

            $name = mb_strtolower(trim((string) $technician->name));
            if ($name === '') {
                continue;
            }

            if (mb_strtolower(trim((string) $project->pic_engineer)) === $name
                || str_contains(mb_strtolower((string) $project->support_technicians), $name)) {
                $ids[] = (int) $technician->id;
            }
        }

        return $ids;
    }
}

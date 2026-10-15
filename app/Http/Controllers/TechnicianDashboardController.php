<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\ProjectStatus;
use App\Models\User;
use Illuminate\Support\Carbon;

class TechnicianDashboardController extends Controller
{
    private const ACTIVE_STATUSES = ['Open', 'On Progress'];

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

        $technicians = User::role('technician')->orderBy('name')->get(['id', 'name', 'avatar']);

        $runningProjects = Project::with(['customer', 'status'])
            ->whereHas('status', fn ($q) => $q->whereIn('name', self::ACTIVE_STATUSES))
            ->get();

        $doneProjects = Project::whereHas('status', fn ($q) => $q->where('name', 'Done'))->count();

        [$activeTechnicians, $idleTechnicians, $projectsByTechnician] = $this->splitTechniciansByActivity($technicians, $runningProjects);

        // Hitungan per status berubah pelan: cache 5 menit.
        // Simpan angka/teks biasa (aturan keamanan cache melarang simpan bentuk lain).
        $statusCounts = collect(\Illuminate\Support\Facades\Cache::remember(
            'tek:status-counts',
            300,
            fn () => ProjectStatus::withCount('projects')
                ->orderBy('sort_order')
                ->get()
                ->map(fn ($status) => [
                    'name' => $status->name,
                    'count' => $status->projects_count,
                    'color' => ProjectStatus::BADGE_COLORS[$status->name] ?? 'slate',
                ])
                ->all()
        ));

        $totalProjects = $statusCounts->sum('count');
        $statusBadgeColors = ProjectStatus::BADGE_COLORS;
        $statusBarColors = self::STATUS_BAR_COLORS;

        $recentProjects = Project::with(['customer', 'status'])
            ->latest('created_at')
            ->take(5)
            ->get();

        $activities = ProjectActivity::with(['project', 'user'])
            ->latest('activity_date')
            ->take(5)
            ->get();

        return view('teknisi.dashboard', compact(
            'technicians',
            'runningProjects',
            'doneProjects',
            'activeTechnicians',
            'idleTechnicians',
            'projectsByTechnician',
            'statusCounts',
            'totalProjects',
            'statusBadgeColors',
            'statusBarColors',
            'recentProjects',
            'activities',
            'now',
        ));
    }

    private function splitTechniciansByActivity($technicians, $runningProjects): array
    {
        // ponytail: name-matching against pic_engineer/support_technicians text columns; switch to a relation if matching drifts
        $active = collect();
        $idle = collect();
        $projects = [];

        foreach ($technicians as $technician) {
            $name = strtolower($technician->name);
            $matched = $runningProjects->filter(function ($p) use ($name) {
                return strtolower((string) $p->pic_engineer) === $name
                    || str_contains(strtolower((string) $p->support_technicians), $name);
            })->values();

            if ($matched->isNotEmpty()) {
                $active->push($technician);
                $projects[$technician->id] = $matched;
            } else {
                $idle->push($technician);
            }
        }

        return [$active, $idle, $projects];
    }
}
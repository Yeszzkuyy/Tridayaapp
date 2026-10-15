<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\ProjectDocument;
use App\Enums\ProjectStatus;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    public function index()
    {
        // Hitungan berubah jarang; cache 2 menit (stale dapat diterima untuk angka ringkas).
        $counts = Cache::remember('dash:counts', 120, fn () => [
            'customers' => Customer::count(),
            'documents' => ProjectDocument::count(),
            'users' => User::count(),
            'projects' => Project::count(),
            'active' => Project::whereHas('status', fn ($q) => $q->whereIn('name', [
                ProjectStatus::Open->value,
                ProjectStatus::OnProgress->value,
            ]))->count(),
        ]);

        $customerCount = $counts['customers'];
        $documentCount = $counts['documents'];
        $userCount = $counts['users'];
        $totalProjects = $counts['projects'];
        $activeProjects = $counts['active'];

        // Aktivitas Terbaru
        $activities = ProjectActivity::with(['project', 'user'])
            ->latest('activity_date')
            ->take(5)
            ->get();

        return view('dashboard.index', compact(
            'customerCount',
            'documentCount',
            'userCount',
            'totalProjects',
            'activeProjects',
            'activities'
        ));
    }
}
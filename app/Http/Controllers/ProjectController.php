<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\Customer;
use App\Models\AccountManager;
use App\Models\WorkType;
use App\Models\User;
use Illuminate\Http\Request;
use App\Services\Project\ProjectService;
use App\Enums\ProjectStatus as ProjectStatusEnum;

class ProjectController extends Controller
{
    protected $projectService;

    public function __construct(ProjectService $projectService)
    {
        $this->projectService = $projectService;
    }

    public function index()
    {
        $this->authorize('viewAny', Project::class);

        // Paginasi di SQL supaya perubahan status tidak membuat project "menghilang" dari daftar
        $projects = Project::with(['customer', 'workType', 'status'])
            ->latest()
            ->paginate(15)
            ->withQueryString();

        // Total semua project
        $totalProjects = Project::count();

        // Total project aktif (hitung di SQL, bukan di koleksi)
        $activeProjects = Project::whereHas('status', fn ($q) => $q->whereIn('name', [
            ProjectStatusEnum::Open->value,
            ProjectStatusEnum::OnProgress->value,
        ]))->count();

        $statusBadgeColors = ProjectStatus::BADGE_COLORS;

        return view('projects.index', compact('projects', 'totalProjects', 'activeProjects', 'statusBadgeColors'));
    }

    public function create(Request $request)
    {
        $this->authorize('create', Project::class);

        $customerId = $request->query('customer_id');
        
        if (!$customerId) {
            return redirect()->route('customers.index')
                ->with('error', 'Silakan pilih customer terlebih dahulu.');
        }
        
        $customer = Customer::findOrFail($customerId);
        $workTypes = WorkType::all();
        $accountManagers = AccountManager::all();
        $statuses = ProjectStatus::orderBy('sort_order')->get();
        $technicians = User::role('technician')->orderBy('name')->get(['id', 'name']);

        return view('projects.create', compact('customer', 'workTypes', 'accountManagers', 'statuses', 'technicians'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', Project::class);

        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'name' => 'required|string|max:255',
            'work_type_id' => 'required|exists:work_types,id',
            'account_manager_id' => 'nullable|exists:account_managers,id',
            'pic_engineer' => 'required|string|max:255',
            'support_technicians' => 'nullable|array',
            'support_technicians.*' => 'string|max:255',
            'project_status_id' => 'nullable|exists:project_statuses,id',
            'description' => 'nullable|string',
        ]);

        $validated['support_technicians'] = implode(', ', array_filter($validated['support_technicians'] ?? []));

        $project = $this->projectService->create($validated);

        return redirect()
            ->route('customers.show', $project->customer_id)
            ->with('success', 'Project berhasil ditambahkan.');
    }

    public function show(Project $project)
    {
        $this->authorize('view', $project);

        $project->load([
            'customer', 
            'workType', 
            'accountManager', 
            'tasks', 
            'documents', 
            'supports', 
            'activities'
        ]);

        return view('projects.show', compact('project'));
    }

    public function edit(Project $project)
    {
        $this->authorize('update', $project);

        $customers = Customer::all();
        $workTypes = WorkType::all();
        $accountManagers = AccountManager::all();
        $statuses = ProjectStatus::orderBy('sort_order')->get();
        $technicians = User::role('technician')->orderBy('name')->get(['id', 'name']);

        return view('projects.edit', compact('project', 'customers', 'workTypes', 'accountManagers', 'statuses', 'technicians'));
    }

    public function update(Request $request, Project $project)
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'project_name' => 'required|string|max:255',
            'work_type_id' => 'required|exists:work_types,id',
            'account_manager_id' => 'nullable|exists:account_managers,id',
            'pic_engineer' => 'required|string|max:255',
            'support_technicians' => 'nullable|array',
            'support_technicians.*' => 'string|max:255',
            'project_status_id' => 'nullable|exists:project_statuses,id',
            'progress' => 'nullable|integer|min:0|max:100',
            'description' => 'nullable|string',
        ]);

        $validated['support_technicians'] = implode(', ', array_filter($validated['support_technicians'] ?? []));

        $project->update($validated);
    
        return redirect()
            ->route('projects.show', $project)
            ->with('success', 'Project berhasil diupdate.');
    }

    public function destroy(Project $project)
    {
        $this->authorize('delete', $project);

        $project->delete();

        return redirect()
            ->route('projects.index')
            ->with('success', 'Project berhasil dihapus.');
    }
}
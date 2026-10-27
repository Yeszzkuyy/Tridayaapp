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

    public function index(Request $request)
    {
        $this->authorize('viewAny', Project::class);

        $user = auth()->user();
        $isTech = $user->can('manage-technician');
        $isManager = $user->can('manage-sales-leads');
        $canSeeAll = $isTech || $isManager;

        // Tab: all (teknisi/manager), assigned (PIC teknisi), sales (sales PIC).
        // Sales biasa dipaksa ke tab miliknya sendiri.
        $tab = (string) $request->get('tab', 'all');
        if (! $canSeeAll) {
            $tab = 'sales';
        } elseif (! in_array($tab, ['all', 'assigned', 'sales'], true)) {
            $tab = 'all';
        }

        $query = Project::with(['customer', 'workType', 'status'])->latest();

        if ($tab === 'assigned') {
            $query->where('teknisi_user_id', $user->id);
        } elseif ($tab === 'sales') {
            $query->where('sales_user_id', $user->id);
        }

        $statusFilter = $request->integer('status') ?: null;
        if ($statusFilter) {
            $query->where('project_status_id', $statusFilter);
        }

        // Paginasi di SQL supaya perubahan status tidak membuat project "menghilang" dari daftar
        $projects = $query->paginate(15)->withQueryString();

        // Total semua project
        $totalProjects = Project::count();

        // Total project aktif (hitung di SQL, bukan di koleksi)
        $activeProjects = Project::whereHas('status', fn ($q) => $q->whereIn('name', [
            ProjectStatusEnum::Open->value,
            ProjectStatusEnum::OnProgress->value,
        ]))->count();

        $statusBadgeColors = ProjectStatus::BADGE_COLORS;
        $statuses = ProjectStatus::orderBy('sort_order')->get();

        return view('projects.index', compact(
            'projects', 'totalProjects', 'activeProjects', 'statusBadgeColors',
            'statuses', 'statusFilter', 'tab', 'isTech', 'isManager'
        ));
    }

    public function create(Request $request)
    {
        $customerId = $request->query('customer_id');

        if (!$customerId) {
            return redirect()->route('customers.index')
                ->with('error', __('Please select a customer first.'));
        }

        $customer = Customer::findOrFail($customerId);

        // Project manual khusus teknisi (sales lewat Convert to Project).
        abort_unless(auth()->user()->can('create', Project::class), 403);
        $workTypes = WorkType::all();
        $accountManagers = AccountManager::all();
        $statuses = ProjectStatus::orderBy('sort_order')->get();
        $technicians = User::role('technician')->orderBy('name')->get(['id', 'name']);

        return view('projects.create', compact('customer', 'workTypes', 'accountManagers', 'statuses', 'technicians'));
    }

    public function store(Request $request)
    {
        $customer = Customer::findOrFail(
            $request->validate(['customer_id' => 'required|exists:customers,id'])['customer_id']
        );

        abort_unless(auth()->user()->can('create', Project::class), 403);

        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'name' => 'required|string|max:255',
            'work_type_id' => 'required|exists:work_types,id',
            'account_manager_id' => 'nullable|exists:account_managers,id',
            'pic_engineer' => 'nullable|string|max:255',
            'support_technicians' => 'nullable|array',
            'support_technicians.*' => 'string|max:255',
            'project_status_id' => 'nullable|exists:project_statuses,id',
            'description' => 'nullable|string',
        ]);

        $validated['support_technicians'] = implode(', ', array_filter($validated['support_technicians'] ?? []));

        $project = $this->projectService->create($validated);

        return redirect()
            ->route('customers.show', $project->customer_id)
            ->with('success', __('Project added successfully.'));
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
            'pic_engineer' => 'nullable|string|max:255',
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
            ->with('success', __('Project updated successfully.'));
    }

    public function destroy(Project $project)
    {
        $this->authorize('delete', $project);

        $project->delete();

        return redirect()
            ->route('projects.index')
            ->with('success', __('Project deleted successfully.'));
    }
}
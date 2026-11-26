<?php

namespace App\Http\Controllers;

use App\Models\ProjectStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

class ProjectStatusController extends Controller
{
    public function index()
    {
        $statuses = ProjectStatus::latest()->get();
        return view('admin-panel.project-statuses.index', compact('statuses'));
    }

    public function create()
    {
        return view('admin-panel.project-statuses.create');
    }

    public function store(Request $request)
    {
        Gate::authorize('manage', ProjectStatus::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'nullable|string|max:50',
            'is_default' => 'nullable|boolean',
        ]);

        ProjectStatus::create($validated);
        Cache::forget('master:project-statuses');
        Cache::forget('tek:status-counts');

        return redirect()
            ->route('admin-panel.project-statuses.index')
            ->with('success', __('Project Status added successfully.'));
    }

    public function edit(ProjectStatus $projectStatus)
    {
        return view('admin-panel.project-statuses.edit', compact('projectStatus'));
    }

    public function update(Request $request, ProjectStatus $projectStatus)
    {
        Gate::authorize('manage', ProjectStatus::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'nullable|string|max:50',
            'is_default' => 'nullable|boolean',
        ]);

        if ($projectStatus->isSystem() && $validated['name'] !== $projectStatus->name) {
            return back()
                ->withInput()
                ->withErrors(['name' => __('System status name cannot be changed.')]);
        }

        $validated['name'] = $projectStatus->isSystem()
            ? $projectStatus->name
            : $validated['name'];

        $projectStatus->update($validated);
        Cache::forget('master:project-statuses');
        Cache::forget('tek:status-counts');

        return redirect()
            ->route('admin-panel.project-statuses.index')
            ->with('success', __('Project Status updated successfully.'));
    }

    public function destroy(ProjectStatus $projectStatus)
    {
        Gate::authorize('manage', ProjectStatus::class);

        if ($projectStatus->isSystem()) {
            return redirect()
                ->route('admin-panel.project-statuses.index')
                ->with('error', __('System status cannot be deleted.'));
        }

        $projectStatus->delete();
        Cache::forget('master:project-statuses');
        Cache::forget('tek:status-counts');
        return redirect()
            ->route('admin-panel.project-statuses.index')
            ->with('success', __('Project Status deleted successfully.'));
    }
}
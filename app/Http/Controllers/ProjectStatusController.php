<?php

namespace App\Http\Controllers;

use App\Models\ProjectStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

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
            ->with('success', __('Project Status berhasil ditambahkan.'));
    }

    public function edit(ProjectStatus $projectStatus)
    {
        return view('admin-panel.project-statuses.edit', compact('projectStatus'));
    }

    public function update(Request $request, ProjectStatus $projectStatus)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'nullable|string|max:50',
            'is_default' => 'nullable|boolean',
        ]);

        $projectStatus->update($validated);
        Cache::forget('master:project-statuses');
        Cache::forget('tek:status-counts');

        return redirect()
            ->route('admin-panel.project-statuses.index')
            ->with('success', __('Project Status berhasil diupdate.'));
    }

    public function destroy(ProjectStatus $projectStatus)
    {
        $projectStatus->delete();
        Cache::forget('master:project-statuses');
        Cache::forget('tek:status-counts');
        return redirect()
            ->route('admin-panel.project-statuses.index')
            ->with('success', __('Project Status berhasil dihapus.'));
    }
}
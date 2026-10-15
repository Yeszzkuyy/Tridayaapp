<?php

namespace App\Http\Controllers;

use App\Models\SizingProject;
use App\Models\Project;
use Illuminate\Http\Request;

class SizingProjectController extends Controller
{
    public function index(Request $request)
    {
        $query = SizingProject::with('project.customer')->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        $sizings = $query->paginate(15)->withQueryString();
        $projects = Project::with('customer:id,name')->orderBy('project_name')->limit(200)->get(['id', 'project_name', 'customer_id']);

        return view('teknisi.sizing-projects.index', compact('sizings', 'projects'));
    }

    public function create(Request $request)
    {
        $projects = Project::with('customer:id,name')->orderBy('project_name')->limit(200)->get(['id', 'project_name', 'customer_id']);
        $selectedProject = $request->query('project_id');

        return view('teknisi.sizing-projects.create', compact('projects', 'selectedProject'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'sales_pic' => 'nullable|string|max:255',
            'customer_needs' => 'nullable|string',
            'recommendation' => 'nullable|string',
            'specifications' => 'nullable|string',
            'quantity' => 'nullable|integer|min:0',
            'topology' => 'nullable|string',
            'technical_notes' => 'nullable|string',
            'status' => 'required|in:draft,in_progress,waiting_approval,completed',
        ]);

        SizingProject::create($validated);

        return redirect()->route('teknisi.sizing-projects.index')
            ->with('success', 'Sizing Project berhasil ditambahkan.');
    }

    public function show(SizingProject $sizingProject)
    {
        $sizingProject->load('project.customer');

        return view('teknisi.sizing-projects.show', compact('sizingProject'));
    }

    public function edit(SizingProject $sizingProject)
    {
        $projects = Project::with('customer:id,name')->orderBy('project_name')->limit(200)->get(['id', 'project_name', 'customer_id']);

        return view('teknisi.sizing-projects.edit', compact('sizingProject', 'projects'));
    }

    public function update(Request $request, SizingProject $sizingProject)
    {
        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'sales_pic' => 'nullable|string|max:255',
            'customer_needs' => 'nullable|string',
            'recommendation' => 'nullable|string',
            'specifications' => 'nullable|string',
            'quantity' => 'nullable|integer|min:0',
            'topology' => 'nullable|string',
            'technical_notes' => 'nullable|string',
            'status' => 'required|in:draft,in_progress,waiting_approval,completed',
        ]);

        $sizingProject->update($validated);

        return redirect()->route('teknisi.sizing-projects.show', $sizingProject)
            ->with('success', 'Sizing Project berhasil diupdate.');
    }

    public function destroy(SizingProject $sizingProject)
    {
        $sizingProject->delete();

        return redirect()->route('teknisi.sizing-projects.index')
            ->with('success', 'Sizing Project berhasil dihapus.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Instalasi;
use App\Models\Project;
use Illuminate\Http\Request;

class InstalasiController extends Controller
{
    public function index(Request $request)
    {
        $query = Instalasi::with('project.customer')->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        $instalasis = $query->paginate(15)->withQueryString();
        $projects = Project::with('customer:id,name')->orderBy('project_name')->limit(200)->get(['id', 'project_name', 'customer_id']);

        return view('teknisi.instalasis.index', compact('instalasis', 'projects'));
    }

    public function create(Request $request)
    {
        $projects = Project::with('customer:id,name')->orderBy('project_name')->limit(200)->get(['id', 'project_name', 'customer_id']);
        $selectedProject = $request->query('project_id');

        return view('teknisi.instalasis.create', compact('projects', 'selectedProject'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'location' => 'nullable|string|max:255',
            'technician_pic' => 'nullable|string|max:255',
            'schedule_date' => 'nullable|date',
            'job_status' => 'nullable|string|max:255',
            'installation_report' => 'nullable|string',
            'notes' => 'nullable|string',
            'status' => 'required|in:scheduled,on_progress,waiting,completed,cancelled',
        ]);

        Instalasi::create($validated);

        return redirect()->route('teknisi.instalasis.index')
            ->with('success', 'Instalasi berhasil ditambahkan.');
    }

    public function show(Instalasi $instalasi)
    {
        $instalasi->load('project.customer');

        return view('teknisi.instalasis.show', compact('instalasi'));
    }

    public function edit(Instalasi $instalasi)
    {
        $projects = Project::with('customer:id,name')->orderBy('project_name')->limit(200)->get(['id', 'project_name', 'customer_id']);

        return view('teknisi.instalasis.edit', compact('instalasi', 'projects'));
    }

    public function update(Request $request, Instalasi $instalasi)
    {
        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'location' => 'nullable|string|max:255',
            'technician_pic' => 'nullable|string|max:255',
            'schedule_date' => 'nullable|date',
            'job_status' => 'nullable|string|max:255',
            'installation_report' => 'nullable|string',
            'notes' => 'nullable|string',
            'status' => 'required|in:scheduled,on_progress,waiting,completed,cancelled',
        ]);

        $instalasi->update($validated);

        return redirect()->route('teknisi.instalasis.show', $instalasi)
            ->with('success', 'Instalasi berhasil diupdate.');
    }

    public function destroy(Instalasi $instalasi)
    {
        $instalasi->delete();

        return redirect()->route('teknisi.instalasis.index')
            ->with('success', 'Instalasi berhasil dihapus.');
    }
}

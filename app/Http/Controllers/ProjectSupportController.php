<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectSupport;
use App\Models\User;
use Illuminate\Http\Request;
use App\Models\ProjectActivity;

class ProjectSupportController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Project $project)
    {
        $this->authorize('update', $project);

        $engineers = User::where('role', 'technician')->get();

        return view(
            'project_supports.create',
            compact(
                'project',
                'engineers'
            )
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(
        Request $request,
        Project $project
    )
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'user_id' => 'required',
        ]);

        $project->supports()->create([
            'user_id' => $validated['user_id'],
        ]);

        $engineer = User::findOrFail(
            $validated['user_id']
        );

        ProjectActivity::create([
            'project_id'    => $project->id,
            'user_id'       => auth()->id(),
            'activity_date' => now(),
            'title'         => 'Tambah Support Engineer',
            'description'   => $engineer->name . ' ditambahkan sebagai support engineer',
        ]);

        return redirect()
            ->route('projects.show', $project)
            ->with(
                'success',
                'Support engineer berhasil ditambahkan'
            );
    }

    /**
     * Display the specified resource.
     */
    public function show(ProjectSupport $projectSupport)
    {
        $projectSupport->load(['project', 'engineer']);

        return view(
            'project_supports.show',
            compact('projectSupport')
        );
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ProjectSupport $projectSupport)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ProjectSupport $projectSupport)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ProjectSupport $projectSupport)
    {
        $this->authorize('update', $projectSupport->project);

        $project = $projectSupport->project;
    
        ProjectActivity::create([
            'project_id' => $project->id,
            'user_id' => auth()->id(),
            'activity_date' => now(),
            'title' => 'Support Engineer Dihapus',
            'description' => $projectSupport->engineer->name . ' dihapus dari support engineer',
        ]);

        $projectSupport->delete();
    
        return redirect()
            ->route('projects.show', $project)
            ->with('success', __('Support engineer deleted successfully'));
    }
}

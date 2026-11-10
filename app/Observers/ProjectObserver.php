<?php

namespace App\Observers;

use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\ProjectStatus;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class ProjectObserver
{
    public function creating(Project $project): void
    {
        // Langkah "Request" selalu selesai sejak project dibuat. Diisi saat
        // creating (bukan created) agar tidak memicu event updated palsu.
        if ($project->workflow_state === null) {
            $project->workflow_state = ['request'];
        }

        if ($project->progress === null) {
            $project->progress = 17;
        }
    }

    public function created(Project $project): void
    {
        $userId = Auth::id() ?? User::first()?->id;
        if ($userId) {
            ProjectActivity::create([
                'project_id' => $project->id,
                'user_id' => $userId,
                'activity_date' => now(),
                'title' => __('Projects Created'),
                'description' => __('New project created successfully'),
            ]);
        }

        // Sinkronisasi status customer: punya project = sudah deal/berjalan.
        if ($project->customer && $project->customer->status === 'lead') {
            $project->customer->update(['status' => 'deal']);
        }
    }

    public function updated(Project $project): void
    {
        if (! $project->wasChanged('project_status_id')) {
            return;
        }

        $oldStatus = ProjectStatus::find($project->getOriginal('project_status_id'))?->name;

        ProjectActivity::create([
            'project_id' => $project->id,
            'user_id' => Auth::id(),
            'activity_date' => now(),
            'title' => __('Status Changed'),
            'description' => __('Status project diubah dari :old menjadi :new', ['old' => $oldStatus, 'new' => $project->status?->name]),
        ]);
    }

    public function deleted(Project $project): void
    {
        if ($project->isForceDeleting()) {
            return; // force delete tidak boleh di resurrect oleh saveQuietly
        }
        $project->forceFill(['deleted_by' => auth()->id()])->saveQuietly();
        $project->documents()->get()->each(fn ($document) => $document->delete());
        $project->tasks()->get()->each(fn ($task) => $task->delete());
        $project->supports()->get()->each(fn ($support) => $support->delete());
    }

    public function restored(Project $project): void
    {
        // ✅ Ganti onlyTrashed → withTrashed
        $project->documents()->withTrashed()->get()->each(fn ($document) => $document->restore());
        $project->tasks()->withTrashed()->get()->each(fn ($task) => $task->restore());
        $project->supports()->withTrashed()->get()->each(fn ($support) => $support->restore());
    }
}

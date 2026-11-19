<?php

namespace App\Observers;

use App\Models\Project;
use App\Models\SizingProject;
use App\Services\Project\ProjectWorkflowService;

class SizingProjectObserver
{
    public function saved(SizingProject $sizing): void
    {
        $this->sync($sizing);
    }

    public function deleted(SizingProject $sizing): void
    {
        $this->sync($sizing);
    }

    public function restored(SizingProject $sizing): void
    {
        $this->sync($sizing);
    }

    private function sync(SizingProject $sizing): void
    {
        $project = Project::find($sizing->project_id);
        if ($project) {
            app(ProjectWorkflowService::class)->sync($project);
        }
    }
}

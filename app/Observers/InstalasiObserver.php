<?php

namespace App\Observers;

use App\Models\Instalasi;
use App\Models\Project;
use App\Services\Project\ProjectWorkflowService;

class InstalasiObserver
{
    public function saved(Instalasi $instalasi): void
    {
        $this->sync($instalasi);
    }

    public function deleted(Instalasi $instalasi): void
    {
        $this->sync($instalasi);
    }

    public function restored(Instalasi $instalasi): void
    {
        $this->sync($instalasi);
    }

    private function sync(Instalasi $instalasi): void
    {
        if (! $instalasi->project_id) {
            return;
        }

        $project = Project::find($instalasi->project_id);
        if ($project) {
            app(ProjectWorkflowService::class)->sync($project);
        }
    }
}

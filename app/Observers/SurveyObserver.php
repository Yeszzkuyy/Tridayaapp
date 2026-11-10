<?php

namespace App\Observers;

use App\Models\Project;
use App\Models\Survey;
use App\Services\Project\ProjectWorkflowService;

class SurveyObserver
{
    public function saved(Survey $survey): void
    {
        $this->sync($survey);
    }

    public function deleted(Survey $survey): void
    {
        $this->sync($survey);
    }

    public function restored(Survey $survey): void
    {
        $this->sync($survey);
    }

    private function sync(Survey $survey): void
    {
        if (! $survey->project_id) {
            return;
        }

        $project = Project::find($survey->project_id);
        if ($project) {
            app(ProjectWorkflowService::class)->sync($project);
        }
    }
}

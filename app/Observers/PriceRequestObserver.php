<?php

namespace App\Observers;

use App\Models\PriceRequest;
use App\Models\Project;
use App\Services\Project\ProjectWorkflowService;

class PriceRequestObserver
{
    public function saved(PriceRequest $priceRequest): void
    {
        $this->sync($priceRequest);
    }

    public function deleted(PriceRequest $priceRequest): void
    {
        $this->sync($priceRequest);
    }

    public function restored(PriceRequest $priceRequest): void
    {
        $this->sync($priceRequest);
    }

    private function sync(PriceRequest $priceRequest): void
    {
        $projectId = $priceRequest->project_id;

        if (! $projectId && $priceRequest->proposal_id) {
            $projectId = Project::where('proposal_id', $priceRequest->proposal_id)->value('id');
        }

        if (! $projectId) {
            return;
        }

        $project = Project::find($projectId);
        if ($project) {
            app(ProjectWorkflowService::class)->sync($project);
        }
    }
}

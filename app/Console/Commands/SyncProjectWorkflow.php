<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Services\Project\ProjectWorkflowService;
use Illuminate\Console\Command;

class SyncProjectWorkflow extends Command
{
    protected $signature = 'projects:sync-workflow
                            {--project= : Sync one project id only}
                            {--with-activity : Write step-completed activity for newly detected steps}';

    protected $description = 'Sinkronkan workflow, progress, dan status project dari data survey/sizing/price request/instalasi/BAST.';

    public function handle(ProjectWorkflowService $workflow): int
    {
        $query = Project::query()->with(['proposal', 'status']);

        if ($this->option('project')) {
            $query->whereKey((int) $this->option('project'));
        }

        $total = (clone $query)->count();
        $this->info("Menyinkronkan {$total} project...");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->chunkById(100, function ($projects) use ($workflow, $bar) {
            foreach ($projects as $project) {
                $workflow->sync($project, (bool) $this->option('with-activity'));
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info('Selesai.');

        return self::SUCCESS;
    }
}

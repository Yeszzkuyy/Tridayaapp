<?php

namespace App\Services\Project;

use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\ProjectStatus;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProjectService
{
    /**
     * Create a new project.
     *
     * Owner tracking:
     * - Jika caller sudah set sales_user_id / teknisi_user_id (misal dari
     *   ProposalController::convertToProject), pakai nilai itu.
     * - Kalau tidak, isi otomatis berdasarkan role user yang membuat:
     *   teknisi → teknisi_user_id, sales → sales_user_id.
     */
    public function create(array $data): Project
    {
        return DB::transaction(function () use ($data) {
            $user = Auth::user();

            // Auto-fill owner berdasarkan role creator bila belum di-set.
            $salesUserId    = $data['sales_user_id'] ?? null;
            $teknisiUserId  = $data['teknisi_user_id'] ?? null;

            if ($user && !$salesUserId && !$teknisiUserId) {
                if ($user->can('manage-technician')) {
                    $teknisiUserId = $user->id;
                } elseif ($user->can('manage-sales')) {
                    $salesUserId = $user->id;
                }
            }

            $project = Project::create([
                'customer_id'        => $data['customer_id'],
                'sales_user_id'      => $salesUserId,
                'teknisi_user_id'    => $teknisiUserId,
                'project_name'       => $data['name'] ?? $data['project_name'] ?? null,
                'work_type_id'       => $data['work_type_id'] ?? null,
                'account_manager_id' => $data['account_manager_id'] ?? null,
                'pic_engineer'       => $data['pic_engineer'] ?? null,
                'support_technicians' => $data['support_technicians'] ?? null,
                'proposal_id'        => $data['proposal_id'] ?? null,
                'quotation_number'   => $data['quotation_number'] ?? null,
                'description'        => $data['description'] ?? null,
                'start_date'         => $data['start_date'] ?? null,
                'project_status_id'  => $data['project_status_id']
                    ?? ProjectStatus::where('is_default', true)->value('id'),
                'progress'           => $data['progress'] ?? 0,
            ]);

            ProjectActivity::create([
                'project_id'    => $project->id,
                'user_id'       => Auth::id(),
                'activity_date' => now(),
                'title'         => __('Project Created'),
                'description'   => __('New project created successfully.'),
            ]);

            return $project;
        });
    }

    public function update(Project $project, array $data): Project
    {
        return DB::transaction(function () use ($project, $data) {
            $project->update([
                'project_name'       => $data['project_name'] ?? $project->project_name,
                'customer_id'        => $data['customer_id'] ?? $project->customer_id,
                'work_type_id'       => $data['work_type_id'] ?? $project->work_type_id,
                'account_manager_id' => $data['account_manager_id'] ?? $project->account_manager_id,
                'pic_engineer'       => $data['pic_engineer'] ?? $project->pic_engineer,
                'support_technicians' => $data['support_technicians'] ?? $project->support_technicians,
                'progress'           => $data['progress'] ?? $project->progress,
                'project_status_id'  => $data['project_status_id'] ?? $project->project_status_id,
                'description'        => $data['description'] ?? $project->description,
            ]);

            return $project;
        });
    }

    public function delete(Project $project): bool
    {
        return $project->delete();
    }
}

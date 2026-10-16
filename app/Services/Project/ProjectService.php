<?php

namespace App\Services\Project;

use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\ProjectStatus;
use App\Models\Survey;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProjectService
{
    /** Label ramah untuk status mentah workflow (survey/sizing/request harga/instalasi). */
    private const WORKFLOW_STATUS_LABELS = [
        'draft' => 'Draft',
        'scheduled' => 'Scheduled',
        'on_survey' => 'On Survey',
        'in_progress' => 'In Progress',
        'on_progress' => 'In Progress',
        'waiting' => 'Waiting',
        'waiting_approval' => 'Waiting Approval',
        'submitted' => 'Submitted',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];

    /** Warna badge x-status-badge per status workflow. */
    private const WORKFLOW_STATUS_COLORS = [
        'completed' => 'green',
        'approved' => 'green',
        'in_progress' => 'blue',
        'on_progress' => 'blue',
        'on_survey' => 'blue',
        'scheduled' => 'cyan',
        'submitted' => 'yellow',
        'waiting' => 'yellow',
        'waiting_approval' => 'yellow',
        'rejected' => 'red',
        'cancelled' => 'red',
        'draft' => 'slate',
    ];

    public function create(array $data): Project
    {
        return DB::transaction(function () use ($data) {
            $project = Project::create([
                'customer_id' => $data['customer_id'],
                'project_name' => $data['name'] ?? null,
                'work_type_id' => $data['work_type_id'] ?? null,
                'account_manager_id' => $data['account_manager_id'] ?? null,
                'pic_engineer' => $data['pic_engineer'] ?? null, // ← text
                'support_technicians' => $data['support_technicians'] ?? null, // ← text
                'description' => $data['description'] ?? null,
                'project_status_id' => $data['project_status_id']
                    ?? ProjectStatus::where('is_default', true)->value('id'),
                'progress' => 0,
            ]);

            ProjectActivity::create([
                'project_id' => $project->id,
                'user_id' => Auth::id(),
                'activity_date' => now(),
                'title' => 'Project Dibuat',
                'description' => 'Project baru berhasil dibuat',
            ]);

            return $project;
        });
    }

    public function update(Project $project, array $data): Project
    {
        return DB::transaction(function () use ($project, $data) {
            $project->update([
                'project_name' => $data['project_name'] ?? $project->project_name,
                'customer_id' => $data['customer_id'] ?? $project->customer_id,
                'work_type_id' => $data['work_type_id'] ?? $project->work_type_id,
                'account_manager_id' => $data['account_manager_id'] ?? $project->account_manager_id,
                'pic_engineer' => $data['pic_engineer'] ?? $project->pic_engineer,
                'support_technicians' => $data['support_technicians'] ?? $project->support_technicians,
                'progress' => $data['progress'] ?? $project->progress,
                'project_status_id' => $data['project_status_id'] ?? $project->project_status_id,
                'description' => $data['description'] ?? $project->description,
            ]);

            return $project;
        });
    }

    public function delete(Project $project): bool
    {
        return $project->delete();
    }

    /**
     * Susun data workflow (Request → Survey → Sizing → Request Harga →
     * Instalasi → BAST) untuk halaman detail project. Dipisah dari view
     * supaya Blade cukup menerima data siap pakai.
     *
     * @return array{steps: array<int, array<string, mixed>>, progress: int}
     */
    public function workflowSteps(Project $project): array
    {
        // Survey bisa dua arah: yang sudah menempel ke project ini,
        // atau milik customer sama yang belum punya project (survey
        // biasanya dibuat SEBELUM project-nya lahir).
        $surveys = Survey::where(function ($q) use ($project) {
            $q->where('project_id', $project->id)
                ->orWhere(function ($q2) use ($project) {
                    $q2->where('customer_id', $project->customer_id)
                        ->whereNull('project_id');
                });
        })->latest()->get();
        $sizings = $project->sizingProjects()->latest()->get();
        $requestHargas = $project->requestHargas()->latest()->get();
        $instalasis = $project->instalasis()->latest()->get();
        $bastDocs = $project->documents()->where('document_category_id', 1)->latest()->get();

        $stepFor = function (string $label, $record, string $showRoute, string $createUrl): array {
            $status = $record->status ?? null;

            return [
                'label' => $label,
                'done' => $record !== null && $record->status === 'completed',
                'link' => $record ? route($showRoute, $record) : $createUrl,
                'status_label' => $record
                    ? __(self::WORKFLOW_STATUS_LABELS[$status] ?? ucfirst((string) str_replace('_', ' ', (string) $status)))
                    : __('Not yet'),
                'status_color' => self::WORKFLOW_STATUS_COLORS[$status] ?? 'slate',
                'date' => $record?->updated_at,
            ];
        };

        $steps = [
            [
                'label' => __('Request'),
                'done' => true,
                'link' => null,
                'status_label' => __('Project created'),
                'status_color' => 'green',
                'date' => $project->created_at,
            ],
            $stepFor(
                __('Survey'),
                $surveys->first(),
                'teknisi.surveys.show',
                route('teknisi.surveys.create', ['project_id' => $project->id, 'customer_id' => $project->customer_id])
            ),
            $stepFor(
                __('Sizing'),
                $sizings->first(),
                'teknisi.sizing-projects.show',
                route('teknisi.sizing-projects.create', ['project_id' => $project->id])
            ),
            $stepFor(
                __('Price Request'),
                $requestHargas->first(),
                'teknisi.request-hargas.show',
                route('teknisi.request-hargas.create', ['project_id' => $project->id])
            ),
            $stepFor(
                __('Installation'),
                $instalasis->first(),
                'teknisi.instalasis.show',
                route('teknisi.instalasis.create', ['project_id' => $project->id])
            ),
            [
                'label' => __('BAST'),
                'done' => $bastDocs->isNotEmpty(),
                'link' => route('teknisi.documents.index', ['project_id' => $project->id]),
                'status_label' => $bastDocs->isNotEmpty()
                    ? trans_choice(':count document|:count documents', $bastDocs->count())
                    : __('Not yet'),
                'status_color' => $bastDocs->isNotEmpty() ? 'green' : 'slate',
                'date' => $bastDocs->first()?->updated_at,
            ],
        ];

        $completedCount = collect($steps)->where('done', true)->count();

        return [
            'steps' => $steps,
            'progress' => (int) round(($completedCount / count($steps)) * 100),
        ];
    }
}
<?php

namespace App\Services\Project;

use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\ProjectStatus;
use App\Models\User;
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
            $salesUserId = $data['sales_user_id'] ?? null;
            $teknisiUserId = $data['teknisi_user_id'] ?? null;

            if ($user && ! $salesUserId && ! $teknisiUserId) {
                if ($user->can('manage-technician')) {
                    $teknisiUserId = $user->id;
                } elseif ($user->can('manage-sales')) {
                    $salesUserId = $user->id;
                }
            }

            [$picName, $supportNames, $supportIds] = $this->resolveEngineerAssignments($data);

            $project = Project::create([
                'customer_id' => $data['customer_id'],
                'sales_user_id' => $salesUserId,
                'teknisi_user_id' => $teknisiUserId,
                'project_name' => $data['name'] ?? $data['project_name'] ?? null,
                'work_type_id' => $data['work_type_id'] ?? null,
                'account_manager_id' => $data['account_manager_id'] ?? null,
                'pic_engineer' => $picName,
                'support_technicians' => $supportNames,
                'proposal_id' => $data['proposal_id'] ?? null,
                'quotation_number' => $data['quotation_number'] ?? null,
                'description' => $data['description'] ?? null,
                'start_date' => $data['start_date'] ?? null,
                'project_status_id' => $data['project_status_id']
                    ?? ProjectStatus::where('is_default', true)->value('id'),
                'progress' => $data['progress'] ?? 17,
                'workflow_state' => $data['workflow_state'] ?? ['request'],
            ]);

            if ($supportIds !== []) {
                $project->supportUsers()->sync($supportIds);
            }

            // Catatan: ProjectActivity dibuat otomatis oleh ProjectObserver::created()
            // agar tidak tercatat ganda saat ProjectService::create() dipanggil.

            return $project;
        });
    }

    public function update(Project $project, array $data): Project
    {
        return DB::transaction(function () use ($project, $data) {
            [$picName, $supportNames, $supportIds] = $this->resolveEngineerAssignments($data);

            $project->update([
                'project_name' => $data['project_name'] ?? $project->project_name,
                'customer_id' => $data['customer_id'] ?? $project->customer_id,
                'work_type_id' => $data['work_type_id'] ?? $project->work_type_id,
                'account_manager_id' => $data['account_manager_id'] ?? $project->account_manager_id,
                'teknisi_user_id' => array_key_exists('teknisi_user_id', $data)
                    ? $data['teknisi_user_id']
                    : $project->teknisi_user_id,
                'pic_engineer' => $picName ?? $project->pic_engineer,
                'support_technicians' => $supportNames ?? $project->support_technicians,
                'progress' => $data['progress'] ?? $project->progress,
                'project_status_id' => $data['project_status_id'] ?? $project->project_status_id,
                'description' => $data['description'] ?? $project->description,
            ]);

            if ($supportNames !== null) {
                $project->supportUsers()->sync($supportIds ?? []);
            }

            return $project;
        });
    }

    /**
     * Terima sumber baru (akun user) dan sumber lama (nama teks).
     *
     * @return array{0: ?string, 1: ?string, 2: ?array<int, int>}
     *                                                            [nama PIC, daftar nama support, daftar id support]
     */
    private function resolveEngineerAssignments(array $data): array
    {
        $picName = null;
        $supportNames = null;
        $supportIds = null;

        $picId = $data['teknisi_user_id'] ?? null;
        if ($picId) {
            $picName = User::find($picId)?->name;
        }

        if (array_key_exists('support_user_ids', $data)) {
            $ids = collect($data['support_user_ids'] ?? [])
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            $users = User::whereIn('id', $ids)->get(['id', 'name']);
            $supportIds = $users->pluck('id')->all();
            $supportNames = $users->pluck('name')->implode(', ');
        } elseif (array_key_exists('support_technicians', $data)) {
            $names = collect((array) ($data['support_technicians'] ?? []))
                ->map(fn ($name) => trim((string) $name))
                ->filter()
                ->unique()
                ->values();

            $supportNames = $names->implode(', ');

            $techIds = User::whereIn('name', $names)
                ->whereHas('roles', fn ($q) => $q->whereIn('name', ['technician', 'lead-technician', 'prakerin-technician']))
                ->pluck('id');

            $supportIds = $techIds->all();
        }

        if ($picName === null && filled($data['pic_engineer'] ?? null)) {
            $picName = trim((string) $data['pic_engineer']);
        }

        return [$picName, $supportNames, $supportIds];
    }

    public function delete(Project $project): bool
    {
        return $project->delete();
    }
}

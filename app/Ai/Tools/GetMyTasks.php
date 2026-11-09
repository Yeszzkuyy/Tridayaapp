<?php

namespace App\Ai\Tools;

use App\Models\ProjectTask;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetMyTasks implements Tool
{
    public function __construct(public User $user) {}

    public function description(): Stringable|string
    {
        return 'Menampilkan daftar tugas (project task) yang ditugaskan ke user yang sedang bertanya. Read-only.';
    }

    public function handle(Request $request): Stringable|string
    {
        if (! $this->user->hasAnyPermission(['view-technician', 'manage-technician', 'view-sales'])) {
            return 'Akses ditolak: kamu tidak memiliki izin untuk melihat data tugas (perlu izin divisi engineer atau sales).';
        }

        $tasks = ProjectTask::query()
            ->with('project:id,project_name,project_code')
            ->where('assigned_to', $this->user->id)
            ->orderByRaw("CASE WHEN status = 'Done' THEN 1 ELSE 0 END, due_date IS NULL, due_date ASC")
            ->limit(20)
            ->get();

        if ($tasks->isEmpty()) {
            return 'Tidak ada tugas yang ditugaskan kepadamu saat ini.';
        }

        return json_encode(
            $tasks->map(fn ($task) => [
                'id' => $task->id,
                'task' => $task->title,
                'project' => $task->project?->project_name,
                'project_code' => $task->project?->project_code,
                'status' => $task->status,
                'due_date' => $task->due_date?->toDateString(),
            ])->all(),
            JSON_UNESCAPED_UNICODE
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectActivity extends Model
{
    protected $fillable = [
        'project_id',
        'user_id',
        'activity_date',
        'type',
        'title',
        'description',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'activity_date' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Normalize raw/legacy activity titles into consistent English microcopy.
     */
    public function displayTitle(): string
    {
        $raw = trim((string) $this->title);
        $lower = mb_strtolower($raw);

        if (in_array($lower, ['projects created', 'project created', 'project dibuat', 'created a project'], true)) {
            return 'Created a project';
        }

        if (in_array($lower, ['status changed', 'status diubah', 'status project diubah', 'updated project status'], true)) {
            return 'Updated project status';
        }

        if (in_array($lower, ['tambah support engineer', 'support engineer added', 'added a support engineer'], true)) {
            return 'Added a support engineer';
        }

        if (in_array($lower, ['support engineer dihapus', 'support engineer removed', 'removed a support engineer'], true)) {
            return 'Removed a support engineer';
        }

        return $raw !== '' ? $raw : 'Updated project';
    }

    /**
     * Key for deduplicating duplicate log entries (e.g. observer + service both logging creation).
     */
    public function dedupKey(): string
    {
        $normalized = $this->displayTitle();

        if ($normalized === 'Created a project') {
            return 'project-created:' . ($this->project_id ?? 0);
        }

        $minute = $this->activity_date?->format('Y-m-d H:i') ?? '';

        return ($this->project_id ?? 0) . ':' . ($this->user_id ?? 0) . ':' . $normalized . ':' . $minute;
    }
}
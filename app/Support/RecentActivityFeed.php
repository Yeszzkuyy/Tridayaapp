<?php

namespace App\Support;

use App\Models\Instalasi;
use App\Models\ProjectActivity;
use App\Models\Survey;
use Illuminate\Support\Collection;

/**
 * Feed aktivitas terbaru lintas modul untuk dashboard.
 *
 * Sumber:
 * - ProjectActivity (buat project, ubah status, support engineer).
 * - Survey (dibuat, laporan diunggah, selesai).
 * - Instalasi (dibuat, laporan diunggah, selesai, BAST).
 *
 * Tiap record menghasilkan satu baris aktivitas dengan milestone terakhir
 * yang paling relevan, lalu digabung dan diurutkan berdasarkan waktu.
 */
class RecentActivityFeed
{
    public static function build(int $limit = 5): Collection
    {
        $projectActivities = ProjectActivity::with([
            'project:id,project_name,customer_id',
            'project.customer:id,name',
            'user:id,name,avatar',
        ])
            ->latest('activity_date')
            ->latest('id')
            ->take(25)
            ->get()
            ->unique(fn (ProjectActivity $activity) => $activity->dedupKey())
            ->map(fn (ProjectActivity $activity) => [
                'user' => $activity->user,
                'title' => $activity->displayTitle(),
                'subject' => $activity->project?->project_name ?? __('Project not available'),
                'context' => $activity->project?->customer?->name,
                'date' => $activity->activity_date,
                'type' => 'project',
                'project_id' => $activity->project_id,
            ]);

        $surveyActivities = Survey::with([
            'project:id,project_name,customer_id',
            'project.customer:id,name',
            'customer:id,name',
            'technician:id,name,avatar',
            'sales:id,name,avatar',
        ])
            ->latest('updated_at')
            ->take(25)
            ->get()
            ->map(function (Survey $survey) {
                if ($survey->status === Survey::STATUS_COMPLETED) {
                    $title = 'Completed a survey';
                    $date = $survey->updated_at ?? $survey->created_at;
                } elseif ($survey->hasReport()) {
                    $title = 'Uploaded a survey report';
                    $date = $survey->updated_at ?? $survey->created_at;
                } else {
                    $title = 'Created a survey';
                    $date = $survey->created_at;
                }

                return [
                    'user' => $survey->technician ?? $survey->sales,
                    'title' => $title,
                    'subject' => $survey->title ?: __('Survey #:id', ['id' => $survey->id]),
                    'context' => $survey->customer?->name ?? $survey->project?->customer?->name,
                    'date' => $date,
                    'type' => 'survey',
                    'project_id' => $survey->project_id,
                ];
            });

        $installationActivities = Instalasi::with([
            'project:id,project_name,customer_id',
            'project.customer:id,name',
            'customer:id,name',
            'technicalRequest:id,title',
            'technician:id,name,avatar',
            'sales:id,name,avatar',
            'completedBy:id,name,avatar',
        ])
            ->latest('updated_at')
            ->take(25)
            ->get()
            ->map(function (Instalasi $instalasi) {
                if ($instalasi->isBastReady()) {
                    $title = 'BAST signed';
                    $date = $instalasi->bast_ready_at
                        ?? $instalasi->bast_signed_customer_at
                        ?? $instalasi->updated_at;
                    $user = $instalasi->completedBy ?? $instalasi->technician;
                } elseif ($instalasi->completed_at) {
                    $title = 'Completed an installation';
                    $date = $instalasi->completed_at;
                    $user = $instalasi->completedBy ?? $instalasi->technician;
                } elseif ($instalasi->hasReport()) {
                    $title = 'Uploaded an installation report';
                    $date = $instalasi->updated_at ?? $instalasi->created_at;
                    $user = $instalasi->technician;
                } else {
                    $title = 'Created an installation';
                    $date = $instalasi->created_at;
                    $user = $instalasi->sales ?? $instalasi->technician;
                }

                $subject = $instalasi->technicalRequest?->title
                    ?: $instalasi->project?->project_name
                    ?: __('Installation #:id', ['id' => $instalasi->id]);

                return [
                    'user' => $user,
                    'title' => $title,
                    'subject' => $subject,
                    'context' => $instalasi->customer?->name ?? $instalasi->project?->customer?->name,
                    'date' => $date,
                    'type' => 'installation',
                    'project_id' => $instalasi->project_id,
                ];
            });

        return $projectActivities
            ->concat($surveyActivities)
            ->concat($installationActivities)
            ->sortByDesc(fn (array $activity) => $activity['date']?->getTimestamp() ?? 0)
            ->take($limit)
            ->values();
    }
}

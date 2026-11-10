<?php

namespace App\Services\Project;

use App\Models\Instalasi;
use App\Models\Survey;
use App\Models\TechnicalRequest;

/**
 * Workflow pendek untuk pekerjaan lapangan non-project (Request Sales).
 *
 * Survey      : Dijadwalkan -> Survey -> Hasil              (3 langkah)
 * Instalasi   : Dijadwalkan -> Instalasi -> Hasil -> BAST   (4 langkah)
 * Tipe lain   : Dijadwalkan -> Dikerjakan -> Hasil          (3 langkah)
 */
class FieldJobWorkflowService
{
    /**
     * @return array{steps: array<int, array<string, mixed>>, done: int, total: int, progress: int}
     */
    public function evaluate(TechnicalRequest $request): array
    {
        $request->loadMissing(['survey', 'installation']);

        $scheduled = in_array($request->status, ['assigned', 'in_progress', 'completed'], true)
            && (filled($request->target_date) || $request->schedules()->exists());

        $steps = [
            $this->step('scheduled', __('Scheduled'), $scheduled, __('Waiting for the lead engineer to schedule')),
        ];

        if ($request->request_type === 'survey') {
            $survey = $request->survey;
            $hasReport = $survey instanceof Survey && $survey->hasReport();

            $steps[] = $this->step('survey', __('Survey'), $survey !== null, __('Survey has not been created'));
            $steps[] = $this->step('result', __('Result'), $hasReport, __('Upload the survey report'));
        } elseif ($request->request_type === 'installation') {
            $installation = $request->installation;
            $hasReport = $installation instanceof Instalasi && $installation->hasReport();
            $bastReady = $installation instanceof Instalasi && $installation->isBastReady();

            $steps[] = $this->step('installation', __('Installation'), $installation !== null, __('Installation has not been created'));
            $steps[] = $this->step('result', __('Result'), $hasReport, __('Upload the installation report'));

            // BAST hanya langkah pekerjaan non-project. Untuk instalasi di
            // dalam project, BAST resmi mengikuti workflow project (Admin).
            if (blank($request->project_id)) {
                $steps[] = $this->step('bast', __('BAST'), $bastReady, __('Upload the signed BAST'));
            }
        } else {
            $worked = in_array($request->status, ['in_progress', 'completed'], true);
            $steps[] = $this->step('work', __('Work'), $worked, __('Work has not started'));
            $steps[] = $this->step('result', __('Result'), filled($request->technical_result), __('Fill in the technical result'));
        }

        $done = collect($steps)->where('done', true)->count();
        $total = count($steps);

        return [
            'steps' => $steps,
            'done' => $done,
            'total' => $total,
            'progress' => $total > 0 ? (int) round($done / $total * 100) : 0,
        ];
    }

    private function step(string $key, string $label, bool $done, string $pendingDetail): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'done' => $done,
            'detail' => $done ? __('Completed') : $pendingDetail,
        ];
    }
}

<?php

namespace App\Services\Project;

use App\Models\Instalasi;
use App\Models\PriceRequest;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\ProjectStatus;
use App\Models\SizingProject;
use App\Models\Survey;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Satu sumber kebenaran workflow project.
 *
 * Enam langkah besar project:
 *   Request -> Survey -> Sizing -> Price Request -> Installation -> BAST
 *
 * Status tiap langkah dibaca dari data modul (bukan diketik manual), lalu
 * hasilnya dipakai untuk mengisi progress, status project, dan jejak aktivitas.
 */
class ProjectWorkflowService
{
    public const STEPS = ['request', 'survey', 'sizing', 'price_request', 'installation', 'bast'];

    /** Status project yang tidak boleh ditimpa otomatis. */
    private const MANUAL_STATUSES = ['Hold', 'Cancelled', 'Warranty', 'Maintenance'];

    /**
     * Hitung kondisi workflow tanpa mengubah data.
     *
     * @return array{steps: array<int, array<string, mixed>>, done: int, total: int, progress: int, has_activity: bool}
     */
    public function evaluate(Project $project): array
    {
        $project->loadMissing(['proposal', 'status']);

        $surveys = Survey::where('project_id', $project->id)->get();
        $sizings = SizingProject::where('project_id', $project->id)->get();
        $instalasis = Instalasi::where('project_id', $project->id)->get();
        $priceRequests = $this->priceRequestsFor($project);

        $completedSurveys = $surveys->filter(
            fn (Survey $survey) => $survey->status === Survey::STATUS_COMPLETED && $survey->hasReport()
        );
        $completedSizings = $sizings->filter(
            fn (SizingProject $sizing) => $sizing->status === 'completed' && ! empty($sizing->attachments)
        );

        $activeInstallations = $instalasis->where('status', '!=', 'cancelled');
        $completedInstallations = $activeInstallations->filter(
            fn (Instalasi $instalasi) => $instalasi->status === 'completed' && $instalasi->hasReport()
        );

        $activePriceRequests = $priceRequests->whereNotIn('status', ['cancelled', 'rejected']);
        $completedPriceRequests = $activePriceRequests->where('status', 'completed');

        $surveyDone = $surveys->isNotEmpty() && $completedSurveys->count() === $surveys->count();
        $sizingDone = $sizings->isNotEmpty() && $completedSizings->count() === $sizings->count();
        $installationDone = $activeInstallations->isNotEmpty()
            && $completedInstallations->count() === $activeInstallations->count();

        // Quotation yang sudah accepted dianggap menutup fase harga: tidak
        // semua penawaran butuh Price Request terpisah.
        $pricingDone = ($completedPriceRequests->isNotEmpty()
                && $completedPriceRequests->count() === $activePriceRequests->count())
            || ($activePriceRequests->isEmpty() && filled($project->proposal_id));

        $steps = [
            [
                'key' => 'request',
                'label' => __('Request'),
                'done' => true,
                'detail' => __('Project created'),
                'count' => 1,
                'url' => null,
            ],
            $this->step(
                'survey',
                __('Survey'),
                $surveyDone,
                $surveys,
                $completedSurveys,
                $surveys->first() ? route('teknisi.surveys.show', $surveys->first()) : route('teknisi.surveys.create', [
                    'project_id' => $project->id,
                    'customer_id' => $project->customer_id,
                ]),
            ),
            $this->step(
                'sizing',
                __('Sizing'),
                $sizingDone,
                $sizings,
                $completedSizings,
                $sizings->first() ? route('teknisi.sizing-projects.show', $sizings->first()) : route('teknisi.sizing-projects.create', [
                    'project_id' => $project->id,
                ]),
            ),
            [
                'key' => 'price_request',
                'label' => __('Price Request'),
                'done' => $pricingDone,
                'detail' => $completedPriceRequests->isNotEmpty()
                    ? __(':count completed', ['count' => $completedPriceRequests->count()])
                    : ($pricingDone ? __('Pricing final from the accepted quotation') : __('No data yet')),
                'count' => $priceRequests->count(),
                'url' => $priceRequests->first()
                    ? route('sales.price-requests.show', $priceRequests->first())
                    : (filled($project->proposal_id)
                        ? route('sales.proposals.show', $project->proposal_id)
                        : route('sales.price-requests.create', ['customer_id' => $project->customer_id, 'project_id' => $project->id])),
            ],
            $this->step(
                'installation',
                __('Installation'),
                $installationDone,
                $activeInstallations,
                $completedInstallations,
                $instalasis->first() ? route('teknisi.instalasis.show', $instalasis->first()) : route('teknisi.instalasis.create', [
                    'project_id' => $project->id,
                ]),
            ),
            [
                'key' => 'bast',
                'label' => __('BAST'),
                'done' => $project->isBastReady(),
                'detail' => $project->isBastReady()
                    ? __('Signed by both parties')
                    : (filled($project->bast_number) ? __('Waiting for signatures') : __('No BAST yet')),
                'count' => $project->isBastReady() ? 1 : 0,
                'url' => auth()->user()?->can('manage-admin')
                    ? route('admin.bast.edit', $project)
                    : route('project-documents.index', $project),
            ],
        ];

        $done = collect($steps)->where('done', true)->count();
        $total = count($steps);

        $steps = $this->withAccessFlags($steps);

        return [
            'steps' => $steps,
            'done' => $done,
            'total' => $total,
            'progress' => $total > 0 ? (int) round($done / $total * 100) : 0,
            'has_activity' => $surveys->isNotEmpty() || $sizings->isNotEmpty() || $instalasis->isNotEmpty() || $priceRequests->isNotEmpty(),
        ];
    }

    /**
     * Tandai langkah mana yang boleh dibuka/dimulai oleh user saat ini,
     * supaya tombol Start/View tidak menabrak izin (403).
     *
     * @param  array<int, array<string, mixed>>  $steps
     * @return array<int, array<string, mixed>>
     */
    private function withAccessFlags(array $steps): array
    {
        $user = auth()->user();

        $flags = [
            'survey' => [
                'start' => $user?->can('manage-technician') ?? false,
                'view' => $user?->canAny(['view-technician', 'manage-technician', 'view-sales']) ?? false,
            ],
            'sizing' => [
                'start' => $user?->can('manage-technician') ?? false,
                'view' => $user?->canAny(['view-technician', 'manage-technician']) ?? false,
            ],
            'price_request' => [
                'start' => $user?->canAny(['manage-sales', 'manage-inside-sales', 'manage-technician']) ?? false,
                'view' => $user?->canAny(['view-sales', 'manage-sales', 'manage-inside-sales', 'view-admin']) ?? false,
            ],
            'installation' => [
                'start' => $user?->can('manage-technician') ?? false,
                'view' => $user?->canAny(['view-technician', 'manage-technician']) ?? false,
            ],
            'bast' => [
                'start' => $user?->canAny(['manage-admin', 'manage-technician']) ?? false,
                'view' => $user?->canAny(['view-technician', 'manage-technician', 'view-sales', 'view-admin', 'manage-admin']) ?? false,
            ],
        ];

        return array_map(function (array $step) use ($flags) {
            $step['can_start'] = $flags[$step['key']]['start'] ?? false;
            $step['can_view'] = $flags[$step['key']]['view'] ?? false;

            return $step;
        }, $steps);
    }

    /**
     * Sinkronkan workflow ke project: progress, status, dan aktivitas.
     *
     * @return array{workflow: array<string, mixed>, newly_done: array<int, string>}
     */
    public function sync(Project $project, bool $logActivity = true): array
    {
        $workflow = $this->evaluate($project);
        $oldState = array_values((array) ($project->workflow_state ?? []));
        $newState = collect($workflow['steps'])->where('done', true)->pluck('key')->values()->all();
        $newlyDone = array_values(array_diff($newState, $oldState));

        $project->forceFill([
            'workflow_state' => $newState,
            'workflow_synced_at' => now(),
            'progress' => $workflow['progress'],
        ])->save();

        $targetStatus = $this->targetStatus($project->fresh(), $workflow);

        if ($targetStatus && (int) $targetStatus !== (int) $project->project_status_id) {
            $project->forceFill(['project_status_id' => $targetStatus])->save();
            Cache::forget('tek:status-counts');
        }

        if ($logActivity && $newlyDone !== []) {
            $this->logCompletedSteps($project, $workflow['steps'], $newlyDone);
        }

        return [
            'workflow' => $workflow,
            'newly_done' => $newlyDone,
        ];
    }

    /** @return Collection<int, PriceRequest> */
    private function priceRequestsFor(Project $project)
    {
        return PriceRequest::query()
            ->where(function ($query) use ($project) {
                $query->where('project_id', $project->id);

                if (filled($project->proposal_id)) {
                    $query->orWhere('proposal_id', $project->proposal_id);
                }
            })
            ->latest()
            ->get();
    }

    /**
     * @param  Collection<int, mixed>  $all
     * @param  Collection<int, mixed>  $completed
     */
    private function step(string $key, string $label, bool $done, $all, $completed, ?string $url): array
    {
        $count = $all->count();
        $doneCount = $completed->count();

        $detail = match (true) {
            $done => __(':count completed', ['count' => $doneCount]),
            $count > 0 => __(':count in progress', ['count' => $count]),
            default => __('No data yet'),
        };

        return [
            'key' => $key,
            'label' => $label,
            'done' => $done,
            'detail' => $detail,
            'count' => $count,
            'url' => $url,
        ];
    }

    private function targetStatus(Project $project, array $workflow): ?int
    {
        $current = $project->status?->name ?? '';
        if (in_array($current, self::MANUAL_STATUSES, true)) {
            return null;
        }

        if ($workflow['done'] === $workflow['total']) {
            // Tutup komersial hanya bila BAST siap + invoice pelanggan lunas.
            if ($project->evaluateCommercialClosing()) {
                return $this->statusId('Done');
            }

            return in_array($current, ['Pending', 'Open', 'On Progress'], true)
                ? $this->statusId('On Progress')
                : null;
        }

        if ($workflow['has_activity'] || $this->hasFieldStep($workflow)) {
            return in_array($current, ['Pending', 'Open'], true)
                ? $this->statusId('On Progress')
                : null;
        }

        return null;
    }

    /** Apakah ada langkah lapangan yang sudah selesai (bukan pra-project). */
    private function hasFieldStep(array $workflow): bool
    {
        return collect($workflow['steps'])->contains(
            fn (array $step) => $step['done'] && in_array($step['key'], ['survey', 'sizing', 'installation', 'bast'], true)
        );
    }

    private function statusId(string $name): ?int
    {
        return ProjectStatus::where('name', $name)->value('id');
    }

    /** @param array<int, array<string, mixed>> $steps */
    private function logCompletedSteps(Project $project, array $steps, array $keys): void
    {
        $userId = auth()->id() ?? $project->teknisi_user_id ?? $project->sales_user_id ?? $project->proposal?->created_by;
        if (! $userId) {
            return;
        }

        $byKey = collect($steps)->keyBy('key');

        foreach ($keys as $key) {
            $step = $byKey->get($key);
            if (! $step) {
                continue;
            }

            ProjectActivity::create([
                'project_id' => $project->id,
                'user_id' => $userId,
                'activity_date' => now(),
                'title' => __('Workflow step completed'),
                'description' => $step['label'].': '.$step['detail'],
            ]);
        }
    }
}

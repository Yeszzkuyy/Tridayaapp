<?php

namespace App\Http\Controllers;

use App\Models\Instalasi;
use App\Models\Survey;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Field Work — satu daftar untuk Instalasi + Survey.
 *
 * Semua role teknisi melihat seluruh data (bukan hanya miliknya), dengan
 * filter jenis, status, PIC, tanggal, dan pencarian. Detail tetap memakai
 * halaman modul masing-masing supaya laporan/dokumen tidak terduplikasi.
 */
class FieldWorkController extends Controller
{
    private const PER_PAGE = 15;

    public function index(Request $request)
    {
        $tab = in_array($request->query('tab'), ['all', 'installation', 'survey'], true)
            ? $request->query('tab')
            : 'all';

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => (string) $request->query('status', ''),
            'pic' => trim((string) $request->query('pic', '')),
            'date_from' => (string) $request->query('date_from', ''),
            'date_to' => (string) $request->query('date_to', ''),
        ];

        $allRows = $this->installationRows($filters)
            ->merge($this->surveyRows($filters));

        $tabCounts = [
            'all' => $allRows->count(),
            'installation' => $allRows->where('type', 'installation')->count(),
            'survey' => $allRows->where('type', 'survey')->count(),
        ];

        $rows = match ($tab) {
            'installation' => $allRows->where('type', 'installation'),
            'survey' => $allRows->where('type', 'survey'),
            default => $allRows,
        };

        $rows = $rows->sortByDesc('date_sort')->values();

        $stats = [
            'total' => $rows->count(),
            'active' => $rows->whereIn('status_key', ['planned', 'in_progress', 'waiting'])->count(),
            'done_month' => $rows->filter(fn ($row) => $row['status_key'] === 'done'
                && filled($row['date'])
                && Carbon::parse($row['date'])->isSameMonth(now()))->count(),
            'installation' => $tabCounts['installation'],
            'survey' => $tabCounts['survey'],
        ];

        $page = LengthAwarePaginator::resolveCurrentPage();
        $items = $rows->forPage($page, self::PER_PAGE)->values();

        $paginator = new LengthAwarePaginator(
            $items,
            $rows->count(),
            self::PER_PAGE,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return view('teknisi.field-work.index', [
            'items' => $paginator,
            'tab' => $tab,
            'tabCounts' => $tabCounts,
            'stats' => $stats,
            'filters' => $filters,
        ]);
    }

    /** @return Collection<int, array<string, mixed>> */
    private function installationRows(array $filters): Collection
    {
        $query = Instalasi::query()->with(['project.customer']);

        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $query->where(function ($w) use ($q) {
                $w->whereLike('location', "%{$q}%")
                    ->orWhereLike('technician_pic', "%{$q}%")
                    ->orWhereHas('project', fn ($p) => $p
                        ->whereLike('project_name', "%{$q}%")
                        ->orWhereHas('customer', fn ($c) => $c->whereLike('name', "%{$q}%")));
            });
        }

        if ($filters['pic'] !== '') {
            $query->whereLike('technician_pic', "%{$filters['pic']}%");
        }

        $this->applyStatusFilter($query, $filters['status'], 'installation');
        $this->applyDateFilter($query, $filters, 'schedule_date');

        return $query->latest('id')->get()->map(function (Instalasi $instalasi) {
            [$statusKey, $statusLabel] = $this->normalizeStatus('installation', $instalasi->status);
            $date = $instalasi->schedule_date ?? $instalasi->created_at;

            return [
                'type' => 'installation',
                'type_label' => __('Installation'),
                'id' => $instalasi->id,
                'title' => $instalasi->project?->project_name ?? __('Installation #:id', ['id' => $instalasi->id]),
                'detail' => $instalasi->location,
                'customer' => $instalasi->project?->customer?->name,
                'project' => $instalasi->project?->project_name,
                'pic' => $instalasi->technician_pic,
                'date' => $date?->toDateString(),
                'date_sort' => $date?->getTimestamp() ?? 0,
                'status' => $instalasi->status,
                'status_key' => $statusKey,
                'status_label' => $statusLabel,
                'url' => route('teknisi.instalasis.show', $instalasi->id),
            ];
        });
    }

    /** @return Collection<int, array<string, mixed>> */
    private function surveyRows(array $filters): Collection
    {
        $query = Survey::query()->with(['customer', 'project.customer', 'sales']);

        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $query->where(function ($w) use ($q) {
                $w->whereLike('title', "%{$q}%")
                    ->orWhereLike('location', "%{$q}%")
                    ->orWhereLike('pic', "%{$q}%")
                    ->orWhereHas('customer', fn ($c) => $c->whereLike('name', "%{$q}%"))
                    ->orWhereHas('project', fn ($p) => $p->whereLike('project_name', "%{$q}%"));
            });
        }

        if ($filters['pic'] !== '') {
            $query->whereLike('pic', "%{$filters['pic']}%");
        }

        $this->applyStatusFilter($query, $filters['status'], 'survey');
        $this->applyDateFilter($query, $filters, 'survey_date');

        return $query->latest('id')->get()->map(function (Survey $survey) {
            [$statusKey, $statusLabel] = $this->normalizeStatus('survey', $survey->status);
            $date = $survey->survey_date ?? $survey->created_at;

            return [
                'type' => 'survey',
                'type_label' => __('Survey'),
                'id' => $survey->id,
                'title' => $survey->title ?: __('Survey #:id', ['id' => $survey->id]),
                'detail' => $survey->location,
                'customer' => $survey->customer?->name ?? $survey->project?->customer?->name,
                'project' => $survey->project?->project_name,
                'pic' => $survey->pic,
                'date' => $date?->toDateString(),
                'date_sort' => $date?->getTimestamp() ?? 0,
                'status' => $survey->status,
                'status_key' => $statusKey,
                'status_label' => $statusLabel,
                'url' => route('teknisi.surveys.show', $survey->id),
            ];
        });
    }

    /**
     * Filter status memakai istilah seragam agar satu pilihan berlaku untuk
     * dua modul yang punya daftar status berbeda.
     */
    private function applyStatusFilter($query, string $status, string $type): void
    {
        $map = [
            'planned' => $type === 'installation' ? ['scheduled'] : ['draft', 'scheduled'],
            'in_progress' => $type === 'installation' ? ['on_progress'] : ['on_survey'],
            'waiting' => $type === 'installation' ? ['waiting'] : [],
            'done' => ['completed'],
            'cancelled' => ['cancelled'],
        ];

        if (isset($map[$status])) {
            $query->whereIn('status', $map[$status]);
        }
    }

    private function applyDateFilter($query, array $filters, string $column): void
    {
        if ($filters['date_from'] !== '') {
            $query->whereDate($column, '>=', $filters['date_from']);
        }

        if ($filters['date_to'] !== '') {
            $query->whereDate($column, '<=', $filters['date_to']);
        }
    }

    /** @return array{0: string, 1: string} */
    private function normalizeStatus(string $type, ?string $status): array
    {
        return match ($status) {
            'draft', 'scheduled' => ['planned', __('Planned')],
            'on_progress', 'on_survey' => ['in_progress', __('In Progress')],
            'waiting' => ['waiting', __('Waiting')],
            'completed' => ['done', __('Done')],
            'cancelled' => ['cancelled', __('Cancelled')],
            default => ['planned', __(ucfirst((string) $status ?: 'Planned'))],
        };
    }
}

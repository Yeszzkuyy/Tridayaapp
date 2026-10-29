<?php

namespace App\Http\Controllers;

use App\Models\LeadTask;
use App\Models\PriceRequest;
use App\Models\TechnicalRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SalesRequestHubController extends Controller
{
    public const TABS = ['inside-sales', 'price', 'engineer'];

    public function index(Request $request)
    {
        $tab = in_array($request->get('tab'), self::TABS, true)
            ? $request->get('tab')
            : 'inside-sales';

        $user = $request->user();

        $openTask = $this->openTask($request);
        if ($openTask) {
            $tab = 'inside-sales';
        }

        $taskQuery = $this->tasksFor($user);
        $techBase = $this->techRequestsFor($user);
        $priceBase = $this->priceRequestsFor($user);

        return view('sales.requests.index', [
            'tab' => $tab,
            'openTask' => $openTask,
            'tasks' => (clone $taskQuery)->paginate(10)->withQueryString(),
            'priceRequests' => (clone $priceBase)->paginate(10, ['*'], 'price_page')->withQueryString(),
            'engineerRequests' => (clone $techBase)->where('request_type', '!=', 'estimate')->paginate(10, ['*'], 'engineer_page')->withQueryString(),
            'counts' => [
                'inside-sales' => (clone $taskQuery)->count(),
                'price' => (clone $priceBase)->count(),
                'engineer' => (clone $techBase)->where('request_type', '!=', 'estimate')->count(),
            ],
        ]);
    }

    /**
     * Deep link notifikasi: buka diskusi satu task di dalam hub.
     * Boleh dibuka oleh assignee, pemilik lead (LeadPolicy), atau management.
     */
    private function openTask(Request $request): ?LeadTask
    {
        $taskId = (int) $request->query('task', 0);
        if ($taskId <= 0) {
            return null;
        }

        $user = $request->user();
        $task = LeadTask::with(['lead.customer', 'assignee', 'creator', 'comments.user'])
            ->findOrFail($taskId);

        $isAssignee = (int) $task->assigned_to === (int) $user->id;
        $canViewLead = $task->lead !== null && Gate::forUser($user)->allows('view', $task->lead);

        abort_unless($isAssignee || $canViewLead, 403);

        return $task;
    }

    /**
     * Task inside sales dengan scoping yang sama seperti daftar LeadTask:
     * inside sales melihat miliknya, sales melihat lead miliknya,
     * management melihat semua.
     *
     * Multi-role (sales + inside-sales) memakai OR: task yang di-assign ke
     * dia ATAU lead yang di-assign ke dia — bukan AND, yang membuat task
     * hilang saat assignee dan pemilik lead berbeda orang.
     */
    private function tasksFor($user)
    {
        $query = LeadTask::with(['lead.customer', 'assignee', 'creator'])->withCount('attachments')->latest();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->can('manage-sales-leads')) {
            return $query;
        }

        $query->where(function ($q) use ($user) {
            if ($user->can('manage-inside-sales')) {
                $q->orWhere('assigned_to', $user->id);
            }
            if ($user->can('view-sales') || $user->can('manage-sales')) {
                $q->orWhereHas('lead', fn ($l) => $l->where('assigned_to', $user->id));
            }
        });

        return $query;
    }

    /**
     * Request teknis: pemilik request melihat miliknya sendiri,
     * management (manage-sales-leads) melihat semua.
     */
    private function techRequestsFor($user)
    {
        $query = TechnicalRequest::with(['lead.customer', 'requester', 'technician'])->latest();

        if (!($user && $user->can('manage-sales-leads'))) {
            $query->where('requested_by', $user->id);
        }

        return $query;
    }

    /**
     * Request Harga (sistem terpisah): pemilik melihat miliknya sendiri,
     * management (manage-sales-leads) melihat semua.
     */
    private function priceRequestsFor($user)
    {
        $query = PriceRequest::with(['customer', 'requester', 'assignee'])->latest();

        if (!($user && $user->can('manage-sales-leads'))) {
            $query->where('requested_by', $user->id);
        }

        return $query;
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\LeadTask;
use App\Models\TechnicalRequest;
use Illuminate\Http\Request;

class SalesRequestHubController extends Controller
{
    public const TABS = ['inside-sales', 'price', 'engineer'];

    public function index(Request $request)
    {
        $tab = in_array($request->get('tab'), self::TABS, true)
            ? $request->get('tab')
            : 'inside-sales';

        $user = $request->user();

        $taskQuery = $this->tasksFor($user);
        $techBase = $this->techRequestsFor($user);

        return view('sales.requests.index', [
            'tab' => $tab,
            'tasks' => (clone $taskQuery)->paginate(10)->withQueryString(),
            'priceRequests' => (clone $techBase)->where('request_type', 'estimate')->paginate(10, ['*'], 'price_page')->withQueryString(),
            'engineerRequests' => (clone $techBase)->where('request_type', '!=', 'estimate')->paginate(10, ['*'], 'engineer_page')->withQueryString(),
            'counts' => [
                'inside-sales' => (clone $taskQuery)->count(),
                'price' => (clone $techBase)->where('request_type', 'estimate')->count(),
                'engineer' => (clone $techBase)->where('request_type', '!=', 'estimate')->count(),
            ],
        ]);
    }

    /**
     * Task inside sales dengan scoping yang sama seperti daftar LeadTask:
     * inside sales melihat miliknya, sales melihat lead miliknya,
     * management melihat semua.
     */
    private function tasksFor($user)
    {
        $query = LeadTask::with(['lead.customer', 'assignee', 'creator'])->latest();

        if ($user && $user->can('manage-inside-sales') && !$user->can('manage-sales-leads')) {
            $query->where('assigned_to', $user->id);
        }
        if ($user && $user->hasRole('sales') && !$user->can('manage-marketing') && !$user->can('manage-sales-leads')) {
            $query->whereHas('lead', fn ($l) => $l->where('assigned_to', $user->id));
        }

        return $query;
    }

    /**
     * Request teknis: pemilik request melihat miliknya sendiri,
     * management (manage-sales-leads) melihat semua.
     */
    private function techRequestsFor($user)
    {
        $query = TechnicalRequest::with(['lead.customer', 'technician'])->latest();

        if (!($user && $user->can('manage-sales-leads'))) {
            $query->where('requested_by', $user->id);
        }

        return $query;
    }
}

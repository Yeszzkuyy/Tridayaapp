<?php

namespace App\Http\Controllers;

use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\User;
use App\Services\SalesActivityService;
use App\Services\SalesService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SalesDarController extends Controller
{
    public function __construct(
        private SalesActivityService $activityService,
        private SalesService $salesService,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();

        $filters = $request->only([
            'date', 'date_from', 'date_to', 'user_id', 'customer', 'stage', 'type',
        ]);

        $activities = $this->activityService->paginate($filters, $user);

        $canViewAll = $this->activityService->canViewAll($user);

        $salesUsers = $canViewAll
            ? User::whereHas('roles', function ($q) {
                $q->where('name', 'sales')->orWhere('name', 'like', 'sales-%');
            })->orderBy('name')->get(['id', 'name'])
            : collect();

        $leadOptions = Lead::with('customer')
            ->where('assigned_to', $user->id)
            ->whereNotIn('status', ['won', 'lost'])
            ->latest('id')
            ->limit(300)
            ->get(['id', 'customer_id', 'project_name', 'status']);

        $types = FollowUp::ACTIVITY_TYPES;
        $stages = LeadController::STATUSES;

        return view('sales.dar.index', compact(
            'activities', 'salesUsers', 'leadOptions', 'types', 'stages', 'canViewAll', 'filters'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'lead_id' => 'required|exists:leads,id',
            'type' => 'required|in:'.implode(',', FollowUp::ACTIVITY_TYPES),
            'activity_date' => 'required|date|before_or_equal:today',
            'activity_time' => 'nullable|date_format:H:i',
            'description' => 'required|string|max:2000',
            'outcome' => 'nullable|string|max:2000',
            'next_follow_up_date' => 'nullable|date|after_or_equal:today',
            'next_follow_up_time' => 'nullable|date_format:H:i',
        ]);

        $lead = Lead::findOrFail($validated['lead_id']);
        $this->authorize('update', $lead);

        $occurredAt = Carbon::parse(
            $validated['activity_date'].' '.($validated['activity_time'] ?? '00:00')
        );

        $this->salesService->createFollowUp([
            'customer_id' => $lead->customer_id,
            'lead_id' => $lead->id,
            'type' => $validated['type'],
            'description' => $validated['description'],
            'follow_up_date' => $validated['activity_date'],
            'activity_time' => $validated['activity_time'] ?? null,
            'status' => 'completed',
            'completed_at' => $occurredAt,
            'outcome' => $validated['outcome'] ?? null,
            'source' => 'manual',
            'next_follow_up_date' => $validated['next_follow_up_date'] ?? null,
            'next_follow_up_time' => $validated['next_follow_up_time'] ?? null,
        ]);

        return redirect()
            ->route('sales.dar.index')
            ->with('success', __('Activity added.'));
    }
}

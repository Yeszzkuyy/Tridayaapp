<?php

namespace App\Http\Controllers;

use App\Models\LeadTask;
use App\Support\SparklineBuilder;
use Illuminate\Http\Request;

class InsideSalesController extends Controller
{
    public function dashboard(Request $request)
    {
        $user = $request->user();

        $base = LeadTask::query()->where('assigned_to', $user->id);

        $tasks = (clone $base)
            ->with(['lead.customer', 'creator', 'proposal'])
            ->withCount('items')
            ->latest()
            ->limit(100)
            ->get();

        $kpi = [
            'open' => (clone $base)->where('status', 'todo')->count(),
            'in_progress' => (clone $base)->where('status', 'in_progress')->count(),
            'quotation_ready' => (clone $base)->whereHas('proposal')->count(),
            'done' => (clone $base)->where('status', 'done')->count(),
        ];

        $spark = [
            'open' => SparklineBuilder::monthlyCounts((clone $base)->where('status', 'todo'), 'created_at'),
            'in_progress' => SparklineBuilder::monthlyCounts((clone $base)->where('status', 'in_progress'), 'created_at'),
            'quotation_ready' => SparklineBuilder::monthlyCounts((clone $base)->whereHas('proposal'), 'created_at'),
            'done' => SparklineBuilder::monthlyCounts((clone $base)->where('status', 'done'), 'created_at'),
        ];

        return view('inside-sales.dashboard', [
            'tasks' => $tasks,
            'kpi' => $kpi,
            'spark' => $spark,
        ]);
    }
}

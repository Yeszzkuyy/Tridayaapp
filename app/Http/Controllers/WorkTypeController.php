<?php

namespace App\Http\Controllers;

use App\Models\WorkType;
use Illuminate\Http\Request;

class WorkTypeController extends Controller
{
    public function index()
    {
        $workTypes = WorkType::withCount([
            'projects' => fn ($query) => $query->withTrashed(),
        ])
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        return view('admin-panel.work-types.index', compact('workTypes'));
    }

    public function create()
    {
        return view('admin-panel.work-types.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        WorkType::create($validated);

        return redirect()
            ->route('admin-panel.work-types.index')
            ->with('success', __('Work Type added successfully.'));
    }

    public function edit(WorkType $workType)
    {
        return view('admin-panel.work-types.edit', compact('workType'));
    }

    public function update(Request $request, WorkType $workType)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $workType->update($validated);

        return redirect()
            ->route('admin-panel.work-types.index')
            ->with('success', __('Work Type updated successfully.'));
    }

    public function destroy(WorkType $workType)
    {
        $projectCount = $workType->projects()->withTrashed()->count();

        if ($projectCount > 0) {
            return redirect()
                ->route('admin-panel.work-types.index')
                ->with('error', __('This Work Type is used by :count project(s). Deactivate it instead of deleting.', [
                    'count' => $projectCount,
                ]));
        }

        // Tidak ada hard delete: Work Type hanya dinonaktifkan agar riwayat
        // dan referensi lama tetap aman.
        $workType->update(['is_active' => false]);

        return redirect()
            ->route('admin-panel.work-types.index')
            ->with('success', __('Work Type deactivated successfully.'));
    }

    public function toggleActive(WorkType $workType)
    {
        $workType->update(['is_active' => ! $workType->isActive()]);

        return redirect()
            ->route('admin-panel.work-types.index')
            ->with('success', $workType->isActive()
                ? __('Work Type activated successfully.')
                : __('Work Type deactivated successfully.'));
    }
}
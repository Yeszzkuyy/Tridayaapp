<?php

namespace App\Http\Controllers;

use App\Models\WorkType;
use App\Services\AuditLogger;
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

        $workType = WorkType::create($validated);

        AuditLogger::log(
            'work_type',
            'created',
            $workType,
            [],
            ['name' => $workType->name, 'is_active' => $workType->isActive()],
            __('Work Type created'),
        );

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

        $old = ['name' => $workType->name, 'is_active' => $workType->isActive()];
        $workType->update($validated);
        $new = ['name' => $workType->name, 'is_active' => $workType->isActive()];

        if ($old !== $new) {
            AuditLogger::log('work_type', 'updated', $workType, $old, $new, __('Work Type updated'));
        }

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
        $wasActive = $workType->isActive();
        $workType->update(['is_active' => false]);

        if ($wasActive) {
            AuditLogger::log(
                'work_type',
                'deactivated',
                $workType,
                ['is_active' => true],
                ['is_active' => false],
                __('Work Type deactivated'),
            );
        }

        return redirect()
            ->route('admin-panel.work-types.index')
            ->with('success', __('Work Type deactivated successfully.'));
    }

    public function toggleActive(WorkType $workType)
    {
        $old = $workType->isActive();
        $workType->update(['is_active' => ! $old]);

        AuditLogger::log(
            'work_type',
            $workType->isActive() ? 'activated' : 'deactivated',
            $workType,
            ['is_active' => $old],
            ['is_active' => $workType->isActive()],
            $workType->isActive() ? __('Work Type activated') : __('Work Type deactivated'),
        );

        return redirect()
            ->route('admin-panel.work-types.index')
            ->with('success', $workType->isActive()
                ? __('Work Type activated successfully.')
                : __('Work Type deactivated successfully.'));
    }
}
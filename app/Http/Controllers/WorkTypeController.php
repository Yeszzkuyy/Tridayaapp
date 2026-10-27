<?php

namespace App\Http\Controllers;

use App\Models\WorkType;
use Illuminate\Http\Request;

class WorkTypeController extends Controller
{
    public function index()
    {
        $workTypes = WorkType::latest()->get();
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
        $workType->delete();
        return redirect()
            ->route('admin-panel.work-types.index')
            ->with('success', __('Work Type deleted successfully.'));
    }
}
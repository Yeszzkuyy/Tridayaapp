<?php

namespace App\Http\Controllers;

use App\Models\DocumentCategory;
use Illuminate\Http\Request;

class DocumentCategoryController extends Controller
{
    public function index()
    {
        $categories = DocumentCategory::latest()->get();
        return view('admin-panel.document-categories.index', compact('categories'));
    }

    public function create()
    {
        return view('admin-panel.document-categories.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        DocumentCategory::create($validated);

        return redirect()
            ->route('admin-panel.document-categories.index')
            ->with('success', __('Document Category added successfully.'));
    }

    public function edit(DocumentCategory $documentCategory)
    {
        return view('admin-panel.document-categories.edit', compact('documentCategory'));
    }

    public function update(Request $request, DocumentCategory $documentCategory)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $documentCategory->update($validated);

        return redirect()
            ->route('admin-panel.document-categories.index')
            ->with('success', __('Document Category updated successfully.'));
    }

    public function destroy(DocumentCategory $documentCategory)
    {
        $documentCategory->delete();
        return redirect()
            ->route('admin-panel.document-categories.index')
            ->with('success', __('Document Category deleted successfully.'));
    }
}
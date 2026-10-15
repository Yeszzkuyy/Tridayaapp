<?php

namespace App\Http\Controllers;

use App\Models\ProjectDocument;
use App\Models\DocumentCategory;
use Illuminate\Http\Request;

class DocumentRepositoryController extends Controller
{
    public function index(Request $request)
    {
        $query = ProjectDocument::with(['project.customer', 'category', 'uploader'])->latest();

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }
        if ($request->filled('document_category_id')) {
            $query->where('document_category_id', $request->document_category_id);
        }

        $documents = $query->paginate(15)->withQueryString();
        $categories = DocumentCategory::orderBy('name')->get();

        return view('teknisi.documents.index', compact('documents', 'categories'));
    }
}

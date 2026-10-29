<?php

namespace App\Http\Controllers;

use App\Services\DocumentCenterService;
use Illuminate\Http\Request;

/**
 * Menu utama Dokumen: pencarian lintas modul.
 *
 * Halaman ini tidak menyimpan file sendiri. Semua baris berasal dari tabel
 * modul aslinya lewat DocumentCenterService, dan tautan preview/download
 * memakai endpoint lama yang sudah ber-otorisasi.
 */
class DocumentCenterController extends Controller
{
    public function index(Request $request, DocumentCenterService $documents)
    {
        $user = $request->user();
        $filters = $this->filters($request);

        return view('documents.index', [
            'documents' => $documents->paginate($user, $filters),
            'options' => $documents->filterOptions($user),
            'filters' => $filters,
            'hasAnySource' => $documents->hasAnySource($user),
            'categories' => DocumentCenterService::CATEGORIES,
            'subcategories' => DocumentCenterService::SUBCATEGORIES,
            'statuses' => DocumentCenterService::STATUSES,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        $category = $request->query('category');
        $subcategory = $request->query('subcategory');
        $status = $request->query('status');
        $sort = $request->query('sort');
        $dateField = $request->query('date_field');

        return [
            'q' => filled($request->query('q')) ? mb_substr(trim((string) $request->query('q')), 0, 120) : null,
            'category' => in_array($category, DocumentCenterService::CATEGORIES, true) ? $category : null,
            'subcategory' => in_array($subcategory, DocumentCenterService::SUBCATEGORIES, true) ? $subcategory : null,
            'customer_id' => $request->integer('customer_id') ?: null,
            'project_id' => $request->integer('project_id') ?: null,
            'date_from' => filled($request->query('date_from')) ? (string) $request->query('date_from') : null,
            'date_to' => filled($request->query('date_to')) ? (string) $request->query('date_to') : null,
            'date_field' => $dateField === 'upload' ? 'upload' : 'document',
            'status' => in_array($status, DocumentCenterService::STATUSES, true) ? $status : null,
            'sort' => in_array($sort, DocumentCenterService::SORTS, true) ? $sort : 'newest',
        ];
    }
}

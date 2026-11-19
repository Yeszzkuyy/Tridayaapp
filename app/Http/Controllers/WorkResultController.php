<?php

namespace App\Http\Controllers;

use App\Models\Instalasi;
use App\Models\Survey;
use Illuminate\Http\Request;

/**
 * Halaman baca-saja "Hasil Pekerjaan" untuk Sales/Admin/Management.
 *
 * Tujuan: notifikasi hasil survey & instalasi tidak lagi melempar user
 * non-teknisi ke halaman yang butuh izin teknisi.
 */
class WorkResultController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        abort_unless($user && (
            $user->can('view-technician')
            || $user->can('manage-technician')
            || $user->can('view-sales')
            || $user->can('view-admin')
            || $user->can('manage-admin')
            || $user->hasRole('ceo')
        ), 403);

        $surveyQuery = Survey::with(['customer:id,name', 'sales:id,name', 'project:id,project_name'])
            ->latest();
        $instalasiQuery = Instalasi::with(['project.customer'])->latest();

        // Sales hanya melihat pekerjaan miliknya sendiri; teknisi/admin/
        // management/CEO melihat semua sebagai read-only.
        $seesAll = $user->can('view-technician')
            || $user->can('manage-technician')
            || $user->can('manage-admin')
            || $user->can('manage-sales-leads')
            || $user->hasRole('ceo');

        if (! $seesAll) {
            $surveyQuery->where(function ($query) use ($user) {
                $query->where('sales_user_id', $user->id)
                    ->orWhereHas('project', fn ($project) => $project->where('sales_user_id', $user->id));
            });

            $instalasiQuery->whereHas('project', fn ($project) => $project->where('sales_user_id', $user->id));
        }

        if ($request->filled('q')) {
            $term = '%'.$request->string('q')->toString().'%';
            $surveyQuery->where('title', 'like', $term);
        }

        return view('teknisi.work-results.index', [
            'surveys' => $surveyQuery->limit(20)->get(),
            'instalasis' => $instalasiQuery->limit(20)->get(),
            'canReadTechnicianPages' => $user->can('view-technician') || $user->can('manage-technician'),
            'filters' => $request->only('q'),
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Tahap 5 — BAST (Berita Acara Serah Terima).
 *
 * Admin mengisi nomor/tanggal BAST dan menautkan dokumen BAST yang sudah
 * diunggah Teknisi (project_documents). Setelah BAST + pengiriman +
 * pelunasan lengkap, Project::evaluateCommercialClosing() menutup proyek.
 */
class BastController extends Controller
{
    public function index(Request $request)
    {
        $projects = Project::query()
            ->with(['customer', 'proposal', 'bastDocument', 'invoices', 'goodsDeliveries', 'status'])
            ->when($request->filled('q'), fn ($q) => $q->where(function ($w) use ($request) {
                $w->whereLike('project_name', "%{$request->q}%")
                    ->orWhereLike('project_code', "%{$request->q}%")
                    ->orWhereLike('bast_number', "%{$request->q}%")
                    ->orWhereHas('customer', fn ($c) => $c->whereLike('name', "%{$request->q}%"));
            }))
            ->when($request->filled('status'), function ($q) use ($request) {
                if ($request->status === 'closed') {
                    $q->whereNotNull('commercial_closed_at');
                } elseif ($request->status === 'open') {
                    $q->whereNull('commercial_closed_at');
                } elseif ($request->status === 'bast') {
                    $q->whereNotNull('bast_number');
                }
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.bast.index', [
            'projects' => $projects,
            'status' => $request->input('status'),
        ]);
    }

    public function edit(Project $project)
    {
        $project->load([
            'customer', 'proposal.items', 'bastDocument',
            'documents.category', 'invoices.payments', 'goodsDeliveries',
        ]);

        return view('admin.bast.edit', compact('project'));
    }

    public function update(Request $request, Project $project)
    {
        $validated = $request->validate([
            'bast_number' => [
                'nullable', 'string', 'max:100',
                Rule::unique('projects', 'bast_number')->ignore($project->id),
            ],
            'bast_date' => 'nullable|date',
            'bast_document_id' => 'nullable|exists:project_documents,id',
            'requires_installation' => 'nullable|boolean',
            'delivery_deadline' => 'nullable|date',
        ]);

        if (! empty($validated['bast_document_id'])) {
            $belongs = $project->documents()->whereKey($validated['bast_document_id'])->exists();
            if (! $belongs) {
                throw ValidationException::withMessages([
                    'bast_document_id' => __('The selected document does not belong to this project.'),
                ]);
            }
        }

        $validated['requires_installation'] = $request->boolean('requires_installation');
        $project->update($validated);
        $project->evaluateCommercialClosing();

        return redirect()->route('admin.bast.index')
            ->with('success', __('BAST saved. Commercial closing is evaluated automatically.'));
    }
}

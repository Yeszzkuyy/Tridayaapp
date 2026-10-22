<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Support\PtAccess;
use App\Models\ProjectDocument;
use App\Models\DocumentCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProjectDocumentController extends Controller
{
    public function index(Project $project)
    {
        $this->authorize('view', $project);

        $documents = $project->documents()  // ← pakai relasi dari model Project
            ->with(['category', 'uploader'])
            ->latest()
            ->get();

        // Sales hanya boleh upload kategori Survey (survey awal); teknisi &
        // management boleh semua kategori.
        $canAll = auth()->user()->can('manage-technician') || auth()->user()->can('manage-sales-leads');
        $categories = DocumentCategory::query()
            ->when(! $canAll,
                fn ($q) => $q->where('name', 'like', '%Survey%'))
            ->orderBy('name')
            ->get();

        return view('project_documents.index', compact('project', 'documents', 'categories'));
    }

    public function store(Request $request, Project $project)
    {
        $user = auth()->user();

        // Teknisi: bebas. Sales: hanya dokumen Survey untuk project miliknya
        // (survey awal) — sesuai matriks peran di PRD project workflow.
        if (! $user->can('manage-technician') && ! $user->can('manage-sales-leads')) {
            // Sales: hanya Survey, dan hanya untuk customer di PT-nya
            // (atau project yang memang ia pegang).
            $isSalesAllowed = (int) $project->sales_user_id === (int) $user->id
                || ($project->customer && PtAccess::canWritePt($user, $project->customer->pt_group));
            abort_unless(
                $isSalesAllowed && $this->isSurveyCategory($request->input('document_category_id')),
                403,
                __('Sales can only upload Survey documents for their own project.')
            );
        }

        $request->validate([
            'file' => ['required', 'file', 'max:20480', \App\Rules\SecureFile::documents()],
            'document_category_id' => 'nullable|exists:document_categories,id',
            'notes' => 'nullable|string|max:500',
        ]);

        $file = $request->file('file');
        $originalName = \App\Rules\SecureFile::sanitizeName($file->getClientOriginalName());
        $path = $file->storeAs(
            'documents/' . $project->id,
            Str::uuid() . '.' . strtolower($file->getClientOriginalExtension()),
            'private'
        );

        $project->documents()->create([
            'file_name' => $originalName,
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'notes' => $request->notes,
            'document_category_id' => $request->document_category_id,
            'uploaded_by' => auth()->id(),
        ]);

        return redirect()
            ->back()
            ->with('success', __('Document uploaded successfully.'))
            ->with('doc_uploaded', true);
    }

    /**
     * Upload dari halaman customer: boleh tanpa project (tersimpan dengan
     * customer_id) atau ke salah satu project milik customer tersebut.
     */
    public function storeForCustomer(Request $request, \App\Models\Customer $customer)
    {
        // Teknisi ATAU siapa pun yang boleh edit customer ini (dokumen legal).
        $canUpload = auth()->user()->can('manage-technician') || auth()->user()->can('update', $customer);
        abort_unless($canUpload, 403);
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:20480', \App\Rules\SecureFile::documents()],
            'project_id' => 'nullable|exists:projects,id',
            'document_category_id' => 'nullable|exists:document_categories,id',
            'notes' => 'nullable|string|max:500',
            // File pengganti form Create Invoice / Create PO / Record Payment.
            'doc_type' => 'nullable|in:' . implode(',', \App\Models\ProjectDocument::DOC_TYPES),
        ]);

        // File invoice/PO/payment selalu milik customer, bukan milik project.
        if ($validated['doc_type'] ?? null) {
            $validated['project_id'] = null;
        }

        $project = $validated['project_id'] ?? null
            ? Project::findOrFail($validated['project_id'])
            : null;
        abort_if($project && (int) $project->customer_id !== (int) $customer->id, 422);

        if ($project) {
            // Sales hanya boleh upload dokumen Survey (survey awal) untuk
            // project miliknya; teknisi bebas.
            if (! auth()->user()->can('manage-technician') && ! auth()->user()->can('manage-sales-leads')) {
                $user = auth()->user();
                $isSalesAllowed = (int) $project->sales_user_id === (int) $user->id
                    || ($project->customer && PtAccess::canWritePt($user, $project->customer->pt_group));
                abort_unless(
                    $isSalesAllowed && $this->isSurveyCategory($validated['document_category_id'] ?? null),
                    403,
                    __('Sales can only upload Survey documents for their own project.')
                );
            }
        }

        $file = $request->file('file');
        $originalName = \App\Rules\SecureFile::sanitizeName($file->getClientOriginalName());
        $path = $file->storeAs(
            'documents/customer-' . $customer->id,
            Str::uuid() . '.' . strtolower($file->getClientOriginalExtension()),
            'private'
        );

        ProjectDocument::create([
            'project_id' => $project?->id,
            'customer_id' => $customer->id,
            'doc_type' => $validated['doc_type'] ?? null,
            'file_name' => $originalName,
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'notes' => $validated['notes'] ?? null,
            'document_category_id' => $validated['document_category_id'] ?? null,
            'uploaded_by' => auth()->id(),
        ]);

        return redirect()
            ->back()
            ->with('success', __('Document uploaded successfully.'))
            ->with('doc_uploaded', true);
    }

    /**
     * Pemilik dokumen untuk otorisasi: project-nya, atau customer-nya
     * bila dokumen umum tanpa project.
     */
    private function ownerOf(ProjectDocument $document)
    {
        return $document->project ?? $document->customer;
    }

    public function preview(ProjectDocument $document)
    {
        $owner = $this->ownerOf($document);
        abort_if(! $owner, 404);
        $this->authorize('view', $owner);

        // MIME dari isi file + hanya tipe aman yang inline (svg ikut download).
        return \App\Rules\SecureFile::fileResponse('private', $document->file_path, $document->file_name);
    }

    public function download(ProjectDocument $document)
    {
        $owner = $this->ownerOf($document);
        abort_if(! $owner, 404);
        $this->authorize('view', $owner);

        // Cek apakah file ada di storage
        if (!Storage::disk('private')->exists($document->file_path)) {
            abort(404, 'File tidak ditemukan.');
        }

        return Storage::disk('private')->download($document->file_path, $document->file_name);
    }

    /**
     * Ubah nama file (ekstensi asli dipertahankan demi keamanan).
     * Izin mengikuti aturan hapus: dokumen project = teknisi,
     * dokumen umum = teknisi ATAU pengedit customer.
     */
    public function rename(Request $request, ProjectDocument $document)
    {
        if ($document->project) {
            $this->authorize('update', $document->project);
        } else {
            $owner = $document->customer;
            abort_if(! $owner, 404);
            abort_unless(
                auth()->user()->can('manage-technician') || auth()->user()->can('update', $owner),
                403
            );
        }

        $validated = $request->validate(['name' => 'required|string|max:255']);

        $base = trim(pathinfo(\App\Rules\SecureFile::sanitizeName($validated['name']), PATHINFO_FILENAME));
        if ($base === '') {
            $base = pathinfo($document->file_name, PATHINFO_FILENAME);
        }
        $ext = strtolower(pathinfo($document->file_name, PATHINFO_EXTENSION));

        $document->update(['file_name' => $base . ($ext ? '.' . $ext : '')]);

        return redirect()
            ->back()
            ->with('success', __('File renamed successfully.'))
            ->with('doc_uploaded', true);
    }

    public function destroy(ProjectDocument $document)
    {
        if ($document->project) {
            $this->authorize('update', $document->project);
        } else {
            // Dokumen umum: teknisi ATAU pengedit customer-nya.
            $owner = $document->customer;
            abort_if(! $owner, 404);
            abort_unless(
                auth()->user()->can('manage-technician') || auth()->user()->can('update', $owner),
                403
            );
        }

        // Hapus file dari storage kalau ada
        if ($document->file_path && Storage::disk('private')->exists($document->file_path)) {
            Storage::disk('private')->delete($document->file_path);
        }

        // File payment dipakai juga sebagai bukti transfer (path sama).
        // Lepas tautannya supaya tombol Proof tidak menunjuk file yang hilang.
        $linked = $document->documentable;
        if ($linked instanceof \App\Models\Payment && $linked->proof_file === $document->file_path) {
            $linked->update(['proof_file' => null]);
        }

        // Force delete (hapus permanen dari database)
        $document->forceDelete();

        return redirect()
            ->back()
            ->with('success', __('Document deleted successfully.'));
    }

    /** Kategori Survey (mis. "Report Survey") — dipakai untuk batas upload sales. */
    private function isSurveyCategory($categoryId): bool
    {
        if (empty($categoryId)) {
            return false;
        }

        $name = DocumentCategory::whereKey($categoryId)->value('name');

        return $name !== null && str_contains(mb_strtolower($name), 'survey');
    }
}

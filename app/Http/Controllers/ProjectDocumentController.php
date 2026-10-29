<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Project;
use App\Support\PtAccess;
use App\Support\DocumentAccess;
use App\Services\DocumentActivityLogger;
use App\Models\ProjectDocument;
use App\Models\DocumentCategory;
use App\Services\ProjectDocumentCleanup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

class ProjectDocumentController extends Controller
{
    private function hasVersioning(): bool
    {
        return Schema::hasColumn('project_documents', 'document_group_id')
            && Schema::hasColumn('project_documents', 'version');
    }

    public function index(Project $project)
    {
        $this->authorize('view', $project);

        $query = $project->documents()
            ->with(['category', 'uploader'])
            // Dokumen transaksi (invoice/po/payment) bukan dokumen proyek;
            // dokumen hasil modul (survey/sizing/instalasi) ikut tampil.
            ->where(function ($q) {
                $q->whereNull('doc_type')
                    ->orWhereNotIn('doc_type', ProjectDocument::DOC_TYPES);
            })
            ->whereNull('deleted_at');

        if (request()->filled('search')) {
            $query->where('file_name', 'like', '%'.request('search').'%');
        }

        if (request()->filled('category')) {
            $query->where('document_category_id', request('category'));
        }

        $sort = request('sort', 'latest');
        $query->orderBy($sort === 'oldest' ? 'created_at' : 'created_at', $sort === 'oldest' ? 'asc' : 'desc');

        $documents = $query->paginate(15)->withQueryString();

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

        if (! $user->can('manage-technician') && ! $user->can('manage-sales-leads')) {
            $isSalesAllowed = (int) ($project->sales_user_id ?? 0) === (int) $user->id
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

        $attributes = [
            'project_id' => $project->id,
            'customer_id' => $project->customer_id,
            'title' => $originalName,
            'file_name' => $originalName,
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'notes' => $request->notes,
            'document_category_id' => $request->document_category_id,
            'uploaded_by' => auth()->id(),
        ];

        if ($this->hasVersioning()) {
            $attributes['document_group_id'] = Str::uuid();
            $attributes['version'] = 1;
        }

        $project->documents()->create($attributes);

        return redirect()
            ->back()
            ->with('success', __('Document uploaded successfully.'))
            ->with('doc_uploaded', true);
    }

    public function storeForCustomer(Request $request, \App\Models\Customer $customer)
    {
        $canUpload = auth()->user()->can('manage-technician')
            || auth()->user()->can('manage-admin')
            || auth()->user()->can('update', $customer);
        abort_unless($canUpload, 403);
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:20480', \App\Rules\SecureFile::documents()],
            'project_id' => 'nullable|exists:projects,id',
            'document_category_id' => 'nullable|exists:document_categories,id',
            'notes' => 'nullable|string|max:500',
            'doc_type' => 'nullable|in:' . implode(',', \App\Models\ProjectDocument::DOC_TYPES),
            'transaction_id' => 'nullable|integer',
        ]);

        if ($validated['doc_type'] ?? null) {
            $validated['project_id'] = null;
        }

        $project = $validated['project_id'] ?? null
            ? Project::findOrFail($validated['project_id'])
            : null;
        abort_if($project && (int) $project->customer_id !== (int) $customer->id, 422);

        if ($project) {
            if (! auth()->user()->can('manage-technician') && ! auth()->user()->can('manage-sales-leads')) {
                $user = auth()->user();
                $isSalesAllowed = (int) ($project->sales_user_id ?? 0) === (int) $user->id
                    || ($project->customer && PtAccess::canWritePt($user, $project->customer->pt_group));
                abort_unless(
                    $isSalesAllowed && $this->isSurveyCategory($validated['document_category_id'] ?? null),
                    403,
                    __('Sales can only upload Survey documents for their own project.')
                );
            }
        }

        // Bila admin memilih jenis transaksi, tautkan ke record aslinya supaya
        // nomor, sumber, dan aturan aksesnya konsisten (bukan dokumen "invoice"
        // tanpa transaksi). Divalidasi SEBELUM file disimpan agar tidak ada
        // file yatim saat transaksi tidak valid.
        $documentable = $this->resolveUploadDocumentable(
            $customer,
            $validated['doc_type'] ?? null,
            (int) ($validated['transaction_id'] ?? 0)
        );

        $file = $request->file('file');
        $originalName = \App\Rules\SecureFile::sanitizeName($file->getClientOriginalName());
        $path = $file->storeAs(
            'documents/customer-' . $customer->id,
            Str::uuid() . '.' . strtolower($file->getClientOriginalExtension()),
            'private'
        );

        $attributes = [
            'project_id' => $project?->id,
            'customer_id' => $customer->id,
            'doc_type' => $validated['doc_type'] ?? null,
            'documentable_type' => $documentable?->getMorphClass(),
            'documentable_id' => $documentable?->getKey(),
            'title' => $originalName,
            'file_name' => $originalName,
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'notes' => $validated['notes'] ?? null,
            'document_category_id' => $validated['document_category_id'] ?? null,
            'uploaded_by' => auth()->id(),
        ];

        if ($this->hasVersioning()) {
            $attributes['document_group_id'] = Str::uuid();
            $attributes['version'] = 1;
        }

        ProjectDocument::create($attributes);

        return redirect()
            ->back()
            ->with('success', __('Document uploaded successfully.'))
            ->with('doc_uploaded', true);
    }

    /**
     * Validasi record transaksi opsional saat upload dokumen berjenis.
     * Harus milik customer yang sama; null bila tidak memilih transaksi.
     */
    private function resolveUploadDocumentable(\App\Models\Customer $customer, ?string $docType, int $transactionId): ?object
    {
        if ($transactionId <= 0) {
            return null;
        }

        if (! $docType) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'transaction_id' => __('Choose a document type before linking a transaction.'),
            ]);
        }

        $documentable = match ($docType) {
            'invoice' => \App\Models\Invoice::query()
                ->whereKey($transactionId)
                ->where('customer_id', $customer->id)
                ->first(),
            'po' => \App\Models\PurchaseOrder::query()
                ->whereKey($transactionId)
                ->where('customer_id', $customer->id)
                ->first(),
            'payment' => \App\Models\Payment::query()
                ->whereKey($transactionId)
                ->whereHas('invoice', fn ($query) => $query->where('customer_id', $customer->id))
                ->first(),
            default => null,
        };

        if (! $documentable) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'transaction_id' => __('The selected transaction does not belong to this customer.'),
            ]);
        }

        return $documentable;
    }

    /**
     * Dokumen milik modul (survey/sizing/instalasi) dikelola dari menu
     * modulnya: file yang sama dipakai bersama, jadi mutasi lewat menu
     * dokumen membuat file dan daftar tidak sinkran. Berlaku untuk semua
     * role — termasuk super-admin yang di Gate::before mem-bypass policy.
     */
    private function assertNotModuleOwned(ProjectDocument $document): void
    {
        abort_if(
            $document->isModuleDocument(),
            403,
            __('Dokumen ini dikelola dari menu modulnya (Survey/Sizing/Instalasi).')
        );
    }

    public function storeVersion(Request $request, ProjectDocument $document)
    {
        $this->assertNotModuleOwned($document);
        abort_unless(Gate::allows('uploadVersion', $document), 403);

        if (! $this->hasVersioning()) {
            return back()->withErrors(['file' => __('Sistem versi belum aktif. Hubungi administrator.')]);
        }

        $request->validate([
            'file' => ['required', 'file', 'max:20480', \App\Rules\SecureFile::documents()],
            'notes' => 'nullable|string|max:500',
        ]);

        $file = $request->file('file');
        $originalName = \App\Rules\SecureFile::sanitizeName($file->getClientOriginalName());
        $baseDir = $document->project_id
            ? 'documents/' . $document->project_id
            : 'documents/customer-' . $document->customer_id;
        $path = $file->storeAs(
            $baseDir,
            Str::uuid() . '.' . strtolower($file->getClientOriginalExtension()),
            'private'
        );

        $nextVersion = ($document->version ?? 1) + 1;

        $newDocument = $document->replicate();
        $newDocument->version = $nextVersion;
        $newDocument->file_name = $originalName;
        $newDocument->file_path = $path;
        $newDocument->file_size = $file->getSize();
        $newDocument->mime_type = $file->getMimeType() ?: 'application/octet-stream';
        $newDocument->notes = $request->notes;
        $newDocument->uploaded_by = auth()->id();
        $newDocument->created_at = now();
        $newDocument->updated_at = now();
        $newDocument->save();

        return redirect()
            ->back()
            ->with('success', __('Versi baru berhasil diunggah.'))
            ->with('doc_uploaded', true);
    }

    private function ownerOf(ProjectDocument $document)
    {
        return $document->project ?? $document->customer;
    }

    public function preview(ProjectDocument $document)
    {
        abort_unless(Gate::allows('view', $document), 403);

        return \App\Rules\SecureFile::fileResponse('private', $document->file_path, $document->file_name);
    }

    public function download(ProjectDocument $document)
    {
        abort_unless(Gate::allows('view', $document), 403);

        if (!Storage::disk('private')->exists($document->file_path)) {
            abort(404, 'File tidak ditemukan.');
        }

        return Storage::disk('private')->download($document->file_path, $document->file_name);
    }

    public function update(Request $request, ProjectDocument $document)
    {
        $this->assertNotModuleOwned($document);
        abort_unless(Gate::allows('update', $document), 403);

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'document_category_id' => 'nullable|exists:document_categories,id',
            'notes' => 'nullable|string|max:500',
        ]);

        $updates = [];
        if ($request->has('name')) {
            $base = trim(pathinfo(\App\Rules\SecureFile::sanitizeName($validated['name']), PATHINFO_FILENAME));
            if ($base !== '') {
                $ext = strtolower(pathinfo($document->file_name, PATHINFO_EXTENSION));
                $updates['file_name'] = $base . ($ext ? '.' . $ext : '');
            }
        }
        if ($request->has('document_category_id')) {
            $updates['document_category_id'] = $validated['document_category_id'];
        }
        if ($request->has('notes')) {
            $updates['notes'] = $validated['notes'];
        }

        if (! empty($updates)) {
            $document->update($updates);
        }

        return redirect()
            ->back()
            ->with('success', __('Metadata dokumen berhasil diperbarui.'));
    }

    public function rename(Request $request, ProjectDocument $document)
    {
        $request->merge(['name' => $request->input('name')]);
        return $this->update($request, $document);
    }

    public function archive(ProjectDocument $document, ProjectDocumentCleanup $cleanup)
    {
        abort_unless(Gate::allows('archive', $document), 403);

        // Dokumen modul: lepas tautan file di record modulnya dulu, supaya
        // laporan benar-benar pindah ke Trash (bukan tampil dobel).
        if ($document->isModuleDocument()) {
            $cleanup->releaseModuleFile($document);
        }

        $document->forceFill(['deleted_by' => auth()->id()])->save();
        $document->delete();

        DocumentActivityLogger::log($document, 'archived');

        return redirect()
            ->back()
            ->with('success', __('Dokumen dipindahkan ke Trash. Bisa dipulihkan dalam :days hari.', [
                'days' => ProjectDocument::TRASH_RETENTION_DAYS,
            ]));
    }

    public function restore(ProjectDocument $document, ProjectDocumentCleanup $cleanup)
    {
        abort_unless(Gate::allows('restore', $document), 403);

        if (! $cleanup->restore($document)) {
            return redirect()
                ->back()
                ->withErrors([
                    'document' => __('Tidak bisa restore: modul sudah punya file lain atau record modulnya sudah tidak ada.'),
                ]);
        }

        return redirect()
            ->back()
            ->with('success', __('Dokumen berhasil dipulihkan.'));
    }

    public function destroy(ProjectDocument $document, ProjectDocumentCleanup $cleanup)
    {
        abort_unless(Gate::allows('forceDelete', $document), 403);

        // Hapus permanen dokumen utama hanya lewat menu Trash (atau otomatis
        // setelah 14 hari). Route ini hanya untuk versi lama di riwayat versi.
        $isOldVersion = $document->document_group_id
            && ProjectDocument::where('document_group_id', $document->document_group_id)
                ->where('version', '>', (int) ($document->version ?? 1))
                ->exists();

        abort_unless($isOldVersion, 403);

        $cleanup->forceDelete($document);

        return redirect()
            ->back()
            ->with('success', __('Dokumen dihapus permanen.'));
    }

    public function versionHistory(ProjectDocument $document)
    {
        abort_unless(Gate::allows('view', $document), 403);

        if (! $this->hasVersioning()) {
            return view('project_documents.version-history', [
                'document' => $document->load(['category', 'uploader']),
                'versions' => collect([$document]),
            ]);
        }

        $groupId = $document->document_group_id;
        $versions = $groupId
            ? ProjectDocument::where('document_group_id', $groupId)
                ->with(['category', 'uploader'])
                ->orderBy('version', 'asc')
                ->get()
            : collect([$document->load(['category', 'uploader'])]);

        return view('project_documents.version-history', compact('document', 'versions'));
    }

    public function archiveIndex(Customer $customer)
    {
        $this->authorize('viewAny', ProjectDocument::class);

        $archivedDocuments = DocumentAccess::applyTrashVisibility(
            ProjectDocument::onlyTrashed()
                ->with(['project', 'category', 'uploader'])
                ->where('customer_id', $customer->id),
            auth()->user()
        )
            ->orderBy('deleted_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        return view('project_documents.archive', compact('customer', 'archivedDocuments'));
    }

    private function isSurveyCategory($categoryId): bool
    {
        if (empty($categoryId)) {
            return false;
        }

        $name = DocumentCategory::whereKey($categoryId)->value('name');

        return $name !== null && str_contains(mb_strtolower($name), 'survey');
    }
}

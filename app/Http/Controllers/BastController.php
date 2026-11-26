<?php

namespace App\Http\Controllers;

use App\Models\DocumentCategory;
use App\Models\Instalasi;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\User;
use App\Notifications\BastReadyNotification;
use App\Rules\SecureFile;
use App\Services\AuditLogger;
use App\Services\Project\ProjectWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Tahap 5 — BAST (Berita Acara Serah Terima).
 *
 * Alur:
 * 1. Teknisi menandai instalasi selesai secara teknis dan menyerahkan BAST
 *    fisik yang sudah ditandatangani kepada Admin.
 * 2. Admin mengunggah file BAST. Sistem tidak membaca atau memverifikasi
 *    tanda tangan di dalam file.
 * 3. File BAST menjadi penanda `bast_ready_at`; Finance bisa melihat dokumen.
 * 4. Setelah BAST + pengiriman + pelunasan lengkap,
 *    Project::evaluateCommercialClosing() menutup proyek.
 */
class BastController extends Controller
{
    private function bastFileRule(): SecureFile
    {
        return new SecureFile(
            ['pdf', 'doc', 'docx'],
            [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ],
        );
    }

    public function index(Request $request)
    {
        $projects = Project::query()
            ->with([
                'customer', 'proposal', 'bastDocument', 'invoices', 'goodsDeliveries', 'status',
                'instalasis' => fn ($q) => $q->latest('id'),
            ])
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
                    $q->whereNotNull('bast_document_id');
                } elseif ($request->status === 'ready') {
                    $q->whereNotNull('bast_ready_at');
                } elseif ($request->status === 'waiting_signature') {
                    $q->whereNull('bast_ready_at');
                }
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.bast.index', [
            'projects' => $projects,
            'status' => $request->input('status'),
            'installationJobs' => Instalasi::query()
                ->with(['customer', 'project', 'technician', 'technicalRequest'])
                ->whereNull('project_id')
                ->latest()
                ->paginate(10, ['*'], 'instalasi_page')
                ->withQueryString(),
        ]);
    }

    public function edit(Project $project)
    {
        abort_unless(auth()->user()?->can('manage-admin'), 403);

        $project->load([
            'customer', 'proposal.items', 'bastDocument',
            'documents.category', 'invoices.payments', 'goodsDeliveries',
            'instalasis' => fn ($q) => $q->latest('id'),
        ]);

        return view('admin.bast.edit', compact('project'));
    }

    public function update(Request $request, Project $project)
    {
        abort_unless(auth()->user()?->can('manage-admin'), 403);

        $validated = $request->validate([
            'bast_number' => [
                'nullable', 'string', 'max:100',
                Rule::unique('projects', 'bast_number')->ignore($project->id),
            ],
            'bast_date' => 'nullable|date',
            'bast_file' => [
                Rule::requiredIf(fn () => ! $project->bast_document_id),
                'file',
                'mimes:pdf,doc,docx',
                'max:10240',
                $this->bastFileRule(),
            ],
            'requires_installation' => 'nullable|boolean',
            'delivery_deadline' => 'nullable|date',
        ]);

        $file = $request->file('bast_file');
        unset($validated['bast_file']);

        $wasReady = filled($project->bast_ready_at);
        $oldBast = AuditLogger::snapshot($project, [
            'bast_number',
            'bast_date',
            'bast_document_id',
            'bast_ready_at',
        ]);

        if ($file) {
            $validated['bast_document_id'] = $this->storeBastFile($project, $file)->id;
        }

        $validated['requires_installation'] = $request->boolean('requires_installation');
        $project->update($validated);
        $project->refresh();

        $project->refreshBastReady();
        $project->refresh();

        // BAST adalah pemicu: hitung ulang progress/status, lalu evaluasi Closing.
        app(ProjectWorkflowService::class)->sync($project);
        $project->refresh();
        $project->evaluateCommercialClosing();

        if ($project->isBastReady() && ! $wasReady) {
            $this->notifyFinance($project);
        }

        AuditLogger::log(
            'project',
            'bast_updated',
            $project,
            $oldBast,
            AuditLogger::snapshot($project, [
                'bast_number',
                'bast_date',
                'bast_document_id',
                'bast_ready_at',
            ]),
            __('BAST file updated'),
            ['project_id' => $project->id],
        );

        return redirect()->route('admin.bast.index')
            ->with('success', __('BAST saved. Workflow and commercial closing are evaluated automatically.'));
    }

    /**
     * Simpan BAST yang sudah ditandatangani sebagai dokumen project.
     * Bila BAST sebelumnya sudah tertaut, baris yang sama diperbarui
     * (file lama dihapus setelah DB sukses) supaya tidak menumpuk.
     */
    private function storeBastFile(Project $project, $file): ProjectDocument
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: 'pdf');
        $path = $file->storeAs(
            'bast/'.$project->id,
            Str::uuid()->toString().'.'.$extension,
            'private'
        );

        if (! $path) {
            throw ValidationException::withMessages([
                'bast_file' => __('Failed to store the BAST file. Please try again.'),
            ]);
        }

        $category = DocumentCategory::firstOrCreate(['name' => 'BAST']);
        $existing = $project->bastDocument;
        $oldPath = $existing?->file_path;

        $payload = [
            'project_id' => $project->id,
            'customer_id' => $project->customer_id,
            'doc_type' => 'bast',
            'document_category_id' => $category->id,
            'file_name' => SecureFile::sanitizeName($file->getClientOriginalName()),
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'uploaded_by' => auth()->id(),
        ];

        if ($existing && (int) $existing->project_id === (int) $project->id) {
            $existing->update($payload);
            $document = $existing;
        } else {
            $document = ProjectDocument::create($payload);
        }

        if ($oldPath && $oldPath !== $path) {
            Storage::disk('private')->delete($oldPath);
        }

        return $document;
    }

    /**
     * Sajikan dokumen BAST ke Admin & Finance (read-only). Route ini
     * sengaja terpisah dari route dokumen project agar Finance — yang
     * tidak memegang view-customer — tetap bisa membuka BAST.
     */
    public function document(Project $project)
    {
        $document = $project->bastDocument;

        abort_unless($document && filled($document->file_path), 404);
        abort_unless((int) $document->project_id === (int) $project->id, 404);

        return SecureFile::fileResponse('private', $document->file_path, $document->file_name);
    }

    /**
     * Beri tahu Finance bahwa BAST sudah lengkap dan siap dipakai untuk
     * proses berikutnya (invoice/pelunasan).
     */
    private function notifyFinance(Project $project): void
    {
        $recipients = User::whereHas('roles', fn ($q) => $q->where('name', 'finance'))->get();

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new BastReadyNotification($project));
        }
    }
}

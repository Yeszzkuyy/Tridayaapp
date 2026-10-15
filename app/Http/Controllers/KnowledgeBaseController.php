<?php

namespace App\Http\Controllers;

use App\Enums\KnowledgeBaseCategory;
use App\Models\KnowledgeBaseDocument;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Laravel\Ai\Files;
use Laravel\Ai\Stores;
use Throwable;

class KnowledgeBaseController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('manage-admin');

        $documents = KnowledgeBaseDocument::with(['uploader', 'project'])
            ->when(
                $request->filled('category'),
                fn ($query) => $query->where('category', $request->query('category'))
            )
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where('status', $request->query('status'))
            )
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('knowledge-base.index', [
            'documents' => $documents,
            'categories' => KnowledgeBaseCategory::cases(),
            'projects' => Project::orderBy('project_name')->limit(200)->get(['id', 'project_name']),
            'filters' => $request->only(['category', 'status']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('manage-admin');

        $validated = $request->validate([
            'document' => ['required', 'file', 'max:'.(config('ai.knowledge_base.max_file_size') / 1024), \App\Rules\SecureFile::knowledgeBase()],
            'category' => ['required', 'string', Rule::enum(KnowledgeBaseCategory::class)],
            'project_id' => ['nullable', 'exists:projects,id'],
        ]);

        $file = $request->file('document');
        $mime = $file->getMimeType();

        if (! in_array($mime, config('ai.knowledge_base.allowed_mime_types'), true)) {
            return back()->withErrors(['document' => "Format dokumen tidak didukung ($mime). Gunakan PDF, teks, atau dokumen Office."]);
        }

        $storeId = config('ai.knowledge_base.store_id');

        if (blank($storeId)) {
            return back()->withErrors(['document' => 'Knowledge Base belum dikonfigurasi. Jalankan `php artisan knowledge-base:init-store` di server.']);
        }

        try {
            $store = Stores::get($storeId);

            $safeName = \App\Rules\SecureFile::sanitizeName($file->getClientOriginalName());

            $added = $store->add(
                $file,
                metadata: [
                    'category' => $validated['category'],
                    'original_name' => $safeName,
                    'uploaded_by' => (string) $request->user()->id,
                    'project_id' => (string) ($validated['project_id'] ?? ''),
                ],
            );

            try {
                KnowledgeBaseDocument::create([
                    'original_name' => $safeName,
                    'category' => $validated['category'],
                    'project_id' => $validated['project_id'] ?? null,
                    'user_id' => $request->user()->id,
                    'store_id' => $storeId,
                    'provider_file_id' => $added->fileId(),
                    'provider_document_id' => $added->id(),
                    'mime' => $mime,
                    'size' => $file->getSize(),
                    'status' => 'indexing',
                ]);
            } catch (Throwable $dbException) {
                Log::error('Knowledge base: simpan metadata gagal, membersihkan file provider.', ['exception' => $dbException]);

                try {
                    $store->remove($added->id(), deleteFile: true);
                } catch (Throwable $cleanupException) {
                    Log::error('Knowledge base: gagal membersihkan file provider setelah error database.', ['exception' => $cleanupException]);
                }

                throw $dbException;
            }

            return back()->with('success', 'Dokumen berhasil diunggah dan sedang di-index.');
        } catch (Throwable $e) {
            Log::error('Knowledge base upload gagal: '.$e->getMessage(), ['exception' => $e]);

            return back()->withErrors(['document' => 'Upload gagal. Pastikan API key Gemini aktif dan coba lagi.']);
        }
    }

    public function sync(Request $request): RedirectResponse
    {
        $this->authorize('manage-admin');

        $storeId = config('ai.knowledge_base.store_id');

        if (blank($storeId)) {
            return back()->withErrors(['sync' => 'Knowledge Base belum dikonfigurasi. Jalankan `php artisan knowledge-base:init-store` dulu.']);
        }

        try {
            $counts = Stores::get($storeId)->fileCounts;

            $updated = 0;

            if ($counts->failed > 0) {
                $updated += KnowledgeBaseDocument::where('store_id', $storeId)
                    ->where('status', 'indexing')
                    ->update(['status' => 'failed']);
            }

            if ($counts->failed === 0 && $counts->pending === 0) {
                $updated += KnowledgeBaseDocument::where('store_id', $storeId)
                    ->where('status', 'indexing')
                    ->update(['status' => 'ready']);
            }

            return back()->with('success', "Status indexing disinkronkan ({$updated} dokumen diperbarui). Completed: {$counts->completed}, pending: {$counts->pending}, failed: {$counts->failed}.");
        } catch (Throwable $e) {
            Log::error('Knowledge base sync status gagal: '.$e->getMessage(), ['exception' => $e]);

            return back()->withErrors(['sync' => 'Gagal menyinkronkan status indexing.']);
        }
    }

    public function destroy(Request $request, KnowledgeBaseDocument $document): RedirectResponse
    {
        $this->authorize('manage-admin');

        $storeId = config('ai.knowledge_base.store_id');

        if (blank($storeId)) {
            return back()->withErrors(['delete' => 'Knowledge Base belum dikonfigurasi.']);
        }

        try {
            $store = Stores::get($storeId);

            $removed = false;

            if ($document->provider_document_id) {
                try {
                    $store->remove($document->provider_document_id);
                    $removed = true;
                } catch (Throwable $e) {
                    Log::warning('Knowledge base: hapus via document id gagal, coba fallback file id.', ['exception' => $e]);
                }
            }

            if (! $removed && $document->provider_file_id) {
                $store->remove($document->provider_file_id);
            }

            if ($document->provider_file_id) {
                Files::delete($document->provider_file_id);
            }

            $document->delete();

            return back()->with('success', 'Dokumen dihapus dari Knowledge Base.');
        } catch (Throwable $e) {
            Log::error('Knowledge base hapus dokumen gagal: '.$e->getMessage(), ['exception' => $e]);

            return back()->withErrors(['delete' => 'Gagal menghapus dokumen dari penyedia. Coba lagi.']);
        }
    }
}

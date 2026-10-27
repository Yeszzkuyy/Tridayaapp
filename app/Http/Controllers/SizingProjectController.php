<?php

namespace App\Http\Controllers;

use App\Models\SizingProject;
use App\Models\Project;
use App\Rules\SecureFile;
use App\Services\DocumentRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SizingProjectController extends Controller
{
    /**
     * Batas unggah lampiran hasil sizing (sama dengan dokumen lain).
     */
    private function attachmentRules(): array
    {
        return ['file', 'max:20480', SecureFile::documents()];
    }

    public function index(Request $request)
    {
        $query = SizingProject::with('project.customer')->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        $sizings = $query->paginate(15)->withQueryString();
        $projects = Project::with('customer:id,name')->orderBy('project_name')->limit(200)->get(['id', 'project_name', 'customer_id']);

        return view('teknisi.sizing-projects.index', compact('sizings', 'projects'));
    }

    public function create(Request $request)
    {
        $projects = Project::with('customer:id,name')->orderBy('project_name')->limit(200)->get(['id', 'project_name', 'customer_id']);
        $selectedProject = $request->query('project_id');

        return view('teknisi.sizing-projects.create', compact('projects', 'selectedProject'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'sales_pic' => 'nullable|string|max:255',
            'customer_needs' => 'nullable|string',
            'recommendation' => 'nullable|string',
            'specifications' => 'nullable|string',
            'quantity' => 'nullable|integer|min:0',
            'topology' => 'nullable|string',
            'technical_notes' => 'nullable|string',
            'status' => 'required|in:draft,in_progress,waiting_approval,completed',
            'attachments' => 'nullable|array|max:10',
            'attachments.*' => $this->attachmentRules(),
        ]);

        $files = $request->file('attachments', []);
        unset($validated['attachments']);

        $sizingProject = SizingProject::create($validated);

        if ($files) {
            $this->storeAttachments($sizingProject, $files);
        }

        return redirect()->route('teknisi.sizing-projects.index')
            ->with('success', __('Sizing project added successfully.'));
    }

    public function show(SizingProject $sizingProject)
    {
        $sizingProject->load(['project.customer', 'documents']);

        return view('teknisi.sizing-projects.show', compact('sizingProject'));
    }

    public function edit(SizingProject $sizingProject)
    {
        $projects = Project::with('customer:id,name')->orderBy('project_name')->limit(200)->get(['id', 'project_name', 'customer_id']);

        return view('teknisi.sizing-projects.edit', compact('sizingProject', 'projects'));
    }

    public function update(Request $request, SizingProject $sizingProject)
    {
        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'sales_pic' => 'nullable|string|max:255',
            'customer_needs' => 'nullable|string',
            'recommendation' => 'nullable|string',
            'specifications' => 'nullable|string',
            'quantity' => 'nullable|integer|min:0',
            'topology' => 'nullable|string',
            'technical_notes' => 'nullable|string',
            'status' => 'required|in:draft,in_progress,waiting_approval,completed',
            'attachments' => 'nullable|array|max:10',
            'attachments.*' => $this->attachmentRules(),
            'remove_attachments' => 'nullable|array',
            'remove_attachments.*' => 'string|max:255',
        ]);

        $files = $request->file('attachments', []);
        $remove = (array) ($validated['remove_attachments'] ?? []);
        unset($validated['attachments'], $validated['remove_attachments']);

        $sizingProject->update($validated);

        if ($remove) {
            $this->removeAttachments($sizingProject, $remove);
        }

        if ($files) {
            $this->storeAttachments($sizingProject, $files);
        }

        return redirect()->route('teknisi.sizing-projects.show', $sizingProject)
            ->with('success', __('Sizing project updated successfully.'));
    }

    public function destroy(SizingProject $sizingProject)
    {
        // Dokumen turunannya ikut dibersihkan supaya tidak menggantung.
        DocumentRegistry::forget($sizingProject);

        foreach ($sizingProject->attachments ?? [] as $attachment) {
            $path = is_array($attachment) ? ($attachment['file_path'] ?? null) : $attachment;
            if ($path) {
                Storage::disk('private')->delete($path);
            }
        }

        $sizingProject->delete();

        return redirect()->route('teknisi.sizing-projects.index')
            ->with('success', __('Sizing project deleted successfully.'));
    }

    /**
     * Simpan lampiran baru ke disk privat + daftar dokumen.
     *
     * @param  array<int, \Illuminate\Http\UploadedFile>  $files
     */
    private function storeAttachments(SizingProject $sizingProject, array $files): void
    {
        $stored = $sizingProject->attachments ?? [];

        foreach ($files as $file) {
            $path = $file->storeAs(
                'sizings/' . $sizingProject->id . '/files',
                Str::uuid()->toString() . '.' . strtolower($file->guessExtension() ?: 'dat'),
                'private'
            );

            if (! $path) {
                continue;
            }

            $stored[] = [
                'file_path' => $path,
                'file_name' => SecureFile::sanitizeName($file->getClientOriginalName()),
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            ];
        }

        $sizingProject->attachments = $stored;
        $sizingProject->save();

        // Satu file = satu dokumen: ikut tampil di semua menu dokumen.
        DocumentRegistry::sync($sizingProject);
    }

    /**
     * Buang lampiran yang dicentang untuk dihapus (file + daftar dokumen).
     */
    private function removeAttachments(SizingProject $sizingProject, array $remove): void
    {
        $keep = [];

        foreach ($sizingProject->attachments ?? [] as $attachment) {
            $path = is_array($attachment) ? ($attachment['file_path'] ?? null) : $attachment;

            if ($path && in_array($path, $remove, true)) {
                Storage::disk('private')->delete($path);
                continue;
            }

            $keep[] = $attachment;
        }

        $sizingProject->attachments = $keep;
        $sizingProject->save();

        DocumentRegistry::sync($sizingProject);
    }
}

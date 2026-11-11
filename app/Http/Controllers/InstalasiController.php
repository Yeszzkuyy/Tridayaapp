<?php

namespace App\Http\Controllers;

use App\Models\DocumentCategory;
use App\Models\Instalasi;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\User;
use App\Notifications\InstallationCompletedNotification;
use App\Rules\SecureFile;
use App\Services\DocumentRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class InstalasiController extends Controller
{
    /**
     * Jenis file laporan instalasi yang diizinkan (PDF/DOC, maks 5 MB).
     */
    private const REPORT_RULES = [
        'nullable',
        'file',
        'mimes:pdf,doc,docx',
        'max:5120',
    ];

    private function reportSecureRule(): SecureFile
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
        $query = Instalasi::with(['project.customer', 'customer', 'technician', 'technicalRequest'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        $instalasis = $query->paginate(15)->withQueryString();
        $projects = Project::with('customer:id,name')->orderBy('project_name')->limit(200)->get(['id', 'project_name', 'customer_id']);

        return view('teknisi.instalasis.index', compact('instalasis', 'projects'));
    }

    public function create(Request $request)
    {
        $projects = Project::with('customer:id,name')->orderBy('project_name')->limit(200)->get(['id', 'project_name', 'customer_id']);
        $selectedProject = $request->query('project_id');
        $technicians = User::whereHas('roles', fn ($q) => $q->whereIn('name', ['technician', 'lead-technician', 'prakerin-technician']))
            ->orderBy('name')->get(['id', 'name']);

        return view('teknisi.instalasis.create', compact('projects', 'selectedProject', 'technicians'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'location' => 'nullable|string|max:255',
            'technician_pic' => 'nullable|string|max:255',
            'technician_user_id' => 'nullable|exists:users,id',
            'schedule_date' => 'nullable|date',
            'job_status' => 'nullable|string|max:255',
            'report_file' => array_merge(self::REPORT_RULES, [$this->reportSecureRule()]),
            'status' => 'required|in:scheduled,on_progress,waiting,completed,cancelled',
        ]);

        $report = $request->file('report_file');
        unset($validated['report_file']);

        $technician = filled($validated['technician_user_id'] ?? null)
            ? User::find($validated['technician_user_id'])
            : auth()->user();

        if ($technician) {
            $validated['technician_user_id'] = $technician->id;
            $validated['technician_pic'] = $technician->name;
        }

        $project = Project::find($validated['project_id']);
        $validated['customer_id'] = $project?->customer_id;
        $validated['sales_user_id'] = $project?->sales_user_id;

        // "Selesai secara teknis" wajib punya bukti: file laporan instalasi.
        // Tanpa itu Admin tidak bisa memeriksa kelengkapan sebelum membuat BAST.
        if ($validated['status'] === 'completed' && ! $report) {
            return back()->withInput()->withErrors([
                'report_file' => __('Upload the installation report before completing.'),
            ]);
        }

        if ($validated['status'] === 'completed') {
            $validated['completed_at'] = now();
            $validated['completed_by'] = auth()->id();
        }

        $reportFields = [];
        if ($report) {
            $newPath = $report->storeAs(
                'instalasis/reports',
                Str::uuid()->toString().'.'.strtolower($report->getClientOriginalExtension()),
                'private'
            );

            if (! $newPath) {
                return back()->withInput()
                    ->withErrors(['report_file' => __('Failed to store the report file. Please try again.')]);
            }

            $reportFields = [
                'report_file' => $newPath,
                'report_file_name' => SecureFile::sanitizeName($report->getClientOriginalName()),
            ];
        }

        $instalasi = Instalasi::create($validated + $reportFields);

        // Laporan instalasi ikut tercatat sebagai dokumen (tanpa menyalin file).
        if ($instalasi->report_file) {
            DocumentRegistry::sync($instalasi);
        }

        if ($instalasi->status === 'completed') {
            $this->notifyInstallationCompleted($instalasi);
        }

        return redirect()->route('teknisi.instalasis.index')
            ->with('success', __('Installation added successfully.'));
    }

    public function show(Instalasi $instalasi)
    {
        $instalasi->load(['project.customer', 'customer', 'technician', 'technicalRequest.supportUsers', 'bastDocument', 'completedBy']);

        return view('teknisi.instalasis.show', compact('instalasi'));
    }

    public function edit(Instalasi $instalasi)
    {
        $projects = Project::with('customer:id,name')->orderBy('project_name')->limit(200)->get(['id', 'project_name', 'customer_id']);
        $technicians = User::whereHas('roles', fn ($q) => $q->whereIn('name', ['technician', 'lead-technician', 'prakerin-technician']))
            ->orderBy('name')->get(['id', 'name']);

        return view('teknisi.instalasis.edit', compact('instalasi', 'projects', 'technicians'));
    }

    public function update(Request $request, Instalasi $instalasi)
    {
        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'location' => 'nullable|string|max:255',
            'technician_pic' => 'nullable|string|max:255',
            'technician_user_id' => 'nullable|exists:users,id',
            'schedule_date' => 'nullable|date',
            'job_status' => 'nullable|string|max:255',
            'report_file' => array_merge(self::REPORT_RULES, [$this->reportSecureRule()]),
            'status' => 'required|in:scheduled,on_progress,waiting,completed,cancelled',
        ]);

        $report = $request->file('report_file');
        unset($validated['report_file']);

        $technician = filled($validated['technician_user_id'] ?? null)
            ? User::find($validated['technician_user_id'])
            : null;

        if ($technician) {
            $validated['technician_pic'] = $technician->name;
        }

        $project = Project::find($validated['project_id']);
        $validated['customer_id'] = $project?->customer_id;
        $validated['sales_user_id'] = $project?->sales_user_id;

        $wasComplete = $instalasi->status === 'completed';

        if ($validated['status'] === 'completed'
            && ! $report
            && ! $instalasi->hasReport()) {
            return back()->withInput()->withErrors([
                'report_file' => __('Upload the installation report before completing.'),
            ]);
        }

        $validated['completed_at'] = $validated['status'] === 'completed'
            ? ($instalasi->completed_at ?? now())
            : null;
        $validated['completed_by'] = $validated['status'] === 'completed'
            ? ($instalasi->completed_by ?? auth()->id())
            : null;

        $oldPath = $instalasi->report_file;
        $reportFields = [];
        if ($report) {
            $newPath = $report->storeAs(
                'instalasis/reports',
                Str::uuid()->toString().'.'.strtolower($report->getClientOriginalExtension()),
                'private'
            );

            if (! $newPath) {
                return back()->withInput()
                    ->withErrors(['report_file' => __('Failed to store the report file. Please try again.')]);
            }

            $reportFields = [
                'report_file' => $newPath,
                'report_file_name' => SecureFile::sanitizeName($report->getClientOriginalName()),
            ];
        }

        $instalasi->update($validated + $reportFields);

        // Ganti laporan = dokumen yang sama diperbarui, bukan ditambah.
        DocumentRegistry::sync($instalasi);

        if (! $wasComplete && $instalasi->status === 'completed') {
            $this->notifyInstallationCompleted($instalasi->fresh());
        }

        // File lama dibersihkan hanya setelah DB sukses (urutan dijaga manual).
        if ($report && $oldPath && $oldPath !== ($reportFields['report_file'] ?? null)) {
            Storage::disk('private')->delete($oldPath);
        }

        return redirect()->route('teknisi.instalasis.show', $instalasi)
            ->with('success', __('Installation updated successfully.'));
    }

    /**
     * Simpan BAST instalasi (berlaku untuk project maupun non-project).
     * BAST wajib sebelum pekerjaan instalasi dianggap selesai.
     */
    public function updateBast(Request $request, Instalasi $instalasi)
    {
        $user = $request->user();
        abort_unless($user && ($user->can('manage-technician') || $user->can('manage-admin')), 403);

        $validated = $request->validate([
            'bast_number' => 'nullable|string|max:100',
            'bast_date' => 'nullable|date',
            'bast_file' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:10240'],
            'bast_signed_3dy_name' => 'nullable|string|max:150',
            'bast_signed_3dy_at' => 'nullable|date',
            'bast_signed_customer_name' => 'nullable|string|max:150',
            'bast_signed_customer_at' => 'nullable|date',
        ]);

        $file = $request->file('bast_file');
        unset($validated['bast_file']);

        $oldDocument = $instalasi->bastDocument;

        if ($file) {
            $path = $file->storeAs(
                'bast/instalasi-'.$instalasi->id,
                Str::uuid()->toString().'.'.strtolower($file->getClientOriginalExtension() ?: 'pdf'),
                'private'
            );

            if (! $path) {
                return back()->withInput()->withErrors([
                    'bast_file' => __('Failed to store the BAST file. Please try again.'),
                ]);
            }

            $category = DocumentCategory::firstOrCreate(['name' => 'BAST']);
            $payload = [
                'project_id' => $instalasi->project_id,
                'customer_id' => $instalasi->customer_id ?? $instalasi->project?->customer_id,
                'doc_type' => 'bast',
                'document_category_id' => $category->id,
                'file_name' => SecureFile::sanitizeName($file->getClientOriginalName()),
                'file_path' => $path,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'uploaded_by' => $user->id,
            ];

            if ($oldDocument) {
                $oldPath = $oldDocument->file_path;
                $oldDocument->update($payload);
                $document = $oldDocument;
                if ($oldPath && $oldPath !== $path) {
                    Storage::disk('private')->delete($oldPath);
                }
            } else {
                $document = ProjectDocument::create($payload + [
                    'documentable_type' => Instalasi::class,
                    'documentable_id' => $instalasi->id,
                ]);
            }

            $validated['bast_document_id'] = $document->id;
        }

        $instalasi->update($validated);
        $instalasi->refresh();
        $instalasi->refreshBastReady();

        return back()->with('success', __('BAST saved. The installation is complete after both parties sign.'));
    }

    public function destroy(Instalasi $instalasi)
    {        // Dokumen turunannya ikut dibersihkan supaya tidak menggantung.
        DocumentRegistry::forget($instalasi);

        $instalasi->delete();

        if ($instalasi->report_file) {
            Storage::disk('private')->delete($instalasi->report_file);
        }

        return redirect()->route('teknisi.instalasis.index')
            ->with('success', __('Installation deleted successfully.'));
    }

    /**
     * Beri tahu Admin & Finance bahwa pekerjaan instalasi sudah selesai
     * secara teknis dan siap diperiksa untuk pembuatan BAST.
     */
    private function notifyInstallationCompleted(Instalasi $instalasi): void
    {
        $recipients = User::whereHas('roles', fn ($q) => $q->whereIn('name', ['admin', 'finance']))->get();

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new InstallationCompletedNotification($instalasi));
        }
    }

    /**
     * Sajikan laporan instalasi secara aman dari disk privat (inline untuk
     * PDF/gambar, sisanya diunduh) — mengikuti pola laporan survey.
     */
    public function viewReport(Instalasi $instalasi)
    {
        abort_if(! $instalasi->hasReport(), 404);

        abort_unless(Storage::disk('private')->exists($instalasi->report_file), 404);

        $path = $instalasi->report_file;
        $name = SecureFile::sanitizeName($this->reportFileName($instalasi));
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (! in_array($extension, SecureFile::INLINE_EXTENSIONS, true)) {
            return Storage::disk('private')->download($path, $name, ['X-Content-Type-Options' => 'nosniff']);
        }

        return response()->file(Storage::disk('private')->path($path), [
            'Content-Type' => SecureFile::serverMime(Storage::disk('private')->path($path), $extension),
            'Content-Disposition' => 'inline; filename="'.$name.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Unduh laporan dengan nama file asli upload.
     */
    public function downloadReport(Instalasi $instalasi)
    {
        abort_if(! $instalasi->hasReport(), 404);

        abort_unless(Storage::disk('private')->exists($instalasi->report_file), 404);

        return Storage::disk('private')->download(
            $instalasi->report_file,
            SecureFile::sanitizeName($this->reportFileName($instalasi)),
            ['X-Content-Type-Options' => 'nosniff'],
        );
    }

    /**
     * Nama file unduhan: nama asli upload (sudah disanitasi), fallback generik.
     */
    private function reportFileName(Instalasi $instalasi): string
    {
        return $instalasi->report_file_name
            ?: 'laporan-instalasi.'.strtolower(pathinfo($instalasi->report_file, PATHINFO_EXTENSION));
    }
}

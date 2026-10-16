<?php

namespace App\Http\Controllers;

use App\Models\Instalasi;
use App\Models\Project;
use App\Rules\SecureFile;
use Illuminate\Http\Request;
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
        $query = Instalasi::with('project.customer')->latest();

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

        return view('teknisi.instalasis.create', compact('projects', 'selectedProject'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'location' => 'nullable|string|max:255',
            'technician_pic' => 'nullable|string|max:255',
            'schedule_date' => 'nullable|date',
            'job_status' => 'nullable|string|max:255',
            'report_file' => array_merge(self::REPORT_RULES, [$this->reportSecureRule()]),
            'status' => 'required|in:scheduled,on_progress,waiting,completed,cancelled',
        ]);

        $report = $request->file('report_file');
        unset($validated['report_file']);

        $reportFields = [];
        if ($report) {
            $newPath = $report->storeAs(
                'instalasis/reports',
                Str::uuid()->toString().'.'.strtolower($report->getClientOriginalExtension()),
                'private'
            );

            if (! $newPath) {
                return back()->withInput()
                    ->withErrors(['report_file' => __('Gagal menyimpan file laporan. Silakan coba lagi.')]);
            }

            $reportFields = [
                'report_file' => $newPath,
                'report_file_name' => SecureFile::sanitizeName($report->getClientOriginalName()),
            ];
        }

        Instalasi::create($validated + $reportFields);

        return redirect()->route('teknisi.instalasis.index')
            ->with('success', __('Instalasi berhasil ditambahkan.'));
    }

    public function show(Instalasi $instalasi)
    {
        $instalasi->load('project.customer');

        return view('teknisi.instalasis.show', compact('instalasi'));
    }

    public function edit(Instalasi $instalasi)
    {
        $projects = Project::with('customer:id,name')->orderBy('project_name')->limit(200)->get(['id', 'project_name', 'customer_id']);

        return view('teknisi.instalasis.edit', compact('instalasi', 'projects'));
    }

    public function update(Request $request, Instalasi $instalasi)
    {
        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'location' => 'nullable|string|max:255',
            'technician_pic' => 'nullable|string|max:255',
            'schedule_date' => 'nullable|date',
            'job_status' => 'nullable|string|max:255',
            'report_file' => array_merge(self::REPORT_RULES, [$this->reportSecureRule()]),
            'status' => 'required|in:scheduled,on_progress,waiting,completed,cancelled',
        ]);

        $report = $request->file('report_file');
        unset($validated['report_file']);

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
                    ->withErrors(['report_file' => __('Gagal menyimpan file laporan. Silakan coba lagi.')]);
            }

            $reportFields = [
                'report_file' => $newPath,
                'report_file_name' => SecureFile::sanitizeName($report->getClientOriginalName()),
            ];
        }

        $instalasi->update($validated + $reportFields);

        // File lama dibersihkan hanya setelah DB sukses (urutan dijaga manual).
        if ($report && $oldPath && $oldPath !== ($reportFields['report_file'] ?? null)) {
            Storage::disk('private')->delete($oldPath);
        }

        return redirect()->route('teknisi.instalasis.show', $instalasi)
            ->with('success', __('Instalasi berhasil diupdate.'));
    }

    public function destroy(Instalasi $instalasi)
    {
        $instalasi->delete();

        if ($instalasi->report_file) {
            Storage::disk('private')->delete($instalasi->report_file);
        }

        return redirect()->route('teknisi.instalasis.index')
            ->with('success', __('Instalasi berhasil dihapus.'));
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

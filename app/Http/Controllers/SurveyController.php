<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Survey;
use App\Rules\SecureFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SurveyController extends Controller
{
    public function index(Request $request)
    {
        $query = Survey::with('project.customer')->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        $surveys = $query->paginate(15)->withQueryString();
        $projects = Project::with('customer:id,name')->orderBy('project_name')->limit(200)->get(['id', 'project_name', 'customer_id']);

        return view('teknisi.surveys.index', compact('surveys', 'projects'));
    }

    public function create(Request $request)
    {
        $projects = Project::with('customer:id,name')->orderBy('project_name')->limit(200)->get(['id', 'project_name', 'customer_id']);
        $selectedProject = $request->query('project_id');

        return view('teknisi.surveys.create', compact('projects', 'selectedProject'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'survey_date' => 'nullable|date',
            'location' => 'nullable|string|max:255',
            'pic' => 'nullable|string|max:255',
            'survey_data' => 'nullable|string',
            'report' => ['required', 'file', 'max:5120', SecureFile::documents()],
        ]);

        $file = $request->file('report');

        try {
            $survey = Survey::create([
                'project_id' => $validated['project_id'],
                'survey_date' => $validated['survey_date'] ?? null,
                'location' => $validated['location'] ?? null,
                'pic' => $validated['pic'] ?? null,
                'survey_data' => $validated['survey_data'] ?? null,
            ]);
        } catch (\Throwable $e) {
            report($e);

            return back()->withInput()
                ->withErrors(['report' => __('Gagal membuat survey. Coba lagi.')]);
        }

        // Nama file dibuat server-side; ekstensi dari MIME (bukan nama user),
        // dan jenis file sudah diverifikasi SecureFile::documents().
        $extension = strtolower($file->guessExtension() ?? '');
        if (! in_array($extension, ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'zip', 'rar', 'txt', 'csv'], true)) {
            $extension = 'dat';
        }

        $path = $file->storeAs(
            'surveys/'.$survey->id.'/reports',
            Str::uuid()->toString().'.'.$extension,
            'private'
        );

        if (! $path) {
            // File gagal disimpan: survey tidak jadi dibuat.
            $survey->delete();

            return back()->withInput()
                ->withErrors(['report' => __('Gagal menyimpan file laporan. Silakan coba lagi.')]);
        }

        try {
            $survey->update([
                'report_file' => $path,
                'report_file_name' => SecureFile::sanitizeName($file->getClientOriginalName()),
                'status' => Survey::STATUS_COMPLETED,
            ]);
        } catch (\Throwable $e) {
            // DB gagal: hapus file dan survey agar tidak ada data menggantung.
            Storage::disk('private')->delete($path);
            $survey->delete();
            report($e);

            return back()->withInput()
                ->withErrors(['report' => __('Gagal menyimpan laporan. Coba lagi.')]);
        }

        return redirect()->route('teknisi.surveys.show', $survey)
            ->with('success', 'Survey berhasil ditambahkan.');
    }

    public function show(Survey $survey)
    {
        $survey->load('project.customer');

        return view('teknisi.surveys.show', compact('survey'));
    }

    public function edit(Survey $survey)
    {
        $projects = Project::with('customer:id,name')->orderBy('project_name')->limit(200)->get(['id', 'project_name', 'customer_id']);

        return view('teknisi.surveys.edit', compact('survey', 'projects'));
    }

    public function update(Request $request, Survey $survey)
    {
        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'sales_request' => 'nullable|string',
            'survey_date' => 'nullable|date',
            'location' => 'nullable|string|max:255',
            'pic' => 'nullable|string|max:255',
            'survey_data' => 'nullable|string',
            'survey_report' => 'nullable|string',
            'status' => 'required|in:draft,scheduled,on_survey,completed',
            'notes' => 'nullable|string',
        ]);

        $survey->update($validated);

        return redirect()->route('teknisi.surveys.show', $survey)
            ->with('success', 'Survey berhasil diupdate.');
    }

    public function destroy(Survey $survey)
    {
        $survey->delete();

        return redirect()->route('teknisi.surveys.index')
            ->with('success', 'Survey berhasil dihapus.');
    }

    /**
     * Unggah (atau ganti) laporan survey dalam format PDF.
     *
     * Mutasi tetap di belakang permission manage-technician (sama dengan aksi
     * tulis survey lain), dan diperiksa ulang di sini sebagai pertahanan backend —
     * bukan hanya menyembunyikan tombol di frontend.
     */
    public function uploadReport(Request $request, Survey $survey)
    {
        abort_unless($request->user()?->can('manage-technician'), 403);

        $request->validate([
            'report' => [
                'required',
                'file',
                'mimes:pdf',
                'max:5120',
                new SecureFile(['pdf'], ['application/pdf']),
            ],
            'report_survey_id' => 'nullable|integer',
        ]);

        $file = $request->file('report');
        $oldPath = $survey->report_file;

        // Nama file dibuat server-side (uuid) dengan ekstensi PDF tetap;
        // nama/ekstensi dari user tidak dipercaya.
        $newPath = $file->storeAs(
            'surveys/'.$survey->id.'/reports',
            Str::uuid()->toString().'.pdf',
            'private'
        );

        if (! $newPath) {
            return back()->withInput()
                ->withErrors(['report' => __('Gagal menyimpan file laporan. Silakan coba lagi.')]);
        }

        try {
            DB::transaction(function () use ($survey, $newPath, $file) {
                $survey->update([
                    'report_file' => $newPath,
                    'report_file_name' => SecureFile::sanitizeName($file->getClientOriginalName()),
                    'status' => Survey::STATUS_COMPLETED,
                ]);
            });
        } catch (\Throwable $e) {
            // DB gagal: file baru dibuang, laporan lama tetap utuh,
            // status survey tidak berubah.
            Storage::disk('private')->delete($newPath);
            report($e);

            return back()->withInput()
                ->withErrors(['report' => __('Gagal menyimpan laporan. Coba lagi.')]);
        }

        // Baru setelah DB sukses, file lama (jika ada) dibersihkan.
        // Transaksi DB tidak mencakup filesystem, jadi urutannya dijaga manual.
        if ($oldPath && $oldPath !== $newPath) {
            Storage::disk('private')->delete($oldPath);
        }

        return redirect()->route('teknisi.surveys.index')
            ->with('success', __('Laporan survey berhasil diunggah.'));
    }

    /**
     * Sajikan laporan secara aman dari disk privat.
     * Keputusan inline berdasarkan MIME/ekstensi file yang tersimpan:
     * PDF & gambar tampil inline (viewer browser), dokumen lain diunduh.
     */
    public function viewReport(Request $request, Survey $survey)
    {
        abort_unless($request->user()?->can('view-technician'), 403);

        abort_if(! $survey->hasReport(), 404);

        abort_unless(Storage::disk('private')->exists($survey->report_file), 404);

        $path = $survey->report_file;
        $name = SecureFile::sanitizeName($this->reportFileName($survey));
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
    public function downloadReport(Request $request, Survey $survey)
    {
        abort_unless($request->user()?->can('view-technician'), 403);

        abort_if(! $survey->hasReport(), 404);

        abort_unless(Storage::disk('private')->exists($survey->report_file), 404);

        return Storage::disk('private')->download(
            $survey->report_file,
            SecureFile::sanitizeName($this->reportFileName($survey)),
            ['X-Content-Type-Options' => 'nosniff'],
        );
    }

    /**
     * Nama file unduhan: pakai nama asli upload (tersimpan aman & disanitasi);
     * fallback ke nama generik bila kolom lama kosong.
     */
    private function reportFileName(Survey $survey): string
    {
        return $survey->report_file_name
            ?: 'Survey-Report-'.$survey->id.'.pdf';
    }
}

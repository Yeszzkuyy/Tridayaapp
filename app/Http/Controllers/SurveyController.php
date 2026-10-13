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
            'sales_request' => 'nullable|string',
            'survey_date' => 'nullable|date',
            'location' => 'nullable|string|max:255',
            'pic' => 'nullable|string|max:255',
            'survey_data' => 'nullable|string',
            'survey_report' => 'nullable|string',
            'status' => 'required|in:draft,scheduled,on_survey,completed',
            'notes' => 'nullable|string',
        ]);

        Survey::create($validated);

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
            DB::transaction(function () use ($survey, $newPath) {
                $survey->update([
                    'report_file' => $newPath,
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
     * Sajikan laporan PDF secara inline (aman, dari disk privat).
     */
    public function viewReport(Request $request, Survey $survey)
    {
        abort_unless($request->user()?->can('view-technician'), 403);

        abort_if(! $survey->hasReport(), 404);

        return SecureFile::fileResponse('private', $survey->report_file, $this->reportFileName($survey));
    }

    /**
     * Unduh laporan PDF.
     */
    public function downloadReport(Request $request, Survey $survey)
    {
        abort_unless($request->user()?->can('view-technician'), 403);

        abort_if(! $survey->hasReport(), 404);

        abort_unless(Storage::disk('private')->exists($survey->report_file), 404);

        return Storage::disk('private')->download(
            $survey->report_file,
            $this->reportFileName($survey),
            ['X-Content-Type-Options' => 'nosniff'],
        );
    }

    /**
     * Nama file unduhan yang aman & mudah dibaca (nama asli user tidak disimpan).
     */
    private function reportFileName(Survey $survey): string
    {
        return 'Survey-Report-'.$survey->id.'.pdf';
    }
}

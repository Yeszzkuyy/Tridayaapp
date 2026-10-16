<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Survey;
use App\Models\User;
use App\Notifications\SurveyCreatedNotification;
use App\Notifications\SurveyReportUploadedNotification;
use App\Rules\SecureFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SurveyController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Survey::with(['customer:id,name', 'sales:id,name', 'project:id,project_name'])->latest();

        // Sales (tanpa izin teknisi) hanya melihat survey miliknya (read-only).
        // Pemilik langsung lewat surveys.sales_user_id; fallback data lama
        // lewat project.sales_user_id.
        if (! $user?->can('view-technician')) {
            abort_unless($this->isSalesRole($user), 403);
            $query->where(function ($q) use ($user) {
                $q->where('sales_user_id', $user->id)
                    ->orWhereHas('project', fn ($p) => $p->where('sales_user_id', $user->id));
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('sales_user_id')) {
            $query->where('sales_user_id', $request->sales_user_id);
        }
        if ($request->filled('q')) {
            $query->where('title', 'like', '%'.$request->q.'%');
        }

        $surveys = $query->paginate(15)->withQueryString();
        $salesUsers = User::whereHas('roles', fn ($q) => $q->where('name', 'sales')->orWhere('name', 'like', 'sales-%'))
            ->orderBy('name')->limit(200)->get(['id', 'name']);

        return view('teknisi.surveys.index', compact('surveys', 'salesUsers'));
    }

    public function create(Request $request)
    {
        $projects = Project::with('customer:id,name')->orderBy('project_name')->limit(200)->get(['id', 'project_name', 'customer_id']);
        $customers = \App\Models\Customer::orderBy('name')->limit(200)->get(['id', 'name']);
        $salesUsers = User::whereHas('roles', fn ($q) => $q->where('name', 'sales')->orWhere('name', 'like', 'sales-%'))
            ->orderBy('name')->limit(200)->get(['id', 'name']);

        // Dari timeline project: bawa project + customer-nya otomatis,
        // teknisi cukup mengisi judul + sales (tidak ada pilihan project yang membingungkan).
        $presetProjectId = $request->query('project_id');
        $presetCustomerId = $request->query('customer_id')
            ?? Project::find($presetProjectId)?->customer_id;

        return view('teknisi.surveys.create', compact('projects', 'customers', 'salesUsers', 'presetProjectId', 'presetCustomerId'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'customer_id' => 'nullable|exists:customers,id',
            'project_id' => 'nullable|exists:projects,id',
            'sales_user_id' => 'required|exists:users,id',
            'survey_date' => 'nullable|date',
            'location' => 'nullable|string|max:255',
            'pic' => 'nullable|string|max:255',
            'survey_data' => 'nullable|string',
            // Laporan boleh menyusul — survey bisa dijadwalkan dulu.
            'report' => ['nullable', 'file', 'max:5120', SecureFile::documents()],
        ]);

        $file = $request->file('report');

        // Konsistensi data: laporan baru boleh menutup survey (completed)
        // bila rumahnya sudah jelas (customer atau project) — supaya survey
        // tidak "hilang" dari timeline.
        if ($file && blank($validated['customer_id'] ?? null) && blank($validated['project_id'] ?? null)) {
            return back()->withInput()
                ->withErrors(['customer_id' => __('Select a customer before attaching a report.')]);
        }

        try {
            $survey = Survey::create([
                'title' => $validated['title'],
                'customer_id' => $validated['customer_id'] ?? null,
                'project_id' => $validated['project_id'] ?? null,
                'sales_user_id' => $validated['sales_user_id'],
                'survey_date' => $validated['survey_date'] ?? null,
                'location' => $validated['location'] ?? null,
                'pic' => $validated['pic'] ?? null,
                'survey_data' => $validated['survey_data'] ?? null,
                // Tanpa laporan = masih rencana; dengan laporan = selesai.
                'status' => $file ? Survey::STATUS_COMPLETED : 'scheduled',
            ]);
        } catch (\Throwable $e) {
            report($e);

            return back()->withInput()
                ->withErrors(['report' => __('Failed to create the survey. Please try again.')]);
        }

        // Laporan menyusul nanti juga bisa — baru kirim notifikasi + simpan
        // file bila memang dilampirkan sekarang.
        if (! $file) {
            $survey->sales?->notify(new SurveyCreatedNotification($survey));

            return redirect()->route('teknisi.surveys.show', $survey)
                ->with('success', __('Survey added successfully.'));
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
                ->withErrors(['report' => __('Failed to store the report file. Please try again.')]);
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
                ->withErrors(['report' => __('Failed to save the report. Please try again.')]);
        }

        $survey->sales?->notify(new SurveyReportUploadedNotification($survey));

        return redirect()->route('teknisi.surveys.show', $survey)
            ->with('success', __('Survey added successfully.'));
    }

    public function show(Request $request, Survey $survey)
    {
        $this->guardRead($request->user(), $survey);

        $survey->load(['customer:id,name', 'sales:id,name', 'project:id,project_name']);

        return view('teknisi.surveys.show', compact('survey'));
    }

    public function edit(Survey $survey)
    {
        $projects = Project::with('customer:id,name')->orderBy('project_name')->limit(200)->get(['id', 'project_name', 'customer_id']);
        $customers = \App\Models\Customer::orderBy('name')->limit(200)->get(['id', 'name']);
        $salesUsers = User::whereHas('roles', fn ($q) => $q->where('name', 'sales')->orWhere('name', 'like', 'sales-%'))
            ->orderBy('name')->limit(200)->get(['id', 'name']);

        return view('teknisi.surveys.edit', compact('survey', 'projects', 'customers', 'salesUsers'));
    }

    public function update(Request $request, Survey $survey)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'customer_id' => 'nullable|exists:customers,id',
            'project_id' => 'nullable|exists:projects,id',
            'sales_user_id' => 'required|exists:users,id',
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
            ->with('success', __('Survey updated successfully.'));
    }

    public function destroy(Survey $survey)
    {
        $survey->delete();

        return redirect()->route('teknisi.surveys.index')
            ->with('success', __('Survey deleted successfully.'));
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

        // Konsistensi data: laporan baru menutup survey bila rumahnya sudah jelas
        // (punya customer atau project) — supaya survey tidak "hilang" dari
        // timeline. Survey lama yang nempel project tetap bisa ganti laporan.
        if (blank($survey->customer_id) && blank($survey->project_id)) {
            return back()->withInput()
                ->withErrors(['report' => __('Fill in the survey customer (via Edit) before uploading a report.')]);
        }

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
                ->withErrors(['report' => __('Failed to store the report file. Please try again.')]);
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
                ->withErrors(['report' => __('Failed to save the report. Please try again.')]);
        }

        // Baru setelah DB sukses, file lama (jika ada) dibersihkan.
        // Transaksi DB tidak mencakup filesystem, jadi urutannya dijaga manual.
        if ($oldPath && $oldPath !== $newPath) {
            Storage::disk('private')->delete($oldPath);
        }

        $survey->sales?->notify(new SurveyReportUploadedNotification($survey));

        return redirect()->route('teknisi.surveys.index')
            ->with('success', __('Survey report uploaded successfully.'));
    }

    /**
     * Sajikan laporan secara aman dari disk privat.
     * Keputusan inline berdasarkan MIME/ekstensi file yang tersimpan:
     * PDF & gambar tampil inline (viewer browser), dokumen lain diunduh.
     */
    public function viewReport(Request $request, Survey $survey)
    {
        $this->guardRead($request->user(), $survey);

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
        $this->guardRead($request->user(), $survey);

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

    /**
     * Izin baca survey.
     * Pemegang izin teknisi: penuh. Sales (sales/sales-{pt}): hanya survey
     * miliknya (surveys.sales_user_id = user), dengan fallback data lama
     * lewat project pemiliknya. Read-only.
     */
    private function guardRead(?User $user, Survey $survey): void
    {
        abort_unless($user !== null, 401);

        if ($user->can('view-technician')) {
            return;
        }

        $owns = (int) $survey->sales_user_id === (int) $user->id
            || (int) $survey->project?->sales_user_id === (int) $user->id;

        abort_unless($this->isSalesRole($user) && $owns, 403);
    }

    /**
     * Apakah user berrole sales atau sales-{pt}.
     */
    private function isSalesRole(?User $user): bool
    {
        return $user !== null && $user->roles()
            ->where('name', 'sales')
            ->orWhere('name', 'like', 'sales-%')
            ->exists();
    }
}

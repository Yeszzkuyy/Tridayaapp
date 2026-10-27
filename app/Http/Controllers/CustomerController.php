<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Lead;
use App\Support\PtAccess;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Customer::class);

        $customers = Customer::withCount('projects')
            ->with(['contacts' => function ($query) {
                $query->orderByDesc('is_primary')->latest();
            }])
            ->when($request->filled('search'), function ($query) use ($request) {
                $query->whereLike(['name', 'company', 'email'], $request->string('search'));
            })
            ->when($request->filled('pt_group'), fn ($query) => $query->where('pt_group', $request->string('pt_group')))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $ptGroups = Lead::PT_GROUPS;

        return $request->ajax()
            ? view('customers._list', compact('customers'))->render()
            : view('customers.index', compact('customers', 'ptGroups'))
                ->with('search', $request->string('search'))
                ->with('ptGroup', $request->string('pt_group'));
    }

    /**
     * PT yang boleh ditulis user ini. Kosong (tanpa role PT / management /
     * super-admin) berarti semua PT.
     */
    private function writablePtGroups(): array
    {
        return PtAccess::userPts(auth()->user()) ?: Lead::PT_GROUPS;
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorize('create', Customer::class);

        return view('customers.create', ['ptGroups' => $this->writablePtGroups()]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Customer::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'company' => 'nullable|string|max:255',
            'pt_group' => 'required|in:'.implode(',', $this->writablePtGroups()),
            'address' => 'nullable|string|max:1000',
            'phone' => 'nullable|string|max:50',
            'whatsapp' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'npwp' => 'nullable|string|max:32',
            'npwp_document' => ['nullable', 'file', 'max:10240', \App\Rules\SecureFile::documents()],
            'notes' => 'nullable|string|max:5000',
            'status' => 'nullable|in:lead,deal,instalasi,selesai',
        ]);

        // Form customer hanya punya satu kolom nama; company = name supaya tampilan Lead/Monitoring konsisten
        $validated['company'] ??= $validated['name'];

        $customer = Customer::create($validated);

        if ($request->hasFile('npwp_document')) {
            $file = $request->file('npwp_document');
            $customer->update([
                'npwp_document_path' => $this->storeNpwp($file, $customer),
                'npwp_document_name' => $file->getClientOriginalName(),
            ]);
        }

        return redirect()
            ->route('customers.index')
            ->with('success', __('Customer added successfully'))
            ->with('success_card', true);
    }

    /**
     * Display the specified resource.
     */
    public function show(Customer $customer)
    {
        $this->authorize('view', $customer);

        // Load relasi projects dan contacts sekaligus.
        // Kontak diurutkan berdasarkan is_primary supaya PIC utama muncul di atas.
        // Kartu project butuh status, jenis pekerjaan, dan hitungan progres
        // kerja (task selesai/total + dokumen) — dimuat di sini supaya tidak
        // N+1 saat merender grid kartu.
        $customer->load([
            'projects' => function ($query) {
                $query->with(['status', 'workType'])
                    ->withCount([
                        'tasks',
                        'tasks as tasks_done_count' => fn ($q) => $q->where('status', 'Done'),
                        'documents',
                    ]);
            },
            'contacts' => function ($query) {
                $query->orderBy('is_primary', 'desc')->latest();
            }
        ]);

        return view('customers.show', [
            'customer' => $customer,
            'timeline' => $this->buildTimeline($customer),
            'documents' => $this->generalDocuments($customer),
            'typedDocs' => $this->typedDocuments($customer),
            'custInvoices' => $customer->invoices()->latest('issue_date')->get(),
            'custPos' => $customer->purchaseOrders()->latest('issue_date')->get(),
            'custPayments' => \App\Models\Payment::whereHas('invoice', fn ($q) => $q->where('customer_id', $customer->id))
                ->with('invoice')
                ->latest('payment_date')
                ->get(),
        ]);
    }

    /**
     * Dokumen tab "Documents": dokumen project-project customer + dokumen
     * umum (tanpa project). File invoice/PO/payment (doc_type) tidak ikut
     * di sini — mereka punya section sendiri.
     */
    private function generalDocuments(Customer $customer): \Illuminate\Support\Collection
    {
        $projectIds = $customer->projects->pluck('id')->toArray();

        return \App\Models\ProjectDocument::where(function ($query) use ($projectIds, $customer) {
                $query->whereIn('project_id', $projectIds)
                    ->orWhere('customer_id', $customer->id);
            })
            // Dokumen transaksi (invoice/po/payment) punya section sendiri di
            // bawah; dokumen hasil modul (survey/sizing/instalasi) ikut di sini
            // supaya semua menu dokumen menampilkan isi yang sama.
            ->where(function ($query) {
                $query->whereNull('doc_type')
                    ->orWhereNotIn('doc_type', \App\Models\ProjectDocument::DOC_TYPES);
            })
            ->with(['project', 'category', 'uploader'])
            ->whereNull('deleted_at')
            ->latest()
            ->get();
    }

    /**
     * File invoice/PO/payment per jenis — SATU query dikelompokkan per
     * doc_type (menggantikan 3 query terpisah yang dulu dijalankan dari
     * dalam view tiap kali halaman dirender).
     *
     * @return \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, \App\Models\ProjectDocument>>
     */
    private function typedDocuments(Customer $customer): \Illuminate\Support\Collection
    {
        return \App\Models\ProjectDocument::where('customer_id', $customer->id)
            ->whereIn('doc_type', ['invoice', 'po', 'payment'])
            ->whereNull('deleted_at')
            ->with(['uploader', 'project'])
            ->latest()
            ->get()
            ->groupBy('doc_type');
    }

    /**
     * Timeline gabungan urut waktu: aktivitas project (dibuat/ubah status/
     * support) + follow-up/catatan + meeting. Dibatasi 50 terbaru.
     * Sengaja TIDAK memuat PIC/dokumen/invoice — bukan bagian dari "activity".
     */
    private function buildTimeline(Customer $customer): \Illuminate\Support\Collection
    {
        $projectIds = $customer->projects->pluck('id');
        $leadIds = $customer->leads()->pluck('id');

        $items = collect();

        $projectActivities = \App\Models\ProjectActivity::whereIn('project_id', $projectIds)
            ->with(['project', 'user'])
            ->latest('activity_date')
            ->take(50)
            ->get()
            ->map(fn ($a) => [
                'date' => $a->activity_date ?? $a->created_at,
                'type' => 'project',
                'user' => $a->user,
                'title' => $a->title ?? __('Activity'),
                'detail' => $a->description,
                'context' => $a->project?->project_name,
            ]);

        $followUps = $customer->followUps()->with('creator')
            ->latest('follow_up_date')
            ->take(50)
            ->get()
            ->map(fn ($fu) => [
                'date' => $fu->follow_up_date ?? $fu->created_at,
                'type' => 'followup',
                'user' => $fu->creator,
                'title' => \App\Models\FollowUp::typeLabel($fu->type),
                'detail' => $fu->description,
                'context' => $fu->follow_up_date?->format('d M Y'),
            ]);

        $meetings = \App\Models\SalesSchedule::whereIn('lead_id', $leadIds)
            ->with('creator')
            ->latest('start_at')
            ->take(50)
            ->get()
            ->map(fn ($m) => [
                'date' => $m->start_at ?? $m->created_at,
                'type' => 'meeting',
                'user' => $m->creator,
                'title' => $m->title ?? __('Meeting'),
                'detail' => $m->location,
                'context' => $m->start_at?->format('d M Y H:i'),
            ]);

        return $items
            ->concat($projectActivities)
            ->concat($followUps)
            ->concat($meetings)
            ->sortByDesc('date')
            ->values()
            ->take(50);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Customer $customer)
    {
        $this->authorize('update', $customer);

        $customer->load(['contacts' => fn ($query) => $query->orderByDesc('is_primary')->latest()]);

        return view('customers.edit', ['customer' => $customer, 'ptGroups' => $this->writablePtGroups()]);
    }

    private function storeNpwp(UploadedFile $file, Customer $customer): string
    {
        return $file->storeAs(
            "customers/{$customer->id}/npwp",
            Str::uuid() . '.' . strtolower($file->getClientOriginalExtension()),
            'private'
        );
    }

    public function downloadNpwp(Customer $customer)
    {
        $this->authorize('view', $customer);
        abort_if(! $customer->npwp_document_path, 404);

        return \App\Rules\SecureFile::fileResponse(
            'private',
            $customer->npwp_document_path,
            $customer->npwpDisplayName() ?? basename($customer->npwp_document_path)
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Customer $customer)
    {
        $this->authorize('update', $customer);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'company' => 'nullable|string|max:255',
            'pt_group' => 'required|in:'.implode(',', $this->writablePtGroups()),
            'address' => 'nullable|string|max:1000',
            'phone' => 'nullable|string|max:50',
            'whatsapp' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'npwp' => 'nullable|string|max:32',
            'npwp_document' => ['nullable', 'file', 'max:10240', \App\Rules\SecureFile::documents()],
            'notes' => 'nullable|string|max:5000',
            'status' => 'nullable|in:lead,deal,instalasi,selesai',
        ]);

        $validated['company'] ??= $validated['name'];

        if ($request->hasFile('npwp_document')) {
            if ($customer->npwp_document_path) {
                Storage::disk('private')->delete($customer->npwp_document_path);
            }
            $file = $request->file('npwp_document');
            $validated['npwp_document_path'] = $this->storeNpwp($file, $customer);
            $validated['npwp_document_name'] = $file->getClientOriginalName();
        }

        $customer->update($validated);

        return redirect()
            ->route('customers.index')
            ->with('success', __('Customer updated successfully'))
            ->with('success_card', true);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Customer $customer)
    {
        $this->authorize('delete', $customer);

        $customer->delete();

        return redirect()
            ->route('customers.index')
            ->with('success', __('Customer deleted successfully'));
    }
}
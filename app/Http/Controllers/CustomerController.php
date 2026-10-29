<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Lead;
use App\Services\CustomerContactService;
use App\Support\PtAccess;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CustomerController extends Controller
{
    public function __construct(private CustomerContactService $contacts) {}

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
            'billing_address' => 'nullable|string|max:1000',
            'shipping_address' => 'nullable|string|max:1000',
            'finance_pic_name' => 'nullable|string|max:255',
            'finance_pic_phone' => 'nullable|string|max:50',
            'finance_pic_email' => 'nullable|email|max:255',
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

        // PIC di form diterjemahkan menjadi satu kontak primary (input eksplisit
        // pengguna), bukan lagi kolom legacy yang berdiri sendiri.
        $contactPerson = $validated['contact_person'] ?? null;
        unset($validated['contact_person']);

        $customer = Customer::create($validated);

        if (filled($contactPerson)) {
            $this->contacts->create($customer, [
                'name' => $contactPerson,
                'is_primary' => true,
            ]);
        }

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
    public function show(Customer $customer, Request $request, \App\Services\DocumentCenterService $documentCenter)
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

        // Tab Dokumen: satu daftar terpadu khusus customer ini. Sumbernya
        // sama dengan menu Dokumen global, jadi file tidak disalin/diduplikasi.
        $documentFilters = [
            'q' => filled($request->query('documents_q')) ? trim((string) $request->query('documents_q')) : null,
            'category' => in_array($request->query('documents_category'), \App\Services\DocumentCenterService::CATEGORIES, true)
                ? $request->query('documents_category') : null,
            'subcategory' => in_array($request->query('documents_subcategory'), \App\Services\DocumentCenterService::SUBCATEGORIES, true)
                ? $request->query('documents_subcategory') : null,
            'customer_id' => $customer->id,
            'sort' => in_array($request->query('documents_sort'), \App\Services\DocumentCenterService::SORTS, true)
                ? $request->query('documents_sort') : 'newest',
        ];

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
            'customerDocuments' => $documentCenter->paginate($request->user(), $documentFilters, 'documents_page'),
            'customerDocumentFilters' => $documentFilters,
        ]);
    }

    /**
     * Endpoint poll tab Activity: hanya potongan timeline (tanpa layout),
     * dipakai halaman detail supaya aktivitas baru muncul tanpa reload.
     */
    public function timeline(Customer $customer)
    {
        $this->authorize('view', $customer);

        return response()->view('customers.partials.timeline', [
            'timeline' => $this->buildTimeline($customer),
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
                // logged_at = kapan dicatat (label "x menit lalu" + urutan),
                // event_at  = kapan kejadiannya (jadwal/aktivitas).
                'logged_at' => $a->created_at,
                'event_at' => $a->activity_date ?? $a->created_at,
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
                'logged_at' => $fu->created_at,
                'event_at' => $this->followUpEventAt($fu),
                'date' => $this->followUpEventAt($fu),
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
                'logged_at' => $m->created_at,
                'event_at' => $m->start_at ?? $m->created_at,
                'date' => $m->start_at ?? $m->created_at,
                'type' => 'meeting',
                'user' => $m->creator,
                'title' => $m->title ?? __('Meeting'),
                'detail' => $m->location,
                'context' => $m->start_at?->format('d M Y H:i'),
            ]);

        // Urutkan dari yang paling baru dicatat, supaya aktivitas yang baru
        // diinput selalu di atas (jadwal yang masih jauh tidak loncat ke atas).
        return $items
            ->concat($projectActivities)
            ->concat($followUps)
            ->concat($meetings)
            ->sortByDesc(fn (array $item) => $item['logged_at']?->getTimestamp() ?? 0)
            ->values()
            ->take(50);
    }

    /**
     * Waktu kejadian follow-up: tanggal + jam aktivitas. Kolom follow_up_date
     * hanya menyimpan tanggal (jam 00:00), jadi jamnya ditempel dari
     * activity_time agar label waktu tidak salah hitung beberapa jam.
     */
    private function followUpEventAt(\App\Models\FollowUp $followUp): ?\Carbon\CarbonInterface
    {
        $date = $followUp->follow_up_date?->copy();

        if (! $date) {
            return $followUp->created_at;
        }

        $time = substr((string) $followUp->activity_time, 0, 5);

        if (substr_count($time, ':') === 1) {
            [$hour, $minute] = array_map('intval', explode(':', $time));
            $date->setTime($hour, $minute);
        }

        return $date;
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
        abort_unless(auth()->user()?->can('view-customer-finance'), 403);
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
            'billing_address' => 'nullable|string|max:1000',
            'shipping_address' => 'nullable|string|max:1000',
            'finance_pic_name' => 'nullable|string|max:255',
            'finance_pic_phone' => 'nullable|string|max:50',
            'finance_pic_email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'whatsapp' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'npwp' => 'nullable|string|max:32',
            'npwp_document' => ['nullable', 'file', 'max:10240', \App\Rules\SecureFile::documents()],
            'notes' => 'nullable|string|max:5000',
            'status' => 'nullable|in:lead,deal,instalasi,selesai',
        ]);

        $validated['company'] ??= $validated['name'];

        // `contact_person` bukan lagi sumber tulis independen: arahkan ke
        // kontak primary supaya halaman Lead/Manage Sales tidak basi.
        $contactPerson = $validated['contact_person'] ?? null;
        unset($validated['contact_person']);

        if ($request->hasFile('npwp_document')) {
            if ($customer->npwp_document_path) {
                Storage::disk('private')->delete($customer->npwp_document_path);
            }
            $file = $request->file('npwp_document');
            $validated['npwp_document_path'] = $this->storeNpwp($file, $customer);
            $validated['npwp_document_name'] = $file->getClientOriginalName();
        }

        $customer->update($validated);

        $primary = $this->contacts->primary($customer);

        if ($primary) {
            // PIC utama diubah eksplisit dari form customer -> ikut ubah kontak.
            if (filled($contactPerson) && $contactPerson !== $primary->name) {
                $this->contacts->update($primary, ['name' => $contactPerson]);
            } else {
                $this->contacts->syncPicCache($customer);
            }
        } elseif (filled($contactPerson) && $contactPerson !== $customer->contact_person) {
            // Customer lama tanpa kontak: angkat PIC yang diisi menjadi primary.
            $this->contacts->create($customer, [
                'name' => $contactPerson,
                'is_primary' => true,
            ]);
        }

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
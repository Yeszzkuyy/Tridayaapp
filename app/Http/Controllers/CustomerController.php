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

        // Load relasi projects dan contacts sekaligus
        // Contacts diurutkan berdasarkan is_primary supaya PIC utama muncul di atas
        $customer->load([
            'projects',
            'contacts' => function ($query) {
                $query->orderBy('is_primary', 'desc')->latest();
            }
        ]);

        return view('customers.show', compact('customer'));
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
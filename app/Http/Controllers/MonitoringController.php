<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\FollowUp;
use App\Models\Project;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\Payment;
use App\Models\ProjectDocument;
use App\Enums\ProjectStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MonitoringController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('view-monitoring');

        // Summary Stats
        $stats = $this->getStats($request);

        // Customer Progress List
        $customers = $this->getCustomerProgress($request);

        // Filter options
        $divisiOptions = ['marketing', 'sales', 'admin', 'teknisi'];
        $statusOptions = $this->getAllStatuses();

        return view('monitoring.index', compact(
            'stats',
            'customers',
            'divisiOptions',
            'statusOptions'
        ));
    }

    private function getStats(Request $request): array
    {
        // Lead masuk (Marketing)
        $leadQuery = Lead::query();
        if ($request->filled('date_from')) {
            $leadQuery->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $leadQuery->whereDate('created_at', '<=', $request->date_to);
        }
        $leadMasuk = $leadQuery->count();

        // Invoice Outstanding (Admin) - unpaid invoices
        $invoiceQuery = Invoice::where('status', 'unpaid');
        if ($request->filled('date_from')) {
            $invoiceQuery->whereDate('issue_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $invoiceQuery->whereDate('issue_date', '<=', $request->date_to);
        }
        $invoiceOutstanding = $invoiceQuery->sum('amount');

        // Instalasi Proses (Teknisi) - projects with status Open/Progress
        $instalasiProsesQuery = Project::whereHas('status', fn ($q) => $q->whereIn('name', [
            ProjectStatus::Open->value,
            ProjectStatus::OnProgress->value,
        ]));
        if ($request->filled('date_from')) {
            $instalasiProsesQuery->whereDate('start_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $instalasiProsesQuery->whereDate('start_date', '<=', $request->date_to);
        }
        $instalasiProses = $instalasiProsesQuery->count();

        // Instalasi Selesai (Teknisi) - projects with status Done
        $instalasiSelesaiQuery = Project::whereHas('status', fn ($q) => $q->where('name', 'Done'));
        if ($request->filled('date_from')) {
            $instalasiSelesaiQuery->whereDate('end_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $instalasiSelesaiQuery->whereDate('end_date', '<=', $request->date_to);
        }
        $instalasiSelesai = $instalasiSelesaiQuery->count();

        // PO Menunggu Proses (Admin) - PO with status draft/diproses
        $poQuery = PurchaseOrder::whereIn('status', ['draft', 'diproses']);
        if ($request->filled('date_from')) {
            $poQuery->whereDate('issue_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $poQuery->whereDate('issue_date', '<=', $request->date_to);
        }
        $poMenunggu = $poQuery->count();

        return [
            'lead_masuk' => $leadMasuk,
            'invoice_outstanding' => $invoiceOutstanding,
            'instalasi_proses' => $instalasiProses,
            'instalasi_selesai' => $instalasiSelesai,
            'po_menunggu' => $poMenunggu,
        ];
    }

    private function getCustomerProgress(Request $request): \Illuminate\Pagination\LengthAwarePaginator
    {
        $query = Customer::query();

        // Search customer
        if ($request->filled('search')) {
            $query->whereLike(['name', 'company'], $request->search);
        }

        // Filter by divisi (latest update source)
        if ($request->filled('divisi')) {
            // We'll filter in the collection since it's complex
        }

        // Fase 1: tanggal aktivitas terakhir per customer via subquery MAX
        // (tanpa memuat baris relasi sama sekali).
        $ordered = $query->select('id')
            ->withMax('leads as latest_lead_at', 'created_at')
            ->withMax('followUps as latest_followup_at', 'follow_up_date')
            ->withMax('projects as latest_project_at', 'start_date')
            ->withMax('invoices as latest_invoice_at', 'issue_date')
            ->withMax('purchaseOrders as latest_po_at', 'issue_date')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'ts' => collect([
                    $c->latest_lead_at, $c->latest_followup_at,
                    $c->latest_project_at, $c->latest_invoice_at, $c->latest_po_at,
                ])->filter()->map(fn ($d) => $d instanceof \DateTimeInterface ? $d->getTimestamp() : (int) strtotime((string) $d))->max() ?? 0,
            ])
            ->sortByDesc('ts')
            ->values();

        $page = \Illuminate\Pagination\Paginator::resolveCurrentPage();
        $pageIds = $ordered->forPage($page, 15)->pluck('id')->all();

        // Fase 2: relasi penuh hanya untuk 15 customer di halaman ini.
        $customers = Customer::with([
            'leads' => fn($q) => $q->latest('created_at'),
            'followUps' => fn($q) => $q->latest('follow_up_date'),
            'projects' => fn($q) => $q->with(['documents', 'status'])->latest('start_date'),
            'invoices' => fn($q) => $q->latest('issue_date'),
            'purchaseOrders' => fn($q) => $q->latest('issue_date'),
        ])->whereIn('id', $pageIds)->get()
            ->sortBy(fn ($c) => array_search($c->id, $pageIds))
            ->values();

        // Hitung aktivitas terbaru sekali per customer (bukan 3x).
        $customers->transform(function ($customer) {
            $latest = $this->getLatestActivity($customer);
            $customer->latest_activity = $latest;
            $customer->overall_status = $latest['overall'] ?? __('New');
            $customer->latest_divisi = $latest['divisi'] ?? null;
            return $customer;
        });

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $customers,
            $ordered->count(),
            15,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );
    }

    private function getLatestActivity(Customer $customer): ?array
    {
        $activities = collect();

        // Lead
        foreach ($customer->leads as $lead) {
            $activities->push([
                'type' => 'lead',
                'label' => 'Lead: ' . ucfirst($lead->status),
                'overall' => 'Lead: ' . ucfirst($lead->status),
                'date' => $lead->created_at,
                'divisi' => 'marketing',
                'icon' => 'briefcase',
                'color' => 'blue',
                'url' => route('leads.show', $lead),
            ]);
        }

        // Follow Up
        foreach ($customer->followUps as $followUp) {
            $activities->push([
                'type' => 'followup',
                'label' => 'Follow Up',
                'overall' => 'Follow Up',
                'date' => $followUp->follow_up_date,
                'divisi' => 'sales',
                'icon' => 'arrow-path',
                'color' => 'emerald',
                'url' => route('sales.follow-ups.show', $followUp),
            ]);
        }

        $projects = $customer->projects;
        foreach ($projects as $project) {
            $statusName = $project->status?->name ?? __('Not Started');
            $activities->push([
                'type' => 'project',
                'label' => 'Project: ' . $statusName,
                'overall' => $statusName,
                'date' => $project->start_date,
                'divisi' => 'teknisi',
                'icon' => 'folder',
                'color' => 'purple',
                'url' => route('projects.show', $project),
            ]);
        }

        // Invoice
        foreach ($customer->invoices as $invoice) {
            $activities->push([
                'type' => 'invoice',
                'label' => 'Invoice: ' . ucfirst($invoice->status),
                'overall' => 'Invoice: ' . ucfirst($invoice->status),
                'date' => $invoice->issue_date,
                'divisi' => 'admin',
                'icon' => 'document-text',
                'color' => 'orange',
                'url' => route('admin.invoices.show', $invoice),
            ]);
        }

        // PO
        foreach ($customer->purchaseOrders as $po) {
            $activities->push([
                'type' => 'po',
                'label' => 'PO: ' . ucfirst($po->status),
                'overall' => 'PO: ' . ucfirst($po->status),
                'date' => $po->issue_date,
                'divisi' => 'admin',
                'icon' => 'clipboard-document',
                'color' => 'amber',
                'url' => route('admin.pos.show', $po),
            ]);
        }

        $latest = $activities->sortByDesc('date')->first();
        return $latest;
    }

    private function getAllStatuses(): array
    {
        // Daftar status project berubah jarang: cache 1 jam
        // (invalidasi di ProjectStatusController).
        $teknisi = \Illuminate\Support\Facades\Cache::remember(
            'master:project-statuses',
            3600,
            fn () => \App\Models\ProjectStatus::orderBy('sort_order')->pluck('name')->all()
        );

        return [
            'marketing' => ['cool', 'warm', 'hot', 'won', 'lost'],
            'sales' => ['followup'],
            'teknisi' => $teknisi,
            'admin' => ['unpaid', 'paid', 'cancelled', 'draft', 'diproses', 'selesai', 'dibatalkan'],
        ];
    }
}
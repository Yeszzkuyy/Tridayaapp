<?php

namespace App\Http\Controllers;

use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadDocument;
use App\Models\Customer;
use App\Models\Partner;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\ProjectStatus;
use App\Models\User;
use App\Models\WorkType;
use App\Models\WhatsappAccount;
use App\Models\WhatsappMessage;
use App\Notifications\LeadAssignedNotification;
use App\Notifications\NewLeadNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class LeadController extends Controller
{
    public const SEGMENTS = ['end_user', 'vendor', 'system_integrator', 'kontraktor', 'gov', 'principle', 'distributor', 'other'];
    public const SOURCES = ['whatsapp', 'email', 'telpon', 'canvasing', 'event', 'website', 'referral', 'social_media', 'other'];
    public const STATUSES = ['cool', 'warm', 'hot', 'won', 'lost'];

    public static function label(string $value): string
    {
        return match ($value) {
            'end_user' => 'End User',
            'system_integrator' => 'System Integrator',
            'gov' => 'Gov',
            'principle' => 'Principle',
            'distributor' => 'Distributor',
            'kontraktor' => __('Kontraktor'),
            'canvasing' => __('Canvasing'),
            'telpon' => __('Telpon'),
            default => __(ucfirst(str_replace('_', ' ', $value))),
        };
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', Lead::class);

        $query = Lead::with(['customer', 'assignee', 'partner'])
            ->when($request->filled('search'), fn ($q) => $q->whereHas('customer',
                fn ($c) => $c->whereLike('name', $request->search)))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('source'), fn ($q) => $q->where('source', $request->source))
            ->when($request->filled('pt_group'), fn ($q) => $q->where('pt_group', $request->pt_group))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('incoming_date', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('incoming_date', '<=', $request->date_to));

        $leads = $query->latest()->paginate(15)->withQueryString();

        $statuses = self::STATUSES;
        $sources = self::SOURCES;
        $ptGroups = Lead::PT_GROUPS;

        return view('leads.index', compact('leads', 'statuses', 'sources', 'ptGroups'));
    }

    public function pipeline(Request $request)
    {
        $this->authorize('viewAny', Lead::class);

        // Pipeline selalu milik 1 sales: hanya lead yang di-assign ke viewer,
        // tanpa pengecualian role (customer tidak campur antar sales).
        $leads = Lead::with(['customer', 'assignee', 'partner'])
            ->where('assigned_to', $request->user()->id)
            ->orderByDesc('incoming_date')
            ->get();

        // Kolom won/lost hanya tampilkan yang ditutup minggu berjalan agar
        // tidak menumpuk, kecuali ?closed=all. Data lama tanpa closed_at
        // pakai updated_at sebagai fallback.
        $showAllClosed = $request->input('closed') === 'all';
        $weekStart = now()->startOfWeek();
        if (!$showAllClosed) {
            $leads = $leads->filter(fn ($lead) => !in_array($lead->status, ['won', 'lost'], true)
                || ($lead->closed_at ?? $lead->updated_at)?->gte($weekStart))->values();
        }

        // Kolom New (virtual, bukan status DB): lead yang di-assign ke sales
        // tapi belum pernah digeser/diubah statusnya. won/lost tidak pernah New.
        $newLeads = $leads->filter(fn ($lead) => $lead->acknowledged_at === null
            && !in_array($lead->status, ['won', 'lost'], true))->values();
        $leads = $leads->reject(fn ($lead) => $newLeads->contains($lead))->values();

        $statuses = self::STATUSES;

        return view('leads.pipeline', compact('leads', 'newLeads', 'statuses', 'showAllClosed', 'weekStart'));
    }

    private function authorizeLeadStatus(Lead $lead): void
    {
        $user = request()->user();
        if ($user && $user->can('manage-marketing')) {
            return;
        }
        if ($user && $user->can('manage-sales') && (int) $lead->assigned_to === (int) $user->id) {
            return;
        }
        abort(403);
    }

    /**
     * Target assignment harus user divisi yang memang memegang lead
     * (sales/marketing), bukan id sembarang lintas divisi.
     */
    private function guardSalesAssignee(mixed $assignedTo): void
    {
        if (empty($assignedTo)) {
            return;
        }

        $assignee = User::find($assignedTo);

        if (! $assignee?->hasAnyRole(['sales', 'marketing'])) {
            abort(422, __('Target assignment harus user Sales atau Marketing.'));
        }
    }

    public function updateStatus(Request $request, Lead $lead)
    {
        $this->authorizeLeadStatus($lead);

        $validated = $request->validate([
            'status' => 'required|in:'.implode(',', self::STATUSES),
        ]);

        if ($validated['status'] !== $lead->status) {
            $old = $lead->status;
            $payload = ['status' => $validated['status']];
            if ($lead->acknowledged_at === null) {
                $payload['acknowledged_at'] = now();
            }
            if ($validated['status'] !== 'lost') {
                $payload['lost_reason'] = null;
                $payload['lost_note'] = null;
            }
            // closed_at = kapan deal ditutup (acuan filter mingguan pipeline).
            $payload['closed_at'] = in_array($validated['status'], ['won', 'lost'], true) ? now() : null;
            $lead->update($payload);

            $this->logActivity($lead, 'status_changed', [
                'status' => ['old' => $old, 'new' => $validated['status']],
            ]);
        }

        return response()->noContent();
    }

    public function batchUpdateStatus(Request $request)
    {
        $validated = $request->validate([
            'changes' => 'required|array',
            'changes.*.lead_id' => 'required|exists:leads,id',
            'changes.*.status' => 'required|in:'.implode(',', self::STATUSES),
        ]);

        $results = [];
        foreach ($validated['changes'] as $change) {
            $lead = Lead::find($change['lead_id']);
            $this->authorizeLeadStatus($lead);
            $old = $lead->status;

            if ($old !== $change['status']) {
                $payload = ['status' => $change['status']];
                if ($lead->acknowledged_at === null) {
                    $payload['acknowledged_at'] = now();
                }
                if ($change['status'] !== 'lost') {
                    $payload['lost_reason'] = null;
                    $payload['lost_note'] = null;
                }
                $payload['closed_at'] = in_array($change['status'], ['won', 'lost'], true) ? now() : null;
                $lead->update($payload);

                $this->logActivity($lead, 'status_changed', [
                    'status' => ['old' => $old, 'new' => $change['status']],
                ]);

                $results[] = ['lead_id' => $lead->id, 'old' => $old, 'new' => $change['status']];
            }
        }

        return response()->json(['updated' => count($results), 'changes' => $results]);
    }

    public function dashboard(Request $request)
    {
        $now = now();
        $dateFrom = $request->filled('date_from') ? $request->date_from : $now->copy()->subMonths(5)->startOfMonth()->toDateString();
        $dateTo = $request->filled('date_to') ? $request->date_to : $now->toDateString();

        if ($dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        // whereDate di kedua batas agar lead hari ini ikut terhitung (cast 'date' menyimpan jam juga)
        $statusCounts = Lead::whereDate('incoming_date', '>=', $dateFrom)->whereDate('incoming_date', '<=', $dateTo)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $countMonth = fn ($date) => Lead::whereDate('incoming_date', '>=', $dateFrom)->whereDate('incoming_date', '<=', $dateTo)
            ->whereMonth('incoming_date', $date->month)
            ->whereYear('incoming_date', $date->year)->count();

        $won = (int) ($statusCounts['won'] ?? 0);
        $lost = (int) ($statusCounts['lost'] ?? 0);

        $stats = [
            'total' => array_sum($statusCounts->all()),
            'this_month' => $countMonth($now),
            'last_month' => $countMonth($now->copy()->subMonthNoOverflow()),
            'active' => array_sum($statusCounts->all()) - $won - $lost,
            'won' => $won,
            'lost' => $lost,
            'conversion' => ($won + $lost) > 0 ? (int) round($won / ($won + $lost) * 100) : 0,
        ];

        // Agregat di SQL; COALESCE/NULLIF portabel MySQL/Postgres/SQLite.
        $perSource = Lead::whereDate('incoming_date', '>=', $dateFrom)->whereDate('incoming_date', '<=', $dateTo)
            ->selectRaw("COALESCE(NULLIF(source, ''), 'lainnya') as source, count(*) as total")
            ->groupBy('source')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => (object) ['source' => $row->source ?? 'lainnya', 'total' => (int) $row->total])
            ->values();

        // Agregat per bulan di SQL; ekspresi bulan beda per driver
        // (substr(date) jalan di SQLite tapi tidak di Postgres).
        $monthExpr = match (\Illuminate\Support\Facades\DB::getDriverName()) {
            'pgsql' => "to_char(incoming_date, 'YYYY-MM')",
            'mysql' => "DATE_FORMAT(incoming_date, '%Y-%m')",
            default => 'substr(incoming_date, 1, 7)',
        };
        $trendQuery = Lead::whereDate('incoming_date', '>=', $dateFrom)->whereDate('incoming_date', '<=', $dateTo)
            ->selectRaw("{$monthExpr} as ym, count(*) as total")
            ->groupBy('ym')
            ->pluck('total', 'ym');

        $months = max(0, now()->parse($dateFrom)->diffInMonths(now()->parse($dateTo)));
        $trend = $months < 1
            ? collect([(object) [
                'label' => now()->parse($dateTo)->translatedFormat('M y'),
                'total' => $trendQuery[now()->parse($dateTo)->format('Y-m')] ?? 0,
            ]])
            : collect(range(0, $months))->map(function ($i) use ($dateTo, $trendQuery) {
                $month = now()->parse($dateTo)->subMonthsNoOverflow($i);

                return (object) [
                    'label' => $month->translatedFormat('M y'),
                    'total' => $trendQuery[sprintf('%04d-%02d', $month->year, $month->month)] ?? 0,
                ];
            })->reverse()->values();

        // Funnel: jumlah lead per status, diurutkan sesuai STATUSES (New → Lost)
        $funnel = collect(self::STATUSES)->map(fn ($status) => [
            'label' => ucfirst($status),
            'value' => (int) ($statusCounts[$status] ?? 0),
            'key'   => $status,
        ]);

        // Warna segmen donut — SAMAKAN dengan warna badge status di view lain.
        // Status = SEMANTIK, tidak mengikuti accent. Palet tetap.
        // 'new' = --semantic-info (#3b82f6).
        // (Diracik di controller, bukan blok @php di Blade: regex storePhpBlocks
        //  Laravel menelan @php(...) inline sebagai pembuka blok bila ada
        //  @endphp lain di file yang sama.)
        $statusPalette = [
            'cool' => '#3b82f6',
            'warm' => '#eab308',
            'hot' => '#f97316',
            'won' => '#22c55e',
            'lost' => '#ef4444',
        ];
        $donutMarketing = $funnel->map(fn ($s) => [
            'label' => $s['label'],
            'value' => $s['value'],
            'key' => $s['key'],
            'color' => $statusPalette[$s['key']] ?? '#64748b',
        ])->values();
        $funnelTotal = $donutMarketing->sum('value');

        // Detail lead per status (untuk tabel dinamis di dashboard)
        $leadsByStatus = Lead::with(['customer', 'partner'])
            ->whereDate('incoming_date', '>=', $dateFrom)
            ->whereDate('incoming_date', '<=', $dateTo)
            ->get(['id', 'customer_id', 'partner_id', 'status', 'source', 'segment', 'incoming_date'])
            ->groupBy('status')
            ->map(fn ($leads) => $leads->map(fn ($lead) => [
                'id' => $lead->id,
                'name' => $lead->customer?->name ?? '-',
                'company' => $lead->customer?->company && $lead->customer->company !== $lead->customer?->name
                    ? $lead->customer->company
                    : null,
                'source' => $lead->source ? ucfirst(str_replace('_', ' ', $lead->source)) : '-',
                'partner' => $lead->partner?->name ?? '-',
                'date' => $lead->incoming_date?->format('d M Y') ?? '-',
            ]));

        return view('marketing.dashboard', compact('stats', 'perSource', 'trend', 'statusCounts', 'dateFrom', 'dateTo', 'funnel', 'donutMarketing', 'funnelTotal', 'leadsByStatus'));
    }

    public function create(Request $request)
    {
        $this->authorize('create', Lead::class);

        $customers = Customer::orderBy('name')->get(['id', 'name']);
        $partners = Partner::orderBy('name')->get(['id', 'name', 'type']);
        $segments = self::SEGMENTS;
        $sources = self::SOURCES;
        $ptGroups = Lead::PT_GROUPS;

        $prefill = $this->prefillFromWhatsapp($request);

        return view('leads.create', compact('customers', 'partners', 'segments', 'sources', 'ptGroups', 'prefill'));
    }

    /**
     * Prefill form Tambah Lead dari konteks WhatsApp Center (nomor pengirim +
     * kebutuhan otomatis dari pesan masuk terakhir).
     *
     * @return array<string, mixed>
     */
    private function prefillFromWhatsapp(Request $request): array
    {
        if (! $request->filled('sender') && ! $request->filled('whatsapp_account_id')) {
            return [];
        }

        $sender = (string) $request->input('sender', '');
        $account = $request->filled('whatsapp_account_id')
            ? WhatsappAccount::find($request->integer('whatsapp_account_id'))
            : null;

        $kebutuhan = $request->input('kebutuhan');

        if (blank($kebutuhan) && $account && $sender !== '') {
            $kebutuhan = app(\App\Services\WhatsappBot::class)->summarizeNeeds($account, $sender);

            if (blank($kebutuhan)) {
                $kebutuhan = WhatsappMessage::where('whatsapp_account_id', $account->id)
                    ->where('sender_number', $sender)
                    ->where('direction', 'inbound')
                    ->latest('id')
                    ->limit(10)
                    ->get()
                    ->sortBy('id')
                    ->pluck('message_body')
                    ->filter()
                    ->implode("\n");

                $kebutuhan = $kebutuhan !== '' ? Str::limit($kebutuhan, 2000, '') : null;
            }
        }

        // Perusahaan hanya di-prefill bila benar-benar diketahui (customer tersimpan).
        // Nama orang pengirim masuk ke PIC, bukan ke Perusahaan.
        $company = (string) $request->input('company', $request->input('name', ''));
        $pic = (string) $request->input('pic', '');
        if ($pic !== '' && $pic === $sender) {
            $pic = '';
        }

        return array_filter([
            'customer_mode' => 'new',
            'customer_name' => $company,
            'customer_contact_person' => $pic,
            'customer_whatsapp' => $sender,
            'source' => 'whatsapp',
            'pt_group' => $account ? strtoupper(substr($account->account_code, 3)) : null,
            'whatsapp_account_id' => $account?->id,
            'kebutuhan' => $kebutuhan,
        ], fn ($v) => filled($v));
    }

    public function store(Request $request)
    {
        $this->authorize('create', Lead::class);

        $validated = $request->validate([
            'customer_mode' => 'required|in:new,existing',
            'customer_name' => 'nullable|required_if:customer_mode,new|string|max:255',
            'customer_company' => 'nullable|string|max:255',
            'customer_email' => 'nullable|email|max:255',
            'customer_phone' => 'nullable|string|max:50',
            'customer_whatsapp' => 'nullable|string|max:50',
            'customer_address' => 'nullable|string|max:1000',
            'customer_id' => 'nullable|required_if:customer_mode,existing|exists:customers,id',
            'customer_contact_person' => 'nullable|string|max:255',
            'partner_id' => 'nullable|exists:partners,id',
            'pt_group' => 'required|in:'.implode(',', Lead::PT_GROUPS),
            'assigned_to' => 'nullable|exists:users,id',
            'whatsapp_account_id' => 'nullable|exists:whatsapp_accounts,id',
            'segment' => 'required|in:'.implode(',', self::SEGMENTS),
            'source' => 'nullable|in:'.implode(',', self::SOURCES),
            'kebutuhan' => 'nullable|string|max:2000',
            'solusi' => 'nullable|string|max:2000',
            'progress_notes' => 'nullable|string|max:2000',
            'notes' => 'nullable|string|max:5000',
            'incoming_date' => 'required|date',
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => ['file', 'max:10240', \App\Rules\SecureFile::documents()],
        ]);

        $this->guardSalesAssignee($validated['assigned_to'] ?? null);

        if ($validated['customer_mode'] === 'new') {
            $validated['customer_id'] = Customer::create([
                'name' => $validated['customer_name'],
                'company' => $validated['customer_company'] ?? $validated['customer_name'],
                'pt_group' => $validated['pt_group'],
                'email' => $validated['customer_email'] ?? null,
                'phone' => $validated['customer_phone'] ?? null,
                'whatsapp' => $validated['customer_whatsapp'] ?? null,
                'address' => $validated['customer_address'] ?? null,
                'contact_person' => $validated['customer_contact_person'] ?? null,
            ])->id;
        } else {
            $customer = Customer::withTrashed()->findOrFail($validated['customer_id']);
            $customerSync = ['pt_group' => $validated['pt_group']];
            if (!empty($validated['customer_contact_person'])) {
                $customerSync['contact_person'] = $validated['customer_contact_person'];
            }
            $customer->update($customerSync);
        }

        unset(
            $validated['customer_mode'],
            $validated['customer_name'],
            $validated['customer_company'],
            $validated['customer_email'],
            $validated['customer_phone'],
            $validated['customer_whatsapp'],
            $validated['customer_address'],
            $validated['customer_contact_person'],
            $validated['attachments'],
        );

        $validated['status'] ??= 'cool';

        $lead = Lead::create($validated);
        $this->saveAttachments($request, $lead);
        $this->logActivity($lead, 'created');

        if (!empty($lead->assigned_to)) {
            // Langsung di-assign saat dibuat: hanya sales itu yang diberi tahu.
            $lead->forceFill([
                'assigned_by' => auth()->id(),
                'assigned_at' => now(),
            ])->saveQuietly();
            $this->notifyAssignee($lead, null);
        } else {
            Notification::send(
                User::permission('manage-sales-leads')->get(),
                new NewLeadNotification($lead)
            );
        }

        return redirect()
            ->route('leads.index')
            ->with('success', __('Lead berhasil ditambahkan'))
            ->with('success_card', true);
    }

    public function show(Lead $lead)
    {
        $this->authorize('view', $lead);

        $lead->load(['customer', 'partner', 'assignee', 'tasks.assignee', 'activities.user', 'customer.projects.documents', 'meetings.creator', 'followUps.creator', 'salesSchedules.assignee', 'technicalRequests.requester', 'technicalRequests.technician', 'proposals.creator']);
        $documents = $lead->customer ? $lead->customer->projects->flatMap->documents : collect();
        $projectStatuses = ProjectStatus::orderBy('sort_order')->get(['id', 'name']);
        $workTypes = WorkType::orderBy('name')->get(['id', 'name']);

        // Riwayat gabungan meeting + follow-up milik lead ini, terbaru dulu.
        // Link detail hanya untuk yang boleh manage-sales (role sales memilikinya).
        $canOpenDetail = auth()->user()?->can('manage-sales');
        $timeline = $lead->meetings->map(fn ($m) => [
            'type' => 'meeting',
            'date' => $m->meeting_date,
            'title' => __('Meeting'),
            'summary' => $m->user_needs ?: $m->notes,
            'by' => $m->creator?->name,
            'url' => $canOpenDetail ? route('sales.meetings.show', $m) : null,
        ])->concat($lead->followUps->map(fn ($f) => [
            'type' => 'follow-up',
            'date' => $f->follow_up_date ?? $f->created_at,
            'time' => $f->activity_time,
            'location' => $f->location,
            'title' => FollowUp::typeLabel($f->type),
            'summary' => $f->description,
            'by' => $f->creator?->name,
            'url' => $canOpenDetail ? route('sales.follow-ups.show', $f) : null,
        ]))->sortByDesc('date')->values();

        // Sales flow: jadwal (upcoming/past), technical request, proposal milik lead ini.
        $upcomingSchedules = $lead->salesSchedules
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->sortBy('start_at')->values();
        $pastSchedules = $lead->salesSchedules
            ->where(fn ($s) => in_array($s->status, ['completed', 'cancelled']) || ($s->start_at && $s->start_at->isPast()))
            ->sortByDesc('start_at')->values();
        $techRequests = $lead->technicalRequests->sortByDesc('created_at')->values();
        $activeTechRequest = $techRequests->first(fn ($r) => ! in_array($r->status, ['completed', 'cancelled', 'rejected']));
        $proposalsList = $lead->proposals->sortByDesc('created_at')->values();

        return view('leads.show', compact('lead', 'documents', 'projectStatuses', 'workTypes', 'timeline', 'upcomingSchedules', 'pastSchedules', 'techRequests', 'activeTechRequest', 'proposalsList'));
    }

    public function edit(Lead $lead)
    {
        $this->authorize('update', $lead);

        $customers = Customer::orderBy('name')->get(['id', 'name']);
        $partners = Partner::orderBy('name')->get(['id', 'name', 'type']);
        $segments = self::SEGMENTS;
        $sources = self::SOURCES;
        $ptGroups = Lead::PT_GROUPS;

        return view('leads.edit', compact('lead', 'customers', 'partners', 'segments', 'sources', 'ptGroups'));
    }

    public function update(Request $request, Lead $lead)
    {
        $this->authorize('update', $lead);

        $validated = $request->validate([
            'customer_mode' => 'required|in:new,existing',
            'customer_name' => 'nullable|required_if:customer_mode,new|string|max:255',
            'customer_company' => 'nullable|string|max:255',
            'customer_email' => 'nullable|email|max:255',
            'customer_phone' => 'nullable|string|max:50',
            'customer_whatsapp' => 'nullable|string|max:50',
            'customer_address' => 'nullable|string|max:1000',
            'customer_id' => 'nullable|required_if:customer_mode,existing|exists:customers,id',
            'customer_contact_person' => 'nullable|string|max:255',
            'partner_id' => 'nullable|exists:partners,id',
            'pt_group' => 'required|in:'.implode(',', Lead::PT_GROUPS),
            'assigned_to' => 'nullable|exists:users,id',
            'segment' => 'required|in:'.implode(',', self::SEGMENTS),
            'source' => 'nullable|in:'.implode(',', self::SOURCES),
            'kebutuhan' => 'nullable|string|max:2000',
            'solusi' => 'nullable|string|max:2000',
            'progress_notes' => 'nullable|string|max:2000',
            'notes' => 'nullable|string|max:5000',
            'incoming_date' => 'required|date',
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => ['file', 'max:10240', \App\Rules\SecureFile::documents()],
        ]);

        $this->guardSalesAssignee($validated['assigned_to'] ?? null);

        if ($validated['customer_mode'] === 'new') {
            $validated['customer_id'] = Customer::create([
                'name' => $validated['customer_name'],
                'company' => $validated['customer_company'] ?? $validated['customer_name'],
                'pt_group' => $validated['pt_group'],
                'email' => $validated['customer_email'] ?? null,
                'phone' => $validated['customer_phone'] ?? null,
                'whatsapp' => $validated['customer_whatsapp'] ?? null,
                'address' => $validated['customer_address'] ?? null,
                'contact_person' => $validated['customer_contact_person'] ?? null,
            ])->id;
        } else {
            $customerData = array_filter([
                'name' => $validated['customer_name'] ?? null,
                'contact_person' => $validated['customer_contact_person'] ?? null,
                'address' => $validated['customer_address'] ?? null,
                'phone' => $validated['customer_phone'] ?? null,
                'whatsapp' => $validated['customer_whatsapp'] ?? null,
                'email' => $validated['customer_email'] ?? null,
                'pt_group' => $validated['pt_group'] ?? null,
            ], fn ($value) => $value !== null);

            if ($customerData) {
                $customer = Customer::withTrashed()->findOrFail($validated['customer_id']);

                // Pindah lead ke customer lain = butuh hak tulis customer.
                // Touch-up customer yang sama tetap boleh (alur marketing).
                if ((int) $customer->id !== (int) $lead->customer_id) {
                    $this->authorize('update', $customer);
                }

                $customer->update($customerData);
            }
        }

        unset(
            $validated['customer_mode'],
            $validated['customer_name'],
            $validated['customer_company'],
            $validated['customer_email'],
            $validated['customer_phone'],
            $validated['customer_whatsapp'],
            $validated['customer_address'],
            $validated['customer_contact_person'],
            $validated['attachments'],
        );

        $lead->fill($validated);

        $changes = [];
        foreach ($validated as $field => $value) {
            if ($lead->isDirty($field)) {
                $changes[$field] = ['old' => $lead->getOriginal($field), 'new' => $value];
            }
        }

        $previousAssignee = $lead->getOriginal('assigned_to');
        $lead->save();
        $this->syncAttachments($request, $lead);

        // Ganti assignee saat edit: hanya sales baru yang diberi tahu.
        if (!empty($lead->assigned_to) && (int) $lead->assigned_to !== (int) $previousAssignee) {
            $this->notifyAssignee($lead, $previousAssignee);
        }

        if ($changes) {
            $this->logActivity($lead, 'updated', $changes);
        }

        return redirect()
            ->route('leads.index')
            ->with('success', __('Lead berhasil diupdate'));
    }

    public function destroy(Lead $lead)
    {
        $this->authorize('delete', $lead);

        $this->logActivity($lead, 'deleted');
        $lead->delete();

        return redirect()
            ->route('leads.index')
            ->with('success', __('Lead berhasil dihapus'));
    }

    public function saveOutcome(Request $request, Lead $lead)
    {
        $this->authorizeLeadStatus($lead);

        $validated = $request->validate([
            'lost_reason' => 'nullable|in:'.implode(',', Lead::LOST_REASONS),
            'lost_note' => 'nullable|string|max:2000',
        ]);

        $lead->fill($validated);
        $changes = [];
        foreach (['lost_reason', 'lost_note'] as $field) {
            if ($lead->isDirty($field)) {
                $changes[$field] = ['old' => $lead->getOriginal($field), 'new' => $lead->{$field}];
            }
        }
        $lead->save();

        if ($changes) {
            $this->logActivity($lead, 'updated', $changes);
        }

        return back()->with('success', __('Hasil lead tersimpan.'));
    }

    public function categorizeAttachment(Request $request, Lead $lead, LeadDocument $document)
    {
        $this->authorize('update', $lead);
        abort_unless((int) $document->lead_id === (int) $lead->id, 404);

        $validated = $request->validate([
            'category' => 'nullable|in:'.implode(',', LeadDocument::CATEGORIES),
        ]);
        $document->update(['category' => $validated['category']]);

        return back()->with('success', __('Kategori dokumen tersimpan.'));
    }

    public function convert(Lead $lead)
    {
        $user = request()->user();
        $isOwner = $user && (int) $lead->assigned_to === (int) $user->id;
        $allowed = $user && ($user->can('manage-marketing')
            || ($isOwner && $user->can('manage-sales'))
            || $user->can('manage-technician')
            || $user->can('manage-admin'));

        abort_unless($allowed, 403);

        // Idempoten: lead yang sudah won/lost tidak boleh di-convert ulang.
        if (in_array($lead->status, ['won', 'lost'], true)) {
            $existing = Project::where('customer_id', $lead->customer_id)->latest()->first();

            return redirect()
                ->route($existing ? 'projects.show' : 'leads.show', $existing ?? $lead)
                ->with('error', __('Lead sudah dikonversi sebelumnya.'));
        }

        $validated = request()->validate([
            'project_name' => 'required|string|max:255',
            'project_status_id' => 'required|exists:project_statuses,id',
            'work_type_id' => 'required|exists:work_types,id',
            'closing_note' => 'nullable|string|max:2000',
        ]);

        $project = Project::create([
            'project_name' => $validated['project_name'],
            'customer_id' => $lead->customer_id,
            'project_status_id' => $validated['project_status_id'],
            'work_type_id' => $validated['work_type_id'] ?? null,
            'start_date' => now()->toDateString(),
        ]);

        $lead->update([
            'status' => 'won',
            'closed_at' => now(),
            'closing_note' => $validated['closing_note'] ?? null,
        ]);
        $this->logActivity($lead, 'converted', ['project_id' => $project->id]);

        return redirect()
            ->route('projects.show', $project)
            ->with('success', __('Lead berhasil dikonversi ke Project'));
    }

    public function activities(Request $request)
    {
        $activities = LeadActivity::with(['lead.customer', 'user'])
            ->when($request->filled('user'), fn ($q) => $q->where('user_id', $request->user))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $filterUser = $request->filled('user') ? User::find($request->user) : null;

        return view('leads.activities', compact('activities', 'filterUser'))
            ->with('customerNames', Customer::pluck('name', 'id'))
            ->with('userNames', User::pluck('name', 'id'));
    }

    public function monitoring()
    {
        $statuses = ['cool', 'warm', 'hot', 'won', 'lost'];

        $groups = collect([
            'Marketing' => User::role('marketing')->orderBy('name')->get(['id', 'name']),
            'Sales' => User::role('sales')->orderBy('name')->get(['id', 'name']),
        ]);

        $ids = $groups->flatten()->pluck('id');

        $counts = Lead::selectRaw('assigned_to, status, count(*) as total')
            ->whereIn('assigned_to', $ids)
            ->groupBy('assigned_to', 'status')
            ->get()
            ->groupBy('assigned_to');

        $lastActivities = LeadActivity::selectRaw('user_id, max(created_at) as last_at')
            ->whereIn('user_id', $ids)
            ->groupBy('user_id')
            ->pluck('last_at', 'user_id')
            ->map(fn ($v) => \Illuminate\Support\Carbon::parse($v));

        $buildRow = fn ($u) => (object) [
            'user' => $u,
            'counts' => collect($statuses)->mapWithKeys(
                fn ($s) => [$s => optional($counts->get($u->id))->firstWhere('status', $s)->total ?? 0]
            ),
            'total' => $counts->get($u->id)?->sum('total') ?? 0,
            'lastActivityAt' => $lastActivities->get($u->id),
        ];

        $summary = $groups->mapWithKeys(
            fn ($users, $division) => [$division => $users->map($buildRow)]
        );

        return view('leads.monitoring', compact('summary', 'statuses'));
    }

    public function showAttachment(Lead $lead, LeadDocument $document)
    {
        $this->authorize('view', $lead);

        if ($document->lead_id !== $lead->id) {
            abort(404);
        }

        return \App\Rules\SecureFile::fileResponse('private', $document->file_path, $document->file_name);
    }

    public function downloadAttachment(Lead $lead, LeadDocument $document)
    {
        $this->authorize('view', $lead);

        if ($document->lead_id !== $lead->id) {
            abort(404);
        }

        return Storage::disk('private')->download($document->file_path, $document->file_name);
    }

    public function destroyAttachment(Lead $lead, LeadDocument $document)
    {
        $this->authorize('update', $lead);

        if ($document->lead_id !== $lead->id) {
            abort(404);
        }

        Storage::disk('private')->delete($document->file_path);
        $document->delete();

        $this->logActivity($lead, 'attachment_deleted', [
            'file' => ['old' => $document->file_name, 'new' => null],
        ]);

        return back()->with('success', __('Lampiran berhasil dihapus'));
    }

    private function saveAttachments(Request $request, Lead $lead): void
    {
        foreach ($request->file('attachments', []) as $file) {
            $path = $file->storeAs(
                "leads/{$lead->id}",
                \Illuminate\Support\Str::uuid() . '.' . strtolower($file->getClientOriginalExtension()),
                'private'
            );
            $lead->documents()->create([
                'file_name' => \App\Rules\SecureFile::sanitizeName($file->getClientOriginalName()),
                'file_path' => $path,
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            ]);
        }
    }

    private function syncAttachments(Request $request, Lead $lead): void
    {
        foreach ($request->file('attachments', []) as $file) {
            $path = $file->storeAs(
                "leads/{$lead->id}",
                \Illuminate\Support\Str::uuid() . '.' . strtolower($file->getClientOriginalExtension()),
                'private'
            );
            $lead->documents()->create([
                'file_name' => \App\Rules\SecureFile::sanitizeName($file->getClientOriginalName()),
                'file_path' => $path,
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            ]);
        }
    }

    private function logActivity(Lead $lead, string $action, ?array $changes = null): void
    {
        LeadActivity::create([
            'lead_id' => $lead->id,
            'user_id' => auth()->id(),
            'action' => $action,
            'changes' => $changes,
        ]);
    }

    /**
     * Notifikasi assign hanya ke sales yang dituju (tidak broadcast).
     */
    private function notifyAssignee(Lead $lead, mixed $previousAssignee): void
    {
        if (empty($lead->assigned_to)
            || (int) $lead->assigned_to === (int) $previousAssignee
            || (int) $lead->assigned_to === (int) auth()->id()) {
            return;
        }

        try {
            User::find($lead->assigned_to)?->notify(new LeadAssignedNotification($lead));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function previewDocument(Lead $lead, ProjectDocument $document)
    {
        $this->authorize('view', $lead);

        if ($document->project->customer_id !== $lead->customer_id) {
            abort(404);
        }

        // MIME dari isi file + hanya tipe aman yang inline (svg ikut download).
        return \App\Rules\SecureFile::fileResponse('private', $document->file_path, $document->file_name);
    }

    public function downloadDocument(Lead $lead, ProjectDocument $document)
    {
        $this->authorize('view', $lead);

        if ($document->project->customer_id !== $lead->customer_id) {
            abort(404);
        }

        if (!Storage::disk('private')->exists($document->file_path)) {
            abort(404, 'File tidak ditemukan.');
        }

        return Storage::disk('private')->download($document->file_path, $document->file_name);
    }

    public function importForm()
    {
        return view('leads.import');
    }

    /**
     * Normalisasi 1 baris import (murni, tanpa query).
     *
     * @return array{row:int, company:string, sales:mixed, customer_attrs:array, lead_attrs:array}
     */
    private function normalizeImportRow(array $data): array
    {
        $companyName = $data['nama_perusahaan'] ?? $data['company'] ?? $data['perusahaan'] ?? null;
        if (empty($companyName) || ! is_string($companyName)) {
            throw new \Exception('Nama perusahaan kosong');
        }
        $companyName = trim($companyName);

        $ptGroup = $data['pt'] ?? $data['pt_group'] ?? 'NTI';
        if (! in_array($ptGroup, Lead::PT_GROUPS, true)) {
            throw new \Exception("PT grup '{$ptGroup}' tidak valid");
        }

        $segment = $data['segment'] ?? 'other';
        if (! in_array($segment, self::SEGMENTS, true)) {
            throw new \Exception("Segment '{$segment}' tidak valid");
        }

        $source = $data['source'] ?? $data['masuk_by'] ?? null;
        if ($source !== null && $source !== '' && ! in_array($source, self::SOURCES, true)) {
            throw new \Exception("Source '{$source}' tidak valid");
        }

        $incomingDate = $data['tanggal'] ?? $data['date'] ?? $data['incoming_date'] ?? now()->toDateString();
        try {
            $incomingDate = \Illuminate\Support\Carbon::parse($incomingDate)->toDateString();
        } catch (\Throwable) {
            $incomingDate = now()->toDateString();
        }

        return [
            'company' => $companyName,
            'sales' => $data['sales'] ?? $data['assigned_to'] ?? null,
            'customer_attrs' => [
                'name' => $companyName,
                'company' => $companyName,
                'pt_group' => $ptGroup,
                'address' => $data['alamat'] ?? $data['address'] ?? null,
                'phone' => $data['telp'] ?? $data['phone'] ?? $data['telepon'] ?? null,
                'whatsapp' => $data['wa'] ?? $data['whatsapp'] ?? null,
                'email' => $data['email'] ?? null,
                'contact_person' => $data['pic'] ?? $data['contact_person'] ?? null,
            ],
            'lead_attrs' => [
                'pt_group' => $ptGroup,
                'segment' => $segment,
                'source' => $source ?: null,
                'kebutuhan' => $data['kebutuhan'] ?? null,
                'solusi' => $data['solusi'] ?? null,
                'progress_notes' => $data['progress'] ?? $data['followup'] ?? null,
                'notes' => $data['catatan'] ?? $data['notes'] ?? null,
                'incoming_date' => $incomingDate,
            ],
        ];
    }

    /**
     * Cocokkan kolom sales ke user: id eksak dulu, lalu nama eksak
     * (case-insensitive). Tanpa LIKE fuzzy — salah assign lebih buruk
     * daripada tidak assign.
     */
    private function resolveImportAssignee(mixed $salesVal, array $usersById, array $usersByNameLower): mixed
    {
        if (empty($salesVal)) {
            return null;
        }

        if (is_numeric($salesVal) && isset($usersById[(int) $salesVal])) {
            return (int) $salesVal;
        }

        return $usersByNameLower[mb_strtolower(trim((string) $salesVal))] ?? null;
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'max:10240', \App\Rules\SecureFile::spreadsheets()],
        ]);

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());

        try {
            if (in_array($extension, ['xlsx', 'xls'])) {
                $spreadsheet = IOFactory::load($file->getRealPath());
                $sheet = $spreadsheet->getActiveSheet();
                $rows = $sheet->toArray();
            } else {
                $rows = [];
                $handle = fopen($file->getRealPath(), 'r');
                while (($row = fgetcsv($handle, 0, ',')) !== false) {
                    $rows[] = $row;
                }
                fclose($handle);
            }

            if (count($rows) < 2) {
                return back()->withErrors(['file' => 'File kosong atau hanya berisi header.']);
            }

            $headers = array_map(fn ($h) => strtolower(trim(str_replace([' ', "\t"], '_', $h))), $rows[0]);
            $results = ['success' => 0, 'failed' => 0, 'errors' => []];

            // Pass 1: normalisasi + validasi per baris (tanpa query).
            $parsed = [];
            for ($i = 1; $i < count($rows); $i++) {
                $row = $rows[$i];
                if (empty(array_filter($row)) || count($row) < count($headers)) continue;

                $data = array_combine($headers, $row);
                $rowNum = $i + 1;

                try {
                    $parsed[] = $this->normalizeImportRow($data) + ['row' => $rowNum];
                } catch (\Exception $e) {
                    $results['failed']++;
                    $results['errors'][] = "Baris {$rowNum}: {$e->getMessage()}";
                }
            }

            // Preload sekali: user (id + nama eksak) dan customer yang sudah ada.
            $usersById = User::pluck('id', 'id')->all();
            $usersByName = User::pluck('id', 'name')->all();
            $usersByNameLower = [];
            foreach ($usersByName as $name => $id) {
                $usersByNameLower[mb_strtolower($name)] = $id;
            }
            $wantedNames = collect($parsed)->pluck('company')->unique()->values()->all();
            $customerIds = Customer::whereIn('name', $wantedNames)->pluck('id', 'name')->all();

            $now = now();
            $leadsBatch = [];

            \Illuminate\Support\Facades\DB::transaction(function () use ($parsed, &$customerIds, &$leadsBatch, &$results, $usersById, $usersByNameLower, $now) {
                // Customer baru: bulk insert sekaligus, bukan firstOrCreate per baris.
                $newNames = collect($parsed)->pluck('company')->unique()->reject(fn ($n) => isset($customerIds[$n]))->values();
                if ($newNames->isNotEmpty()) {
                    $byName = collect($parsed)->keyBy('company');
                    Customer::insert($newNames->map(fn ($name) => array_merge(
                        $byName[$name]['customer_attrs'],
                        ['created_at' => $now, 'updated_at' => $now]
                    ))->all());
                    $customerIds += Customer::whereIn('name', $newNames->all())->pluck('id', 'name')->all();
                }

                foreach ($parsed as $item) {
                    $customerId = $customerIds[$item['company']] ?? null;
                    if (! $customerId) {
                        $results['failed']++;
                        $results['errors'][] = "Baris {$item['row']}: customer gagal disimpan";
                        continue;
                    }

                    $leadsBatch[] = array_merge($item['lead_attrs'], [
                        'customer_id' => $customerId,
                        'assigned_to' => $this->resolveImportAssignee($item['sales'], $usersById, $usersByNameLower),
                        'status' => 'cool',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $results['success']++;
                }

                foreach (array_chunk($leadsBatch, 500) as $chunk) {
                    Lead::insert($chunk);
                }
            });

            return back()->with('import_results', $results);
        } catch (\Exception $e) {
            return back()->withErrors(['file' => 'Gagal memproses file: ' . $e->getMessage()]);
        }
    }
}
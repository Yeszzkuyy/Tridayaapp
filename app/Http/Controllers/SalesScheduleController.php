<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\SalesSchedule;
use App\Models\User;
use App\Notifications\SalesScheduleAssignedNotification;
use App\Support\PtAccess;
use Illuminate\Http\Request;

class SalesScheduleController extends Controller
{
    private function ownScope($query)
    {
        $user = auth()->user();

        if ($user->can('manage-sales-leads')) {
            return $query;
        }

        return $query->where(function ($q) use ($user) {
            $q->where('sales_schedules.assigned_to', $user->id)
                ->orWhere('sales_schedules.created_by', $user->id)
                ->orWhereHas('lead', fn ($l) => $l->where('assigned_to', $user->id));
        });
    }

    private function authorizeSchedule(SalesSchedule $schedule): void
    {
        $this->authorize('view', $schedule->lead);

        $user = auth()->user();
        $involved = in_array($user->id, [$schedule->assigned_to, $schedule->created_by, $schedule->lead->assigned_to]);

        abort_unless($involved || $user->can('manage-sales-leads'), 403);
    }

    private function authorizeManage(SalesSchedule $schedule): void
    {
        $this->authorizeSchedule($schedule);

        $user = auth()->user();
        abort_unless(
            in_array($user->id, [$schedule->assigned_to, $schedule->created_by])
                || $user->can('manage-sales-leads'),
            403
        );
    }

    private function log(Lead $lead, string $action, array $changes = []): void
    {
        LeadActivity::create([
            'lead_id' => $lead->id,
            'user_id' => auth()->id(),
            'action' => $action,
            'changes' => $changes,
        ]);
    }

    public function index(Request $request)
    {
        $tab = $request->get('tab', 'upcoming');

        $query = $this->ownScope(
            SalesSchedule::with(['lead.customer', 'assignee'])->orderBy('start_at')
        );

        if ($tab === 'past') {
            $query->where(function ($q) {
                $q->where('start_at', '<', now()->startOfDay())
                    ->orWhereIn('status', ['completed', 'cancelled']);
            })->orderByDesc('start_at');
        } else {
            $query->where('start_at', '>=', now()->startOfDay())
                ->whereNotIn('status', ['completed', 'cancelled']);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->get('type'));
        }

        $schedules = $query->paginate(15)->withQueryString();

        return view('sales.schedules.index', [
            'schedules' => $schedules,
            'tab' => $tab,
            'customers' => $this->customerOptions(auth()->user()),
            'leadItems' => $this->leadItems(auth()->user()),
        ]);
    }

    /**
     * JSON feed untuk FullCalendar: jadwal kunjungan dari tabel follow_ups.
     * Sales biasa hanya melihat yang ia buat; management (manage-sales-leads)
     * melihat semua jadwal.
     */
    public function getEvents(Request $request)
    {
        $query = FollowUp::with(['customer', 'lead', 'creator'])
            ->whereNotNull('follow_up_date');

        $user = auth()->user();
        if (!$user->can('manage-sales-leads')) {
            $query->where('created_by', $user->id);
        }

        if ($request->filled('start') && $request->filled('end')) {
            $query->whereDate('follow_up_date', '>=', substr($request->query('start'), 0, 10))
                ->whereDate('follow_up_date', '<=', substr($request->query('end'), 0, 10));
        }

        $events = $query->orderBy('follow_up_date')->get()->map(function (FollowUp $fu) {
            $time = $fu->activity_time ?: '09:00';
            $start = $fu->follow_up_date->format('Y-m-d').'T'.$time.':00';
            $customerName = $fu->customer?->name ?? ($fu->lead_id ? 'Lead #'.$fu->lead_id : __('Jadwal'));
            $creatorName = $fu->creator?->name ?? '';

            return [
                'id' => $fu->id,
                'title' => trim(__('Kunjungan').' - '.$customerName.($creatorName ? " ({$creatorName})" : '')),
                'start' => $start,
                'description' => $fu->description,
                'url' => $fu->lead_id
                    ? route('leads.show', $fu->lead_id)
                    : route('customers.show', $fu->customer_id),
            ];
        });

        return response()->json($events);
    }

    public function create(Request $request)
    {
        $lead = Lead::findOrFail($request->get('lead_id'));
        $this->authorize('view', $lead);

        $pics = User::role(['sales', 'inside-sales'])->orderBy('name')->get(['id', 'name']);

        return view('sales.schedules.create', compact('lead', 'pics'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'lead_id' => 'required|exists:leads,id',
            'assigned_to' => 'required|exists:users,id',
            'title' => 'required|string|max:255',
            'type' => 'required|in:' . implode(',', SalesSchedule::TYPES),
            'start_at' => 'required|date',
            'end_at' => 'nullable|date|after_or_equal:start_at',
            'location' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'reminder_at' => 'nullable|date|before:start_at',
        ]);

        $lead = Lead::findOrFail($validated['lead_id']);
        $this->authorize('view', $lead);

        $pic = User::findOrFail($validated['assigned_to']);
        abort_unless($pic->hasAnyRole(['sales', 'inside-sales']), 422, __('PIC harus Sales atau Inside Sales.'));

        $schedule = SalesSchedule::create($validated + ['created_by' => auth()->id()]);

        $this->log($lead, 'schedule_created', ['title' => $schedule->title]);

        if ((int) $schedule->assigned_to !== (int) auth()->id()) {
            $schedule->assignee?->notify(new SalesScheduleAssignedNotification($schedule));
        }

        return redirect()->route('sales.schedules.show', $schedule)
            ->with('success', __('Jadwal berhasil dibuat.'));
    }

    public function show(SalesSchedule $schedule)
    {
        $schedule->load(['lead.customer', 'assignee', 'creator']);
        $this->authorizeSchedule($schedule);

        return view('sales.schedules.show', compact('schedule'));
    }

    public function edit(SalesSchedule $schedule)
    {
        $this->authorizeManage($schedule);
        abort_if(in_array($schedule->status, ['completed', 'cancelled']), 422);

        $pics = User::role(['sales', 'inside-sales'])->orderBy('name')->get(['id', 'name']);

        return view('sales.schedules.edit', compact('schedule', 'pics'));
    }

    public function update(Request $request, SalesSchedule $schedule)
    {
        $this->authorizeManage($schedule);
        abort_if(in_array($schedule->status, ['completed', 'cancelled']), 422);

        $validated = $request->validate([
            'assigned_to' => 'required|exists:users,id',
            'title' => 'required|string|max:255',
            'type' => 'required|in:' . implode(',', SalesSchedule::TYPES),
            'start_at' => 'required|date',
            'end_at' => 'nullable|date|after_or_equal:start_at',
            'location' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'reminder_at' => 'nullable|date|before:start_at',
        ]);

        $rescheduled = $schedule->status === 'scheduled'
            && $validated['start_at'] !== $schedule->start_at->format('Y-m-d H:i:s');

        $schedule->update($validated + ($rescheduled ? ['status' => 'rescheduled'] : []));

        $this->log($schedule->lead, $rescheduled ? 'schedule_rescheduled' : 'schedule_updated', ['title' => $schedule->title]);

        if ((int) $schedule->assigned_to !== (int) auth()->id()) {
            $schedule->assignee?->notify(new SalesScheduleAssignedNotification($schedule));
        }

        return redirect()->route('sales.schedules.show', $schedule)
            ->with('success', __('Jadwal berhasil diperbarui.'));
    }

    public function complete(SalesSchedule $schedule)
    {
        $this->authorizeManage($schedule);
        abort_if(in_array($schedule->status, ['completed', 'cancelled']), 422);

        $schedule->update(['status' => 'completed']);
        $this->log($schedule->lead, 'schedule_completed', ['title' => $schedule->title]);

        return back()->with('success', __('Jadwal ditandai selesai.'));
    }

    public function cancel(SalesSchedule $schedule)
    {
        $this->authorizeManage($schedule);
        abort_if(in_array($schedule->status, ['completed', 'cancelled']), 422);

        $schedule->update(['status' => 'cancelled']);
        $this->log($schedule->lead, 'schedule_cancelled', ['title' => $schedule->title]);

        return back()->with('success', __('Jadwal dibatalkan.'));
    }

    /**
     * Dropdown customer untuk modal tambah jadwal (scope PT sama
     * seperti form follow-up).
     */
    private function customerOptions($user): array
    {
        $pts = PtAccess::userPts($user);

        return Customer::query()
            ->when($pts !== [], fn ($q) => $q->where(
                fn ($w) => $w->whereIn('pt_group', $pts)->orWhereNull('pt_group')
            ))
            ->orderBy('name')
            ->get(['id', 'name', 'pt_group'])
            ->mapWithKeys(fn ($c) => [$c->id => $c->name.($c->pt_group ? " ({$c->pt_group})" : '')])
            ->all();
    }

    /**
     * Dropdown lead untuk modal tambah jadwal. Sales-like hanya
     * melihat lead miliknya sendiri.
     */
    private function leadItems($user): array
    {
        $query = Lead::with('customer')->latest()->limit(200);
        if (PtAccess::isSalesLike($user) && !$user->can('manage-marketing')) {
            $query->where('assigned_to', $user->id);
        }

        return $query->get(['id', 'customer_id', 'status', 'incoming_date', 'assigned_to'])
            ->map(fn ($lead) => [
                'id' => (string) $lead->id,
                'customer_id' => (string) $lead->customer_id,
                'label' => "Lead #{$lead->id} — ".Lead::salesStatusLabel($lead->status)
                    .($lead->incoming_date ? ' — '.$lead->incoming_date->format('d M Y') : ''),
            ])->all();
    }
}

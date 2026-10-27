<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\User;
use App\Support\PtAccess;
use Illuminate\Http\Request;

/**
 * Menu Meeting — dipisah dari Follow Up.
 * Data tetap di tabel follow_ups (type = 'meeting'), tetapi seluruh alur
 * (daftar tracker, buat, laporan MOM + foto) dikelola di sini.
 */
class SalesMeetingController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $canSeeTeam = $user->can('manage-sales-leads');

        // Sales hanya meeting miliknya; manager boleh tab team.
        $tab = $canSeeTeam && $request->get('tab') === 'team' ? 'team' : 'my';

        $base = FollowUp::query()
            ->meetings()
            ->when($tab === 'my', function ($q) use ($user) {
                $q->where(function ($w) use ($user) {
                    $w->where('created_by', $user->id)
                        ->orWhereHas('lead', fn ($l) => $l->where('assigned_to', $user->id));
                });
            });

        // Filter rentang tanggal.
        $range = (string) $request->get('range', 'month');
        $from = match ($range) {
            'today' => today(),
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            default => null,
        };

        $query = (clone $base)
            ->with(['customer', 'lead.customer', 'creator', 'lead.assignee'])
            ->when($from, fn ($q) => $q->whereDate('follow_up_date', '>=', $from))
            ->when($request->get('status') === 'pending', fn ($q) => $q->whereNull('completed_at'))
            ->when($request->get('status') === 'completed', fn ($q) => $q->whereNotNull('completed_at'))
            ->when(in_array($request->get('mode'), FollowUp::MEETING_MODES, true),
                fn ($q) => $q->where('meeting_mode', $request->get('mode')))
            ->when(in_array($request->get('outcome'), FollowUp::MEETING_OUTCOMES, true),
                fn ($q) => $q->where('meeting_outcome', $request->get('outcome')))
            ->when(trim((string) $request->get('q')) !== '', function ($q) use ($request) {
                $term = trim((string) $request->get('q'));
                $q->whereHas('customer', fn ($c) => $c->whereLike('name', $term));
            })
            ->orderByDesc('follow_up_date')
            ->orderByDesc('activity_time')
            ->limit(300);

        $meetings = $query->get();
        $grouped = $meetings->groupBy(fn ($m) => $m->follow_up_date?->toDateString() ?? '-');

        $kpi = [
            'today' => (clone $base)->whereDate('follow_up_date', today())->count(),
            'week' => (clone $base)->whereDate('follow_up_date', '>=', now()->startOfWeek())->count(),
            'pending' => (clone $base)->whereNull('completed_at')
                ->whereDate('follow_up_date', '<=', today())->count(),
            'won' => (clone $base)->where('meeting_outcome', 'closed_won')
                ->whereMonth('follow_up_date', now()->month)
                ->whereYear('follow_up_date', now()->year)->count(),
        ];

        return view('sales.meetings.index', compact('meetings', 'grouped', 'kpi', 'tab', 'canSeeTeam', 'range'));
    }

    public function create(Request $request)
    {
        $user = $request->user();

        return view('sales.meetings.create', [
            'customers' => $this->customerOptions($user),
            'leads' => $this->leadOptions($user),
            'modes' => FollowUp::MEETING_MODES,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'lead_id' => 'nullable|exists:leads,id',
            'follow_up_date' => 'required|date',
            'activity_time' => 'nullable|date_format:H:i',
            'meeting_mode' => 'required|in:'.implode(',', FollowUp::MEETING_MODES),
            'location' => 'nullable|string|max:255',
            'description' => 'required|string|max:2000',
            'next_follow_up_date' => 'nullable|date|after_or_equal:follow_up_date',
        ]);

        $customer = Customer::findOrFail($validated['customer_id']);
        abort_unless(PtAccess::canWritePt($user, $customer->pt_group), 403);

        if (! empty($validated['lead_id'])) {
            $lead = Lead::findOrFail($validated['lead_id']);
            abort_if((int) $lead->customer_id !== (int) $customer->id, 422,
                __('Lead yang dipilih bukan milik customer ini.'));
            abort_unless($user->can('manage-sales-leads') || (int) $lead->assigned_to === (int) $user->id, 403);
        }

        $meeting = FollowUp::create([
            ...$validated,
            'type' => 'meeting',
            'created_by' => $user->id,
        ]);

        return redirect()->route('sales.meetings.show', $meeting)
            ->with('success', __('Meeting created. Fill the report after the meeting.'))
            ->with('success_card', true);
    }

    public function show(FollowUp $followUp)
    {
        abort_unless($followUp->type === 'meeting', 404);
        $this->authorizeMeeting($followUp);
        $followUp->load(['customer', 'lead.customer', 'creator', 'lead.assignee']);

        return view('sales.meetings.show', ['meeting' => $followUp]);
    }

    public function report(Request $request, FollowUp $followUp)
    {
        abort_if($followUp->type !== 'meeting', 422, __('Only meeting type can have a meeting report.'));
        $this->authorizeMeeting($followUp);
        abort_if($followUp->completed_at !== null, 422, __('This meeting has already been completed.'));

        $followUp->load(['customer', 'lead.customer', 'creator']);

        return view('sales.meetings.report', [
            'meeting' => $followUp,
            'returnTo' => $request->query('return'),
            'returnDate' => $request->query('date'),
        ]);
    }

    public function reportStore(Request $request, FollowUp $followUp)
    {
        abort_if($followUp->type !== 'meeting', 422, __('Only meeting type can have a meeting report.'));
        $this->authorizeMeeting($followUp);
        abort_if($followUp->completed_at !== null, 422, __('This meeting has already been completed.'));

        $validated = $request->validate([
            'meeting_photos' => 'required|array|min:1|max:3',
            'meeting_photos.*' => 'image|mimes:jpg,jpeg,png,webp|max:5120',
            'meeting_notes' => 'required|string|min:10',
            'meeting_outcome' => 'required|in:'.implode(',', FollowUp::MEETING_OUTCOMES),
            'meeting_next_action_date' => 'nullable|date|after_or_equal:today',
        ], [
            'meeting_photos.required' => __('Please upload at least 1 photo as meeting documentation.'),
            'meeting_photos.min' => __('Please upload at least 1 photo as meeting documentation.'),
            'meeting_photos.max' => __('Maximum 3 photos allowed.'),
            'meeting_photos.*.max' => __('Each photo must be under 5 MB.'),
            'meeting_notes.required' => __('Meeting notes are required.'),
            'meeting_notes.min' => __('Meeting notes must be at least 10 characters.'),
            'meeting_outcome.required' => __('Please select the meeting outcome.'),
        ]);

        app(\App\Services\SalesService::class)->completeMeetingReport($followUp, $validated);

        if ($request->input('return') === 'schedule') {
            return redirect()->route('sales.schedules.index', $request->filled('date') ? ['date' => substr($request->input('date'), 0, 10)] : [])
                ->with('success', __('Meeting report submitted and marked as completed.'));
        }

        return redirect()->route('sales.meetings.show', $followUp)
            ->with('success', __('Meeting report submitted and marked as completed.'))
            ->with('success_card', true);
    }

    /** Boleh lihat/isi: pemilik meeting atau management. */
    private function authorizeMeeting(FollowUp $meeting): void
    {
        $user = auth()->user();
        if ($user->hasRole('super-admin') || $user->can('manage-sales-leads')) {
            return;
        }

        $owner = (int) $meeting->created_by === (int) $user->id
            || ((int) ($meeting->lead?->assigned_to ?? 0) === (int) $user->id);

        abort_unless($owner, 403);
    }

    /** Customer yang boleh dipilih user ini (batas PT). */
    private function customerOptions(User $user): array
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

    /** Lead milik user (sales) atau semua (management). */
    private function leadOptions(User $user)
    {
        $query = Lead::with('customer')->latest()->limit(200);

        if (! $user->can('manage-sales-leads') && ! $user->can('manage-marketing')) {
            $query->where('assigned_to', $user->id);
        }

        return $query->get(['id', 'customer_id', 'status', 'assigned_to']);
    }
}

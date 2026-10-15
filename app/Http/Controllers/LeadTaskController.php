<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadTask;
use App\Models\User;
use Illuminate\Http\Request;

class LeadTaskController extends Controller
{
    /** Boleh request/kelola task: management, marketing, atau sales pemilik lead. */
    private function authorizeTaskRequest(Lead $lead): void
    {
        $user = request()->user();
        if ($user && ($user->can('manage-sales-leads') || $user->can('manage-marketing'))) {
            return;
        }
        if ($user && $user->can('manage-sales') && (int) $lead->assigned_to === (int) $user->id) {
            return;
        }
        abort(403);
    }

    /** Boleh melihat task: mengikuti policy view lead parent, atau assignee task. */
    private function authorizeTaskView(LeadTask $task): void
    {
        $user = request()->user();
        if ($user && (int) $task->assigned_to === (int) $user->id) {
            return;
        }
        $this->authorize('view', $task->lead);
    }

    /** Boleh mengerjakan/mengubah task: assignee, pembuat, atau management. */
    private function authorizeTaskManage(LeadTask $task): void
    {
        $user = request()->user();
        if ($user && ((int) $task->assigned_to === (int) $user->id
            || (int) $task->created_by === (int) $user->id
            || $user->can('manage-sales-leads'))) {
            return;
        }
        abort(403);
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $query = LeadTask::with(['lead.customer', 'assignee', 'creator'])->latest();

        // Inside sales biasa hanya melihat task miliknya; management melihat semua.
        if ($user && $user->can('manage-inside-sales') && !$user->can('manage-sales-leads')) {
            $query->where('assigned_to', $user->id);
        }
        // Sales biasa hanya melihat task dari lead miliknya (anakan sales).
        if ($user && $user->hasRole('sales') && !$user->can('manage-marketing') && !$user->can('manage-sales-leads')) {
            $query->whereHas('lead', fn ($l) => $l->where('assigned_to', $user->id));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $tasks = $query->paginate(15)->withQueryString();

        return view('lead-tasks.index', compact('tasks'));
    }

    public function create(Request $request)
    {
        $lead = null;
        if ($request->filled('lead_id')) {
            $lead = Lead::with('customer')->findOrFail($request->lead_id);
            $this->authorizeTaskRequest($lead);
        }
        $insideSales = User::role('inside-sales')->orderBy('name')->get(['id', 'name']);

        $leads = null;
        if (!$lead) {
            $leadsQuery = Lead::with('customer')->latest()->limit(100);
            $user = $request->user();
            if ($user && $user->hasRole('sales') && !$user->can('manage-marketing') && !$user->can('manage-sales-leads')) {
                $leadsQuery->where('assigned_to', $user->id);
            }
            $leads = $leadsQuery->get();
        }

        return view('lead-tasks.create', compact('lead', 'insideSales', 'leads'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'lead_id' => 'required|exists:leads,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'assigned_to' => 'nullable|exists:users,id',
            'due_date' => 'nullable|date',
            'priority' => 'nullable|in:'.implode(',', LeadTask::PRIORITIES),
        ]);

        $lead = Lead::findOrFail($validated['lead_id']);
        $this->authorizeTaskRequest($lead);

        if (!empty($validated['assigned_to'])) {
            $assignee = User::find($validated['assigned_to']);
            if (!$assignee?->hasRole('inside-sales')) {
                abort(422, __('Target task harus user dengan role Inside Sales.'));
            }
        }

        $validated['created_by'] = auth()->id();
        $task = LeadTask::create($validated);

        LeadActivity::create([
            'lead_id' => $lead->id,
            'user_id' => auth()->id(),
            'action' => 'task_created',
            'changes' => ['task' => $task->title],
        ]);

        // Beri tahu inside sales yang di-assign (kecuali pembuatnya sendiri).
        if ($task->assigned_to && (int) $task->assigned_to !== (int) auth()->id()) {
            $task->assignee?->notify(new \App\Notifications\LeadTaskNotification($task, 'created'));
        }

        return redirect()
            ->route('lead-tasks.show', $task)
            ->with('success', __('Inside sales task berhasil dibuat.'));
    }

    public function show(LeadTask $leadTask)
    {
        $leadTask->load(['lead.customer', 'assignee', 'creator', 'comments.user']);
        $this->authorizeTaskView($leadTask);

        return view('lead-tasks.show', ['task' => $leadTask]);
    }

    public function edit(LeadTask $leadTask)
    {
        $leadTask->load(['lead.customer']);
        $this->authorizeTaskManage($leadTask);
        $insideSales = User::role('inside-sales')->orderBy('name')->get(['id', 'name']);

        return view('lead-tasks.edit', ['task' => $leadTask, 'insideSales' => $insideSales]);
    }

    public function update(Request $request, LeadTask $leadTask)
    {
        $this->authorizeTaskManage($leadTask);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'assigned_to' => 'nullable|exists:users,id',
            'due_date' => 'nullable|date',
            'priority' => 'nullable|in:'.implode(',', LeadTask::PRIORITIES),
            'status' => 'required|in:'.implode(',', LeadTask::STATUSES),
        ]);

        if (!empty($validated['assigned_to'])) {
            $assignee = User::find($validated['assigned_to']);
            if (!$assignee?->hasRole('inside-sales')) {
                abort(422, __('Target task harus user dengan role Inside Sales.'));
            }
        }

        $oldStatus = $leadTask->status;
        $leadTask->update($validated);

        if ($oldStatus !== $leadTask->status) {
            LeadActivity::create([
                'lead_id' => $leadTask->lead_id,
                'user_id' => auth()->id(),
                'action' => 'task_status_changed',
                'changes' => ['task_status' => ['old' => $oldStatus, 'new' => $leadTask->status]],
            ]);

            // Beri tahu creator + assignee (kecuali aktor sendiri).
            foreach ([$leadTask->creator, $leadTask->assignee] as $recipient) {
                if ($recipient && (int) $recipient->id !== (int) auth()->id()) {
                    $recipient->notify(new \App\Notifications\LeadTaskNotification($leadTask, 'status'));
                }
            }
        }

        return redirect()
            ->route('lead-tasks.show', $leadTask)
            ->with('success', __('Task berhasil diperbarui.'));
    }

    public function destroy(LeadTask $leadTask)
    {
        if (!request()->user()?->can('manage-sales-leads')) {
            abort(403);
        }
        $leadTask->delete();

        return redirect()
            ->route('lead-tasks.index')
            ->with('success', __('Task berhasil dihapus.'));
    }

    public function storeComment(Request $request, LeadTask $leadTask)
    {
        $this->authorizeTaskView($leadTask);

        $validated = $request->validate([
            'body' => 'required|string|max:2000',
        ]);

        $leadTask->comments()->create([
            'user_id' => auth()->id(),
            'body' => $validated['body'],
        ]);

        return redirect()
            ->route('lead-tasks.show', $leadTask)
            ->with('success', __('Komentar terkirim.'));
    }
}

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
        $query = LeadTask::with(['lead.customer', 'assignee', 'creator'])->withCount('attachments')->latest();

        // Management melihat semua. Selain itu scope digabung dengan OR:
        // multi-role (sales + inside-sales) harus melihat task yang di-assign
        // kepadanya ATAU lead miliknya — bukan irisan keduanya (AND), yang
        // membuat task hilang saat assignee dan pemilik lead berbeda orang.
        if ($user && ! $user->can('manage-sales-leads')) {
            $query->where(function ($q) use ($user) {
                if ($user->can('manage-inside-sales')) {
                    $q->orWhere('assigned_to', $user->id);
                }
                if (($user->can('view-sales') || $user->can('manage-sales')) && ! $user->can('manage-marketing')) {
                    $q->orWhereHas('lead', fn ($l) => $l->where('assigned_to', $user->id));
                }
            });
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
                abort(422, __('The task target must be a user with the Inside Sales role.'));
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
            ->with('success', __('Inside sales task created successfully.'));
    }

    public function show(LeadTask $leadTask)
    {
        $leadTask->load(['lead.customer', 'assignee', 'creator', 'comments.user', 'attachments.uploader']);
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
                abort(422, __('The task target must be a user with the Inside Sales role.'));
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
            ->with('success', __('Task updated successfully.'));
    }

    public function destroy(LeadTask $leadTask)
    {
        if (!request()->user()?->can('manage-sales-leads')) {
            abort(403);
        }
        $leadTask->delete();

        return redirect()
            ->route('lead-tasks.index')
            ->with('success', __('Task deleted successfully.'));
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
            ->with('success', __('Comment sent.'));
    }

    /**
     * Upload file quotation baru dari Inside Sales. File lama TIDAK ditimpa —
     * setiap upload jadi versi baru supaya revisi tetap bisa diunduh.
     */
    public function storeAttachment(Request $request, LeadTask $leadTask)
    {
        $this->authorizeTaskManage($leadTask);

        $request->validate([
            'attachment' => ['required', 'file', 'max:10240', \App\Rules\SecureFile::documents()],
        ], [
            'attachment.required' => __('Please choose a file to upload.'),
            'attachment.max' => __('The file must be under 10 MB.'),
        ]);

        $file = $request->file('attachment');
        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $file->getClientOriginalName());
        $path = $file->storeAs(
            'lead-tasks/'.$leadTask->id,
            \Illuminate\Support\Str::uuid().'_'.$safeName,
            'private'
        );

        $leadTask->attachments()->create([
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize() ?: 0,
            'uploaded_by' => auth()->id(),
        ]);

        // Beri tahu peminta (pembuat task) kalau yang upload bukan dia sendiri.
        $requester = $leadTask->creator;
        if ($requester && (int) $requester->id !== (int) auth()->id()) {
            $requester->notify(new \App\Notifications\LeadTaskNotification($leadTask, 'quotation'));
        }

        return redirect()
            ->route('lead-tasks.show', $leadTask)
            ->with('success', __('Quotation file uploaded.'));
    }

    /**
     * Terima file quotation Inside Sales → otomatis buat Quotation (proposal)
     * draft yang tertaut ke lead/customer task, lalu file dipasang sebagai
     * lampiran quotation tersebut. Akses menu Quotation mengikuti lead
     * (pemilik lead + management), sama seperti quotation lain.
     */
    public function acceptAttachment(Request $request, LeadTask $leadTask, \App\Models\LeadTaskAttachment $attachment)
    {
        abort_unless((int) $attachment->lead_task_id === (int) $leadTask->id, 404);

        $user = $request->user();
        $lead = $leadTask->lead;

        // Hanya peminta (pembuat task), pemilik lead, atau management —
        // bukan semua role sales.
        $canAccept = $user && (
            (int) $leadTask->created_by === (int) $user->id
            || (int) ($lead?->assigned_to ?? 0) === (int) $user->id
            || $user->can('manage-sales-leads')
        );

        abort_unless($canAccept, 403);

        // Sales per PT hanya boleh menerima quotation pada PT-nya sendiri.
        abort_unless(
            \App\Support\PtAccess::canWritePt($user, $lead?->customer?->pt_group),
            403
        );

        abort_if($attachment->accepted_at !== null, 422, __('This quotation file has already been accepted.'));

        $proposal = \Illuminate\Support\Facades\DB::transaction(function () use ($attachment, $leadTask, $lead) {
            $proposal = \App\Models\Proposal::create([
                'lead_id' => $leadTask->lead_id,
                'proposal_number' => $this->generateProposalNumber(),
                'created_by' => auth()->id(),
                'proposal_date' => now()->toDateString(),
                'customer' => $lead?->customer?->name ?? '-',
                'status' => 'draft',
                'internal_notes' => __('Created automatically from Inside Sales quotation file :file (task #:task).', [
                    'file' => $attachment->original_name,
                    'task' => $leadTask->id,
                ]),
            ]);

            // File ikut dipasang sebagai lampiran quotation (tanpa mengisi
            // form item — quotation berbasis file).
            \App\Models\ProposalAttachment::create([
                'proposal_id' => $proposal->id,
                'file_path' => $attachment->file_path,
                'original_name' => $attachment->original_name,
                'mime' => $attachment->mime,
                'size' => $attachment->size,
                'uploaded_by' => $attachment->uploaded_by,
            ]);

            $attachment->update([
                'proposal_id' => $proposal->id,
                'accepted_by' => auth()->id(),
                'accepted_at' => now(),
            ]);

            return $proposal;
        });

        // Kabari Inside Sales bahwa file-nya sudah diterima.
        $assignee = $leadTask->assignee;
        if ($assignee && (int) $assignee->id !== (int) auth()->id()) {
            $assignee->notify(new \App\Notifications\LeadTaskNotification($leadTask, 'accepted'));
        }

        return redirect()
            ->route('sales.proposals.show', $proposal)
            ->with('success', __('Quotation created from the accepted file.'));
    }

    /** Nomor quotation berurutan per bulan, format sama seperti menu Quotation. */
    private function generateProposalNumber(): string
    {
        $prefix = 'Q/'.now()->format('Y/m').'/';

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $sequence = \App\Models\Proposal::where('proposal_number', 'like', $prefix.'%')->count() + 1 + $attempt;
            $number = $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);

            if (! \App\Models\Proposal::where('proposal_number', $number)->exists()) {
                return $number;
            }
        }

        return $prefix.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    public function downloadAttachment(LeadTask $leadTask, \App\Models\LeadTaskAttachment $attachment)
    {
        $this->authorizeTaskView($leadTask);

        abort_unless((int) $attachment->lead_task_id === (int) $leadTask->id, 404);
        abort_unless(\Illuminate\Support\Facades\Storage::disk('private')->exists($attachment->file_path), 404, __('File not found.'));

        return \Illuminate\Support\Facades\Storage::disk('private')
            ->download($attachment->file_path, $attachment->original_name);
    }

    public function destroyAttachment(LeadTask $leadTask, \App\Models\LeadTaskAttachment $attachment)
    {
        $this->authorizeTaskManage($leadTask);

        abort_unless((int) $attachment->lead_task_id === (int) $leadTask->id, 404);

        \Illuminate\Support\Facades\Storage::disk('private')->delete($attachment->file_path);
        $attachment->delete();

        return redirect()
            ->route('lead-tasks.show', $leadTask)
            ->with('success', __('Quotation file deleted.'));
    }
}

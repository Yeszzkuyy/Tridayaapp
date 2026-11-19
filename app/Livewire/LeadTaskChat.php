<?php

namespace App\Livewire;

use App\Models\LeadTask;
use App\Notifications\LeadTaskNotification;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

/** Chat Sales <-> Inside Sales di dalam task (polling ringan tiap 5 detik). */
class LeadTaskChat extends Component
{
    use AuthorizesRequests;

    public LeadTask $task;
    public string $body = '';

    public function mount(LeadTask $task): void
    {
        $this->authorizeTaskView($task);
        $this->task = $task;
    }

    public function send(): void
    {
        $this->authorizeTaskView($this->task);

        $validated = $this->validate(['body' => 'required|string|max:2000']);

        $this->task->comments()->create([
            'user_id' => auth()->id(),
            'body' => $validated['body'],
        ]);

        $this->reset('body');
        $this->dispatch('chat-updated');

        // Beri tahu lawan bicara (creator/assignee, kecuali pengirim sendiri).
        // Satu task cukup punya satu notifikasi balasan yang belum dibaca,
        // supaya chat beruntun tidak menumpuk seperti WhatsApp.
        $senderName = auth()->user()?->name;

        foreach ([$this->task->creator, $this->task->assignee] as $recipient) {
            if (! $recipient || (int) $recipient->id === (int) auth()->id()) {
                continue;
            }

            if ($this->hasUnreadReplyNotification($recipient)) {
                continue;
            }

            $recipient->notify(new LeadTaskNotification($this->task, 'comment', $senderName, $validated['body']));
        }
    }

    /** Penerima sudah punya balasan task ini yang belum dibaca? */
    private function hasUnreadReplyNotification(\App\Models\User $recipient): bool
    {
        return $recipient->unreadNotifications()
            ->where('type', LeadTaskNotification::class)
            ->get()
            ->contains(function ($notification): bool {
                $data = $notification->data;

                return LeadTaskNotification::isReplyData($data)
                    && (int) ($data['lead_task_id'] ?? 0) === (int) $this->task->id;
            });
    }

    public function render()
    {
        $comments = $this->task->comments()->reorder()->orderBy('id', 'asc')->with('user')->get();

        return view('livewire.lead-task-chat', compact('comments'));
    }

    /** Boleh chat: mengikuti policy view lead parent, atau assignee task. */
    private function authorizeTaskView(LeadTask $task): void
    {
        $user = auth()->user();
        if ($user && (int) $task->assigned_to === (int) $user->id) {
            return;
        }
        $this->authorize('view', $task->lead);
    }
}

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

        // Beri tahu lawan bicara (creator/assignee, kecuali pengirim sendiri).
        foreach ([$this->task->creator, $this->task->assignee] as $recipient) {
            if ($recipient && (int) $recipient->id !== (int) auth()->id()) {
                $recipient->notify(new LeadTaskNotification($this->task, 'comment'));
            }
        }
    }

    public function render()
    {
        $comments = $this->task->comments()->with('user')->oldest()->get();

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

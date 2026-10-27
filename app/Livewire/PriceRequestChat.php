<?php

namespace App\Livewire;

use App\Models\PriceRequest;
use App\Models\PriceRequestComment;
use App\Models\User;
use App\Notifications\PriceRequestCommentNotification;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Livewire\Component;

/** Live discussion between the Price Request owner and its assigned engineer. */
class PriceRequestChat extends Component
{
    use AuthorizesRequests;

    public PriceRequest $priceRequest;

    public string $body = '';

    public function mount(PriceRequest $priceRequest): void
    {
        $this->authorize('view', $priceRequest);
        $this->priceRequest = $priceRequest;
    }

    public function send(): void
    {
        $this->authorize('comment', $this->priceRequest);
        $validated = $this->validate([
            'body' => 'required|string|max:2000',
        ]);

        $comment = $this->priceRequest->comments()->create([
            'user_id' => auth()->id(),
            'body' => $validated['body'],
            'type' => PriceRequestComment::TYPE_MESSAGE,
        ]);

        $this->reset('body');
        $this->dispatch('price-request-chat-updated');
        $this->notifyParticipants($comment);
    }

    public function render()
    {
        $this->authorize('view', $this->priceRequest);

        $comments = $this->priceRequest->comments()
            ->with('user.roles')
            ->get();

        return view('livewire.price-request-chat', compact('comments'));
    }

    private function notifyParticipants(PriceRequestComment $comment): void
    {
        $this->priceRequest->loadMissing(['requester', 'assignee', 'customer']);
        $senderId = (int) auth()->id();
        $recipients = new Collection();

        if ($senderId === (int) $this->priceRequest->requested_by) {
            if ($this->priceRequest->assignee) {
                $recipients->push($this->priceRequest->assignee);
            } else {
                $recipients = User::role('lead-technician')->get();
            }
        } elseif ($senderId === (int) $this->priceRequest->assigned_to) {
            if ($this->priceRequest->requester) {
                $recipients->push($this->priceRequest->requester);
            }
        } else {
            $recipients->push($this->priceRequest->requester, $this->priceRequest->assignee);
        }

        $recipients
            ->filter()
            ->unique('id')
            ->reject(fn (User $user) => (int) $user->id === $senderId)
            ->each(fn (User $user) => $user->notify(new PriceRequestCommentNotification($this->priceRequest, $comment)));
    }
}

<?php

namespace App\Policies;

use App\Models\PriceRequest;
use App\Models\User;

class PriceRequestPolicy
{
    public function view(User $user, PriceRequest $priceRequest): bool
    {
        if ((int) $priceRequest->requested_by === (int) $user->id
            || (int) $priceRequest->assigned_to === (int) $user->id
            || $user->can('manage-sales-leads')
            || $user->hasRole('lead-technician')) {
            return true;
        }

        // Emergency inbox fallback is visible to the lead team only.
        return $priceRequest->status === 'waiting_lead'
            && $priceRequest->assigned_to === null
            && $user->hasRole('super-admin');
    }

    public function comment(User $user, PriceRequest $priceRequest): bool
    {
        $isParticipant = (int) $priceRequest->requested_by === (int) $user->id
            || (int) $priceRequest->assigned_to === (int) $user->id
            || $user->can('manage-sales-leads')
            || $user->hasRole('super-admin')
            || ($priceRequest->status === 'waiting_lead' && $user->hasRole('lead-technician'));

        return $isParticipant && !$priceRequest->isClosed();
    }

    public function review(User $user, PriceRequest $priceRequest): bool
    {
        return $user->hasRole('lead-technician')
            && $priceRequest->status === 'waiting_lead'
            && $priceRequest->assigned_to === null;
    }

    public function assign(User $user, PriceRequest $priceRequest): bool
    {
        return $user->hasRole('lead-technician')
            && in_array($priceRequest->status, ['accepted', 'assigned'], true);
    }

    public function take(User $user, PriceRequest $priceRequest): bool
    {
        return $user->hasRole('lead-technician')
            && $priceRequest->status === 'waiting_lead'
            && $priceRequest->assigned_to === null;
    }

    public function reject(User $user, PriceRequest $priceRequest): bool
    {
        return $user->hasRole('lead-technician')
            && $priceRequest->status === 'waiting_lead'
            && $priceRequest->assigned_to === null;
    }

    public function reply(User $user, PriceRequest $priceRequest): bool
    {
        return $user->can('manage-technician')
            && (int) $priceRequest->assigned_to === (int) $user->id
            && in_array($priceRequest->status, ['in_progress', 'revision', 'accepted', 'assigned'], true);
    }

    public function decide(User $user, PriceRequest $priceRequest): bool
    {
        return $user->can('manage-sales-leads')
            || (int) $priceRequest->requested_by === (int) $user->id;
    }

    public function cancel(User $user, PriceRequest $priceRequest): bool
    {
        return $this->decide($user, $priceRequest) && !$priceRequest->isClosed();
    }
}

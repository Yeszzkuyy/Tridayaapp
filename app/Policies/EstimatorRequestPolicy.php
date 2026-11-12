<?php

namespace App\Policies;

use App\Models\EstimatorRequest;
use App\Models\User;

class EstimatorRequestPolicy
{
    public function view(User $user, EstimatorRequest $request): bool
    {
        return (int) $request->requested_by === (int) $user->id
            || (int) $request->handled_by === (int) $user->id
            || $user->can('manage-price-requests')
            || $user->can('manage-sales-leads')
            || $user->can('manage-admin')
            || $user->hasRole('ceo');
    }

    public function create(User $user): bool
    {
        return $user->can('manage-sales') || $user->can('manage-sales-leads');
    }

    public function answer(User $user, EstimatorRequest $request): bool
    {
        return $user->can('manage-price-requests')
            && in_array($request->status, ['submitted', 'in_progress', 'answered', 'revision'], true);
    }

    public function decide(User $user, EstimatorRequest $request): bool
    {
        return (int) $request->requested_by === (int) $user->id
            || $user->can('manage-sales-leads');
    }

    public function cancel(User $user, EstimatorRequest $request): bool
    {
        return $this->decide($user, $request)
            && ! in_array($request->status, ['completed', 'rejected', 'cancelled'], true);
    }

    public function export(User $user): bool
    {
        return $user->can('manage-price-requests') || $user->can('manage-sales-leads');
    }
}

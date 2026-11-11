<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Batas data Customer untuk Sales.
 *
 * Sales hanya melihat customer yang:
 * - ia buat sendiri,
 * - punya lead yang di-assign kepadanya,
 * - punya quotation yang ia buat,
 * - punya project dengan sales_user_id dirinya, atau
 * - punya task Inside Sales yang di-assign kepadanya.
 *
 * Management, Admin, Marketing, Teknisi, CEO, dan Super Admin melihat semua.
 * User yang hanya memegang permission tanpa role Sales (mis. akun khusus)
 * tetap melihat semua agar tidak memutus akses admin-like.
 */
final class CustomerAccess
{
    public static function canViewAll(User $user): bool
    {
        return $user->hasRole('super-admin')
            || $user->can('manage-sales-leads')
            || $user->can('manage-admin')
            || $user->can('view-admin')
            || $user->can('manage-marketing')
            || $user->can('view-marketing')
            || $user->can('manage-technician')
            || $user->can('view-technician')
            || $user->hasRole('ceo');
    }

    public static function isRestricted(User $user): bool
    {
        if (self::canViewAll($user)) {
            return false;
        }

        return $user->hasRole('sales')
            || PtAccess::userPts($user) !== []
            || $user->can('manage-inside-sales');
    }

    /** @return Builder<Customer> */
    public static function queryFor(User $user): Builder
    {
        $query = Customer::query();

        if (! self::isRestricted($user)) {
            return $query;
        }

        $userId = (int) $user->id;
        $pts = PtAccess::userPts($user);
        $allowUnowned = $user->hasRole('sales') || $pts !== [];

        return $query->where(function (Builder $q) use ($userId, $allowUnowned, $pts) {
            $q->where('customers.created_by', $userId)
                ->orWhereHas('leads', fn ($lead) => $lead->where('assigned_to', $userId))
                ->orWhereHas('leads.proposals', fn ($proposal) => $proposal->where('created_by', $userId))
                ->orWhereHas('projects', fn ($project) => $project->where('sales_user_id', $userId))
                ->orWhereHas('leads.tasks', fn ($task) => $task->where('assigned_to', $userId))
                ->orWhereHas('followUps', fn ($followUp) => $followUp->where('created_by', $userId))
                ->orWhereHas('pocs', fn ($poc) => $poc->where('created_by', $userId));

            // Data lama tanpa pemilik tetap bisa dilihat Sales sampai diklaim
            // (lead/project/follow-up/POC pertama menjadi penanda pemiliknya).
            if ($allowUnowned) {
                $q->orWhere(function (Builder $orphan) use ($pts) {
                    $orphan->whereNull('customers.created_by')
                        ->whereDoesntHave('leads')
                        ->whereDoesntHave('projects')
                        ->whereDoesntHave('followUps')
                        ->whereDoesntHave('pocs');

                    if ($pts !== []) {
                        $orphan->where(fn ($w) => $w->whereIn('customers.pt_group', $pts)
                            ->orWhereNull('customers.pt_group'));
                    }
                });
            }
        });
    }

    public static function canView(User $user, Customer $customer): bool
    {
        if (! self::isRestricted($user)) {
            return true;
        }

        return self::queryFor($user)->whereKey($customer->getKey())->exists();
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\Project;
use App\Models\User;
use App\Support\PtAccess;
use Illuminate\Http\Request;

class TrashController extends Controller
{
    /**
     * My Trash = data milik user (berdasar owner bisnis), bukan berdasar
     * siapa yang menghapus. deleted_by murni audit trail.
     * Super-admin: seluruh trash + filter per owner.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $isSuperAdmin = $user->hasRole('super-admin');

        [$customers, $projects] = $isSuperAdmin
            ? $this->allTrash($request)
            : $this->ownTrash($user->id);

        [$customerOwners, $projectOwners, $ownerNames] = $this->ownerMaps($customers, $projects);

        $users = $isSuperAdmin ? $this->ownerFilterUsers() : null;

        return view('trash.index', compact(
            'customers', 'projects', 'users', 'isSuperAdmin',
            'customerOwners', 'projectOwners', 'ownerNames'
        ));
    }

    private function ownTrash(int $userId): array
    {
        return [
            Customer::onlyTrashed()->with('deleter')
                ->whereIn('id', $this->ownedCustomerIds($userId))
                ->latest('deleted_at')->paginate(15, ['*'], 'customers_page')->withQueryString(),
            Project::onlyTrashed()->with(['deleter', 'customer' => fn ($q) => $q->withTrashed()])
                ->whereIn('id', $this->ownedProjectIds($userId))
                ->latest('deleted_at')->paginate(15, ['*'], 'projects_page')->withQueryString(),
        ];
    }

    private function allTrash(Request $request): array
    {
        $customerQuery = Customer::onlyTrashed()->with('deleter')->latest('deleted_at');
        $projectQuery = Project::onlyTrashed()->with(['deleter', 'customer' => fn ($q) => $q->withTrashed()])->latest('deleted_at');

        if ($request->filled('user')) {
            $ownerId = $request->integer('user');
            $customerQuery->whereIn('id', $this->ownedCustomerIds($ownerId));
            $projectQuery->whereIn('id', $this->ownedProjectIds($ownerId));
        }

        return [
            $customerQuery->paginate(15, ['*'], 'customers_page')->withQueryString(),
            $projectQuery->paginate(15, ['*'], 'projects_page')->withQueryString(),
        ];
    }

    public function restoreCustomer(int $id)
    {
        $customer = $this->findTrash(Customer::class, $id, 'restore');
        $customer->restore();

        return redirect()->route('trash.index')
            ->with('success', __('Customer ":name" berhasil direstore', ['name' => $customer->name]));
    }

    public function restoreProject(int $id)
    {
        $project = $this->findTrash(Project::class, $id, 'restore');
        $project->restore();

        return redirect()->route('trash.index')
            ->with('success', __('Project ":name" berhasil direstore', ['name' => $project->project_name]));
    }

    public function destroyCustomer(int $id)
    {
        $customer = $this->findTrash(Customer::class, $id, 'forceDelete');
        $customer->forceDelete();

        return redirect()->route('trash.index')
            ->with('success', __('Customer ":name" dihapus permanen', ['name' => $customer->name]));
    }

    public function destroyProject(int $id)
    {
        $project = $this->findTrash(Project::class, $id, 'forceDelete');
        $project->forceDelete();

        return redirect()->route('trash.index')
            ->with('success', __('Project ":name" dihapus permanen', ['name' => $project->name ?? $project->project_name]));
    }

    /**
     * Empty My Trash: hanya milik owner. Super-admin: global.
     */
    public function clear()
    {
        $user = auth()->user();

        if ($user->hasRole('super-admin')) {
            $customerIds = Customer::onlyTrashed()->pluck('id')->all();
            $projectIds = Project::onlyTrashed()->pluck('id')->all();
        } else {
            $customerIds = $this->ownedCustomerIds($user->id);
            $projectIds = $this->ownedProjectIds($user->id);
            $customerIds = $this->ptWritableCustomerIds($user, $customerIds);
            $projectIds = $this->ptWritableProjectIds($user, $projectIds);
        }

        // Customer dulu (cascade DB ikut membersihkan anaknya),
        // lalu project yang masih tersisa.
        if ($customerIds) {
            Customer::onlyTrashed()->whereIn('id', $customerIds)->forceDelete();
        }
        $remainingProjects = $projectIds
            ? Project::onlyTrashed()->whereIn('id', $projectIds)->pluck('id')->all()
            : [];
        if ($remainingProjects) {
            Project::onlyTrashed()->whereIn('id', $remainingProjects)->forceDelete();
        }

        return redirect()->route('trash.index')
            ->with('success', __('Trash berhasil dibersihkan'));
    }

    /**
     * Cari data di trash: non-owner = 404 (jangan bocorkan existence),
     * lalu tetap lewat Policy backend.
     */
    private function findTrash(string $model, int $id, string $ability)
    {
        $item = $model::onlyTrashed()->whereKey($id)->firstOrFail();

        if (!auth()->user()->hasRole('super-admin')
            && $item->trashOwnerId() !== (int) auth()->id()) {
            abort(404);
        }

        $this->authorize($ability, $item);

        return $item;
    }

    /**
     * customer_id => ownerId|null (tepat 1 sales = owner).
     */
    private function customerOwnerMap(array $customerIds): array
    {
        if (!$customerIds) {
            return [];
        }

        $grouped = Lead::withTrashed()->whereIn('customer_id', $customerIds)
            ->whereNotNull('assigned_to')
            ->select('customer_id', 'assigned_to')->distinct()->get()
            ->groupBy('customer_id');

        $map = [];
        foreach ($customerIds as $id) {
            $owners = $grouped[$id] ?? collect();
            $map[$id] = $owners->count() === 1 ? (int) $owners->first()->assigned_to : null;
        }

        return $map;
    }

    private function ownedCustomerIds(int $userId): array
    {
        $ids = Customer::onlyTrashed()->pluck('id')->all();
        if (!$ids) {
            return [];
        }

        $map = $this->customerOwnerMap($ids);

        return array_values(array_filter(
            $ids,
            fn ($id) => ($map[$id] ?? null) === $userId
        ));
    }

    private function ownedProjectIds(int $userId): array
    {
        $customerByProject = Project::onlyTrashed()->pluck('customer_id', 'id')->all();
        if (!$customerByProject) {
            return [];
        }

        $ownerMap = $this->customerOwnerMap(array_values(array_unique($customerByProject)));

        return array_keys(array_filter(
            $customerByProject,
            fn ($customerId) => ($ownerMap[$customerId] ?? null) === $userId
        ));
    }

    /**
     * Saring id customer: hanya yang PT-nya boleh ditulis user.
     */
    private function ptWritableCustomerIds($user, array $ids): array
    {
        if (!$ids) {
            return [];
        }
        $pts = Customer::onlyTrashed()->whereIn('id', $ids)->pluck('pt_group', 'id')->all();
        return array_values(array_filter($ids, fn ($id) => PtAccess::canWritePt($user, $pts[$id] ?? null)));
    }

    /**
     * Saring id project: ikut PT customer-nya.
     */
    private function ptWritableProjectIds($user, array $ids): array
    {
        if (!$ids) {
            return [];
        }
        $cbp = Project::onlyTrashed()->whereIn('id', $ids)->pluck('customer_id', 'id')->all();
        $pts = Customer::withTrashed()->whereIn('id', array_values(array_unique($cbp)))->pluck('pt_group', 'id')->all();
        return array_values(array_filter($ids, fn ($id) => PtAccess::canWritePt($user, $pts[$cbp[$id]] ?? null)));
    }

    /**
     * Peta owner untuk baris yang tampil + nama owner (2 query, bukan per baris).
     */
    private function ownerMaps($customers, $projects): array
    {
        $customerIds = $customers->pluck('id')->all();
        $customerOwners = $this->customerOwnerMap($customerIds);

        $customerByProject = $projects->pluck('customer_id', 'id')->all();
        $projectCustomerOwners = $this->customerOwnerMap(array_values(array_unique($customerByProject)));
        $projectOwners = [];
        foreach ($customerByProject as $projectId => $customerId) {
            $projectOwners[$projectId] = $projectCustomerOwners[$customerId] ?? null;
        }

        $ownerIds = collect($customerOwners)->merge($projectOwners)->filter()->unique()->values()->all();
        $ownerNames = $ownerIds ? User::whereIn('id', $ownerIds)->pluck('name', 'id')->all() : [];

        return [$customerOwners, $projectOwners, $ownerNames];
    }

    /**
     * Daftar user untuk filter super-admin: yang memiliki trash (berdasar owner).
     */
    private function ownerFilterUsers()
    {
        $customerOwners = $this->customerOwnerMap(Customer::onlyTrashed()->pluck('id')->all());
        $customerByProject = Project::onlyTrashed()->pluck('customer_id', 'id')->all();
        $projectOwners = $this->customerOwnerMap(array_values(array_unique($customerByProject)));

        $ownerIds = collect($customerOwners)->merge($projectOwners)->filter()->unique()->values()->all();

        return $ownerIds
            ? User::whereIn('id', $ownerIds)->orderBy('name')->get(['id', 'name'])
            : collect();
    }
}

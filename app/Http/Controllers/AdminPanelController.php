<?php

namespace App\Http\Controllers;

use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class AdminPanelController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:manage-monitoring');
    }

    public function index()
    {
        $users = User::with('roles')->paginate(15);
        
        return view('admin-panel.index', compact('users'));
    }

    // ========== USER MANAGEMENT ==========
    
    public function createUser()
    {
        $roles = Role::where('name', '!=', 'super-admin')->orderBy('name')->get();
        return view('admin-panel.users.create', compact('roles'));
    }

    public function storeUser(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'roles' => 'array',
            'roles.*' => 'exists:roles,name',
        ]);

        $this->guardSuperAdminGrant($request);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        if ($request->filled('roles')) {
            $user->assignRole($request->roles);
            // Selaraskan kolom role legacy (NOT NULL) dengan role pertama.
            $user->forceFill(['role' => $request->roles[0]])->save();
        }

        return redirect()->route('admin-panel.index')
            ->with('success', __('User berhasil dibuat.'));
    }

    public function editUser(User $user)
    {
        $roles = Role::where('name', '!=', 'super-admin')->orderBy('name')->get();
        return view('admin-panel.users.edit', compact('user', 'roles'));
    }

    public function updateUser(Request $request, User $user)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'password' => ['nullable', 'confirmed', Rules\Password::defaults()],
            'roles' => 'array',
            'roles.*' => 'exists:roles,name',
        ]);

        $this->guardSuperAdminGrant($request);
        $this->guardLastSuperAdminDemote($request, $user);

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
        ]);

        if ($request->filled('password')) {
            // Sengaja TIDAK menyentuh password_changed_at: reset oleh admin
            // bukan penggantian oleh pemilik akun, indikator tetap merah.
            $user->update(['password' => Hash::make($request->password)]);
        }

        if ($request->has('roles')) {
            $user->syncRoles($request->roles);
            // Selaraskan kolom role legacy (NOT NULL) dengan role pertama.
            $user->forceFill(['role' => $request->roles[0] ?? 'guest'])->save();
        } else {
            $user->syncRoles([]);
            $user->forceFill(['role' => 'guest'])->save();
        }

        return redirect()->route('admin-panel.index')
            ->with('success', __('User berhasil diupdate.'));
    }

    public function destroyUser(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', __('Tidak bisa menghapus akun sendiri.'));
        }

        if ($reason = $user->deletionBlockReason()) {
            return back()->with('error', $reason);
        }

        $user->delete();
        return redirect()->route('admin-panel.index')
            ->with('success', __('User berhasil dihapus.'));
    }

    /**
     * Hanya super-admin yang boleh memberikan role super-admin.
     * Form memang menyembunyikan opsi ini, tapi validasi tidak boleh
     * mengandalkan UI (request bisa dibuat manual).
     */
    private function guardSuperAdminGrant(Request $request): void
    {
        if (in_array('super-admin', $request->input('roles', []), true)
            && ! $request->user()->hasRole('super-admin')) {
            abort(403, __('Hanya super-admin yang boleh memberikan role super-admin.'));
        }
    }

    /**
     * Cegah lockout: super-admin terakhir tidak boleh di-demote,
     * dan niemand boleh mencabut super-admin dari dirinya sendiri.
     */
    private function guardLastSuperAdminDemote(Request $request, User $user): void
    {
        if (! $user->hasRole('super-admin')) {
            return;
        }

        $removing = ! $request->has('roles')
            || ! in_array('super-admin', $request->input('roles', []), true);

        if (! $removing) {
            return;
        }

        if ((int) $user->id === (int) $request->user()->id) {
            abort(403, __('Tidak bisa mencabut role super-admin dari akun sendiri.'));
        }

        if (User::role('super-admin')->count() <= 1) {
            abort(403, __('Tidak bisa demote super-admin terakhir (sistem akan terkunci).'));
        }
    }

    // ========== ROLE MANAGEMENT ==========
    
    public function createRole()
    {
        return view('admin-panel.roles.create', [
            'permissions' => $this->groupedPermissions(),
            'rolePresets' => Role::with('permissions:id,name')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function storeRole(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|lowercase|unique:roles,name',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        $role = Role::create(['name' => $request->name, 'guard_name' => 'web']);
        
        if ($request->filled('permissions')) {
            $role->givePermissionTo($request->permissions);
        }

        return redirect()->route('admin-panel.index')
            ->with('success', __('Role berhasil dibuat.'));
    }

    public function editRole(Role $role)
    {
        if ($role->name === 'super-admin') {
            abort(403, __('Role super-admin tidak bisa diedit.'));
        }
        
        return view('admin-panel.roles.edit', compact('role') + ['permissions' => $this->groupedPermissions()]);
    }

    public function updateRole(Request $request, Role $role)
    {
        if ($role->name === 'super-admin') {
            abort(403, __('Role super-admin tidak bisa diedit.'));
        }

        $request->validate([
            'name' => 'required|string|max:255|lowercase|unique:roles,name,' . $role->id,
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        $role->update(['name' => $request->name]);
        
        if ($request->has('permissions')) {
            $role->syncPermissions($request->permissions);
        } else {
            $role->syncPermissions([]);
        }

        return redirect()->route('admin-panel.index')
            ->with('success', __('Role berhasil diupdate.'));
    }

    public function destroyRole(Role $role)
    {
        if ($role->name === 'super-admin') {
            abort(403, __('Role super-admin tidak bisa dihapus.'));
        }
        
        $role->delete();
        return redirect()->route('admin-panel.index')
            ->with('success', __('Role berhasil dihapus.'));
    }

    // ========== AUDIT LOG ==========
    
    public function auditLog()
    {
        $logs = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 15);
        return view('admin-panel.audit-log', compact('logs'));
    }

    private function groupedPermissions()
    {
        return Permission::all()->groupBy(function ($p) {
            $parts = explode('-', $p->name);
            return $parts[1] ?? $parts[0] ?? 'other';
        });
    }
}
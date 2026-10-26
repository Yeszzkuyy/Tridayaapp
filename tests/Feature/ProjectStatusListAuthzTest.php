<?php

namespace Tests\Feature;

use App\Livewire\ProjectStatusList;
use App\Models\ProjectStatus;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

/**
 * K2 — otorisasi master ProjectStatusList.
 *
 * Kunci yang dijaga:
 *  - setiap aksi mutasi (save/update/delete) memeriksa otorisasi SETIAP kali dijalankan,
 *    bukan hanya saat halaman dibuka (middleware route tidak berlaku untuk request
 *    /livewire/update, jadi method-level adalah lapisan utamanya);
 *  - pengguna tanpa permission ditolak (HTTP 403 / AuthorizationException);
 *  - id tidak valid = 404, bukan 500;
 *  - halaman 403 dirender ramah (bukan teks mentah).
 */
class ProjectStatusListAuthzTest extends TestCase
{
    use RefreshDatabase;

    /** Role yang TIDAK punya manage-monitoring (sengaja tanpa super-admin: ia punya bypass Gate). */
    private const UNAUTHORIZED_ROLES = ['management', 'ceo', 'technician', 'sales'];

    private function loginAs(string $role): User
    {
        Artisan::call('db:seed', ['--class' => 'RoleAndPermissionSeeder']);
        $user = User::factory()->create();
        $user->assignRole($role);
        return $user;
    }

    /**
     * Jalankan aksi Livewire dan kembalikan exception yang terlempar (null bila sukses).
     */
    private function livewireAct(User $user, array $props, string $action, ...$params): ?\Throwable
    {
        $this->actingAs($user);
        try {
            $component = Livewire::test(ProjectStatusList::class);
            foreach ($props as $key => $value) {
                $component->set($key, $value);
            }
            $component->call($action, ...$params);
            return null;
        } catch (\Throwable $e) {
            return $e;
        }
    }

    private function assertAuthorizationDenied(?\Throwable $e, string $role, string $action): void
    {
        $this->assertNotNull($e, "role [{$role}] seharusnya DITOLAK saat {$action}, tapi aksi berhasil dieksekusi");
        $this->assertInstanceOf(
            AuthorizationException::class,
            $e,
            "role [{$role}] saat {$action} harus ditolak AuthorizationException (HTTP 403), dapat: " . ($e ? get_class($e) . ' — ' . $e->getMessage() : '-')
        );
    }

    // ---------------------------------------------------------------
    // Livewire: pengguna tidak berwenang
    // ---------------------------------------------------------------

    public function test_livewire_save_denied_without_manage_permission(): void
    {
        foreach (self::UNAUTHORIZED_ROLES as $role) {
            $e = $this->livewireAct(
                $this->loginAs($role),
                ['name' => 'Status Baru', 'color' => '#3b82f6', 'is_default' => false],
                'save'
            );
            $this->assertAuthorizationDenied($e, $role, 'save');
        }

        $this->assertDatabaseCount('project_statuses', 0);
    }

    public function test_livewire_update_denied_without_manage_permission(): void
    {
        $status = ProjectStatus::create(['name' => 'Pending', 'color' => '#3b82f6']);

        foreach (self::UNAUTHORIZED_ROLES as $role) {
            $e = $this->livewireAct(
                $this->loginAs($role),
                ['editId' => $status->id, 'editName' => 'Dibobol', 'editColor' => '#000000'],
                'update'
            );
            $this->assertAuthorizationDenied($e, $role, 'update');
        }

        $this->assertSame('Pending', $status->fresh()->name);
    }

    public function test_livewire_delete_denied_without_manage_permission(): void
    {
        foreach (self::UNAUTHORIZED_ROLES as $role) {
            $status = ProjectStatus::create(['name' => 'Aman', 'color' => '#3b82f6']);
            $e = $this->livewireAct($this->loginAs($role), [], 'delete', $status->id);
            $this->assertAuthorizationDenied($e, $role, 'delete');
            $this->assertNotNull(ProjectStatus::find($status->id), "status tidak boleh terhapus oleh role {$role}");
        }
    }

    public function test_livewire_snapshot_tampering_cannot_bypass_authorization(): void
    {
        $status = ProjectStatus::create(['name' => 'Snapshot', 'color' => '#3b82f6']);

        $e = $this->livewireAct(
            $this->loginAs('management'),
            ['editId' => $status->id, 'editName' => 'Dimanipulasi', 'editColor' => '#000000', 'editIsDefault' => true],
            'update'
        );

        $this->assertAuthorizationDenied($e, 'management', 'update (via manipulasi state/snapshot)');
    }

    // ---------------------------------------------------------------
    // Livewire: pengguna berwenang tetap bisa bekerja
    // ---------------------------------------------------------------

    public function test_livewire_mutations_allowed_for_super_admin(): void
    {
        $admin = $this->loginAs('super-admin');

        $e = $this->livewireAct($admin, ['name' => 'Resmi', 'color' => '#3b82f6'], 'save');
        $this->assertNull($e, 'super-admin harus bisa save: ' . ($e?->getMessage()));
        $this->assertDatabaseHas('project_statuses', ['name' => 'Resmi']);

        $status = ProjectStatus::where('name', 'Resmi')->firstOrFail();
        $e = $this->livewireAct($admin, ['editId' => $status->id, 'editName' => 'Resmi 2', 'editColor' => '#ff0000'], 'update');
        $this->assertNull($e, 'super-admin harus bisa update: ' . ($e?->getMessage()));
        $this->assertSame('Resmi 2', $status->fresh()->name);

        $e = $this->livewireAct($admin, [], 'delete', $status->id);
        $this->assertNull($e, 'super-admin harus bisa delete: ' . ($e?->getMessage()));
        $this->assertSoftDeleted('project_statuses', ['id' => $status->id]);
    }

    // ---------------------------------------------------------------
    // Validasi & keamanan tambahan
    // ---------------------------------------------------------------

    public function test_livewire_invalid_id_returns_404_not_500(): void
    {
        $admin = $this->loginAs('super-admin');

        foreach (['delete' => [999999], 'edit' => [999999]] as $action => $params) {
            $e = $this->livewireAct($admin, [], $action, ...$params);
            $this->assertNotNull($e, "{$action} dengan id ngawur harus gagal");
            $this->assertThat(
                $e,
                $this->logicalOr(
                    $this->isInstanceOf(ModelNotFoundException::class),
                    $this->isInstanceOf(NotFoundHttpException::class)
                ),
                "id tidak valid harus menghasilkan 404 (ModelNotFound/NotFoundHttpException), bukan 500: " . get_class($e)
            );
        }
    }

    public function test_livewire_is_default_must_be_boolean(): void
    {
        $this->actingAs($this->loginAs('super-admin'));

        Livewire::test(ProjectStatusList::class)
            ->set('name', 'Validasi')
            ->set('is_default', 'bukan-boolean')
            ->call('save')
            ->assertHasErrors('is_default');

        $this->assertDatabaseMissing('project_statuses', ['name' => 'Validasi']);
    }

    // ---------------------------------------------------------------
    // HTTP (jalur controller admin-panel)
    // ---------------------------------------------------------------

    public function test_http_mutations_denied_for_unauthorized_roles(): void
    {
        $status = ProjectStatus::create(['name' => 'HTTP', 'color' => '#3b82f6']);

        foreach (self::UNAUTHORIZED_ROLES as $role) {
            $user = $this->loginAs($role);
            $this->actingAs($user)
                ->post(route('admin-panel.project-statuses.store'), ['name' => 'X'])
                ->assertForbidden();
            $this->actingAs($user)
                ->put(route('admin-panel.project-statuses.update', $status), ['name' => 'Y'])
                ->assertForbidden();
            $this->actingAs($user)
                ->delete(route('admin-panel.project-statuses.destroy', $status))
                ->assertForbidden();
        }

        $this->assertSame('HTTP', $status->fresh()->name);
        $this->assertDatabaseMissing('project_statuses', ['name' => 'X']);
    }

    public function test_http_crud_still_works_for_super_admin(): void
    {
        $admin = $this->loginAs('super-admin');

        $this->actingAs($admin)->post(route('admin-panel.project-statuses.store'), [
            'name' => 'HTTP Baru', 'color' => '#3b82f6', 'is_default' => 1,
        ])->assertRedirect(route('admin-panel.project-statuses.index'));
        $this->assertDatabaseHas('project_statuses', ['name' => 'HTTP Baru', 'is_default' => 1]);

        $status = ProjectStatus::where('name', 'HTTP Baru')->firstOrFail();
        $this->actingAs($admin)->put(route('admin-panel.project-statuses.update', $status), [
            'name' => 'HTTP Baru 2', 'color' => '#ff0000',
        ])->assertRedirect(route('admin-panel.project-statuses.index'));
        $this->assertSame('HTTP Baru 2', $status->fresh()->name);

        $this->actingAs($admin)
            ->delete(route('admin-panel.project-statuses.destroy', $status))
            ->assertRedirect(route('admin-panel.project-statuses.index'));
        $this->assertSoftDeleted('project_statuses', ['id' => $status->id]);
    }

    public function test_http_invalid_id_returns_404_not_500(): void
    {
        $admin = $this->loginAs('super-admin');

        $this->actingAs($admin)->get(route('admin-panel.project-statuses.edit', 999999))->assertNotFound();
        $this->actingAs($admin)->put(route('admin-panel.project-statuses.update', 999999), ['name' => 'X'])->assertNotFound();
        $this->actingAs($admin)->delete(route('admin-panel.project-statuses.destroy', 999999))->assertNotFound();
    }

    // ---------------------------------------------------------------
    // UX penolakan akses: halaman 403 harus ramah, bukan teks mentah
    // ---------------------------------------------------------------

    public function test_forbidden_page_renders_friendly_error_view(): void
    {
        $response = $this->actingAs($this->loginAs('technician'))->get(route('admin-panel.project-statuses.index'));

        $response->assertForbidden();
        $response->assertSee(__('Access Denied'));
        $response->assertSee(__('You do not have permission to access this page.'));
        $response->assertSee(__('Back'));
        $response->assertSee(__('Dashboard'));
        $response->assertSee(route('dashboard'));
    }
}

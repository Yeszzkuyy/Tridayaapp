<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Instalasi;
use App\Models\Lead;
use App\Models\Survey;
use App\Models\TechnicalRequest;
use App\Models\TechnicianSchedule;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Engineer Request mendukung satu engineer utama + banyak support engineer.
 *
 * Aturan yang dijaga:
 * 1. Support boleh banyak, tidak boleh dobel dengan engineer utama.
 * 2. Engineer utama tetap di assigned_technician_id; support di pivot.
 * 3. Support boleh mengerjakan progres, hasil teknis, dan menyelesaikan.
 * 4. Ganti engineer utama tidak menghapus support lama.
 * 5. Jadwal memuat engineer utama + support dan ikut cek bentrok.
 * 6. Survey/instalasi menampilkan support dari request induknya.
 */
class TechnicalRequestSupportEngineerTest extends TestCase
{
    use RefreshDatabase;

    private bool $seeded = false;

    private function userWithRole(string $role, ?string $name = null): User
    {
        // Seeder role cukup dijalankan sekali per test.
        if (! $this->seeded) {
            $this->seed(RoleAndPermissionSeeder::class);
            $this->seeded = true;
        }

        $user = User::factory()->create($name ? ['name' => $name] : []);
        $user->assignRole($role);

        return $user;
    }

    private function makeLead(User $sales): Lead
    {
        $customer = Customer::create([
            'name' => 'PT Support '.uniqid(),
            'company' => 'Tridaya Group',
            'address' => 'Jl. Support No. 1',
            'contact_person' => 'Pak Support',
            'phone' => '081200009999',
        ]);

        return Lead::create([
            'customer_id' => $customer->id,
            'assigned_to' => $sales->id,
            'pt_group' => 'NTI',
            'segment' => 'vendor',
            'status' => 'hot',
        ]);
    }

    private function makeRequest(User $sales, Lead $lead, array $overrides = []): TechnicalRequest
    {
        return TechnicalRequest::create(array_merge([
            'lead_id' => $lead->id,
            'requested_by' => $sales->id,
            'request_type' => 'consultation',
            'title' => 'Konsultasi Jaringan',
            'priority' => 'normal',
            'status' => 'accepted',
        ], $overrides));
    }

    public function test_lead_engineer_can_assign_many_support_engineers_at_once(): void
    {
        Notification::fake();

        $sales = $this->userWithRole('sales');
        $coordinator = $this->userWithRole('lead-technician');
        $main = $this->userWithRole('technician', 'Engineer Utama');
        $lead = $this->makeLead($sales);
        $request = $this->makeRequest($sales, $lead);

        $supports = collect(range(1, 6))
            ->map(fn (int $i) => $this->userWithRole('technician', 'Support '.$i));

        $this->actingAs($coordinator)
            ->post(route('sales.technical-requests.assign', $request), [
                'technician_id' => $main->id,
                'support_ids' => $supports->pluck('id')->all(),
                'support_present' => 1,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $request->refresh();

        $this->assertSame($main->id, (int) $request->assigned_technician_id);
        $this->assertCount(6, $request->supportUsers);
        $this->assertEqualsCanonicalizing(
            $supports->pluck('id')->all(),
            $request->supportUsers->pluck('id')->map(fn ($id) => (int) $id)->all()
        );
    }

    public function test_main_engineer_cannot_be_saved_twice_as_support(): void
    {
        Notification::fake();

        $sales = $this->userWithRole('sales');
        $coordinator = $this->userWithRole('lead-technician');
        $main = $this->userWithRole('technician');
        $support = $this->userWithRole('technician');
        $lead = $this->makeLead($sales);
        $request = $this->makeRequest($sales, $lead);

        $this->actingAs($coordinator)
            ->post(route('sales.technical-requests.assign', $request), [
                'technician_id' => $main->id,
                'support_ids' => [$main->id, $support->id, $support->id],
                'support_present' => 1,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame([$support->id], $request->fresh()->supportUsers->pluck('id')->all());
    }

    public function test_support_engineer_can_progress_submit_result_and_complete(): void
    {
        Notification::fake();

        $sales = $this->userWithRole('sales');
        $coordinator = $this->userWithRole('lead-technician');
        $main = $this->userWithRole('technician');
        $support = $this->userWithRole('technician');
        $lead = $this->makeLead($sales);
        $request = $this->makeRequest($sales, $lead);

        $this->actingAs($coordinator)
            ->post(route('sales.technical-requests.assign', $request), [
                'technician_id' => $main->id,
                'support_ids' => [$support->id],
                'support_present' => 1,
            ])
            ->assertRedirect();

        $this->actingAs($support)
            ->post(route('sales.technical-requests.progress', $request), [
                'notes' => 'Pengecekan awal selesai.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertSame('in_progress', $request->refresh()->status);

        $this->actingAs($support)
            ->post(route('sales.technical-requests.result', $request), [
                'technical_result' => 'Jaringan stabil, tidak ada kendala.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertSame('Jaringan stabil, tidak ada kendala.', $request->refresh()->technical_result);

        $this->actingAs($support)
            ->post(route('sales.technical-requests.complete', $request))
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertSame('completed', $request->refresh()->status);
    }

    public function test_changing_main_engineer_keeps_existing_support(): void
    {
        Notification::fake();

        $sales = $this->userWithRole('sales');
        $coordinator = $this->userWithRole('lead-technician');
        $mainA = $this->userWithRole('technician');
        $mainB = $this->userWithRole('technician');
        $supportA = $this->userWithRole('technician');
        $supportB = $this->userWithRole('technician');
        $lead = $this->makeLead($sales);
        $request = $this->makeRequest($sales, $lead);

        $this->actingAs($coordinator)->post(route('sales.technical-requests.assign', $request), [
            'technician_id' => $mainA->id,
            'support_ids' => [$supportA->id, $supportB->id],
            'support_present' => 1,
        ])->assertRedirect();

        // Pemanggil lama (tanpa field support) hanya mengganti engineer utama;
        // support yang sudah ada harus tetap dipertahankan.
        $this->actingAs($coordinator)->post(route('sales.technical-requests.assign', $request), [
            'technician_id' => $mainB->id,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $request->refresh();
        $this->assertSame($mainB->id, (int) $request->assigned_technician_id);
        $this->assertEqualsCanonicalizing(
            [$supportA->id, $supportB->id],
            $request->supportUsers->pluck('id')->all()
        );

        // Form baru yang mengirim daftar support kosong berarti menghapus support.
        $this->actingAs($coordinator)->post(route('sales.technical-requests.assign', $request), [
            'technician_id' => $mainB->id,
            'support_present' => 1,
        ])->assertRedirect();

        $this->assertCount(0, $request->fresh()->supportUsers);
    }

    public function test_support_engineer_sees_task_and_inbox_but_other_engineers_do_not(): void
    {
        Notification::fake();

        $sales = $this->userWithRole('sales');
        $coordinator = $this->userWithRole('lead-technician');
        $main = $this->userWithRole('technician');
        $support = $this->userWithRole('technician');
        $outsider = $this->userWithRole('technician');
        $lead = $this->makeLead($sales);
        $request = $this->makeRequest($sales, $lead, [
            'title' => 'Konsultasi Support Terlihat',
        ]);

        $this->actingAs($coordinator)->post(route('sales.technical-requests.assign', $request), [
            'technician_id' => $main->id,
            'support_ids' => [$support->id],
            'support_present' => 1,
        ])->assertRedirect();

        $this->actingAs($support)
            ->get(route('teknisi.tasks.show', $request))
            ->assertOk();

        $tasksHtml = $this->actingAs($support)
            ->get(route('teknisi.tasks.index'))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString($request->title, $tasksHtml);
        $this->assertStringContainsString(__('Support Technicians'), $tasksHtml);
        $this->assertStringContainsString($support->name, $tasksHtml);

        $inboxHtml = $this->actingAs($support)
            ->get(route('teknisi.requests.index', ['tab' => 'engineer']))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString($request->title, $inboxHtml);
        $this->assertStringContainsString(__('Support Technicians'), $inboxHtml);
        $this->assertStringContainsString($support->name, $inboxHtml);

        // Sales melihat support pada daftar Request Tim Engineer.
        $salesHtml = $this->actingAs($sales)
            ->get(route('sales.requests.index', ['tab' => 'engineer']))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString(__('Support Technicians'), $salesHtml);
        $this->assertStringContainsString($support->name, $salesHtml);

        $this->actingAs($outsider)
            ->get(route('teknisi.tasks.show', $request))
            ->assertForbidden();
    }

    public function test_assign_creates_one_schedule_containing_lead_and_support_engineers(): void
    {
        Notification::fake();

        $sales = $this->userWithRole('sales');
        $coordinator = $this->userWithRole('lead-technician');
        $main = $this->userWithRole('technician');
        $supportA = $this->userWithRole('technician');
        $supportB = $this->userWithRole('technician');
        $lead = $this->makeLead($sales);
        $request = $this->makeRequest($sales, $lead, [
            'target_date' => '2026-12-01',
            'target_time' => '09:00',
        ]);

        $this->actingAs($coordinator)->post(route('sales.technical-requests.assign', $request), [
            'technician_id' => $main->id,
            'support_ids' => [$supportA->id, $supportB->id],
            'support_present' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $schedule = TechnicianSchedule::where('technical_request_id', $request->id)->firstOrFail();

        $this->assertSame($main->id, (int) $schedule->technician_user_id);
        $this->assertEqualsCanonicalizing(
            [$main->id, $supportA->id, $supportB->id],
            $schedule->technicians()->pluck('users.id')->map(fn ($id) => (int) $id)->all()
        );
    }

    public function test_support_engineer_schedule_conflict_blocks_assignment(): void
    {
        Notification::fake();

        $sales = $this->userWithRole('sales');
        $coordinator = $this->userWithRole('lead-technician');
        $main = $this->userWithRole('technician');
        $support = $this->userWithRole('technician');
        $lead = $this->makeLead($sales);
        $request = $this->makeRequest($sales, $lead, [
            'target_date' => '2026-12-02',
            'target_time' => '09:00',
        ]);

        $existing = TechnicianSchedule::create([
            'user_id' => $coordinator->id,
            'title' => 'Instalasi Lain',
            'start_at' => Carbon::parse('2026-12-02 08:00:00'),
            'end_at' => Carbon::parse('2026-12-02 11:00:00'),
            'status' => 'scheduled',
        ]);
        $existing->technicians()->attach($support->id);

        $this->actingAs($coordinator)
            ->post(route('sales.technical-requests.assign', $request), [
                'technician_id' => $main->id,
                'support_ids' => [$support->id],
                'support_present' => 1,
            ])
            ->assertSessionHasErrors('support_ids');

        // Assign yang gagal tidak boleh meninggalkan penugasan setengah jadi.
        $request->refresh();
        $this->assertNull($request->assigned_technician_id);
        $this->assertCount(0, $request->supportUsers);
    }

    public function test_support_engineer_is_shown_on_survey_and_installation_detail(): void
    {
        Notification::fake();

        $sales = $this->userWithRole('sales');
        $coordinator = $this->userWithRole('lead-technician');
        $main = $this->userWithRole('technician');
        $support = $this->userWithRole('technician', 'Support Lapangan');
        $lead = $this->makeLead($sales);

        $surveyRequest = $this->makeRequest($sales, $lead, [
            'request_type' => 'survey',
            'title' => 'Survey Support',
        ]);
        $this->actingAs($coordinator)->post(route('sales.technical-requests.assign', $surveyRequest), [
            'technician_id' => $main->id,
            'support_ids' => [$support->id],
            'support_present' => 1,
        ])->assertRedirect();

        $survey = Survey::where('technical_request_id', $surveyRequest->id)->firstOrFail();
        $surveyHtml = $this->actingAs($main)
            ->get(route('teknisi.surveys.show', $survey))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString($support->name, $surveyHtml);

        $installationRequest = $this->makeRequest($sales, $lead, [
            'request_type' => 'installation',
            'title' => 'Instalasi Support',
        ]);
        $this->actingAs($coordinator)->post(route('sales.technical-requests.assign', $installationRequest), [
            'technician_id' => $main->id,
            'support_ids' => [$support->id],
            'support_present' => 1,
        ])->assertRedirect();

        $instalasi = Instalasi::where('technical_request_id', $installationRequest->id)->firstOrFail();
        $instalasiHtml = $this->actingAs($main)
            ->get(route('teknisi.instalasis.show', $instalasi))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString($support->name, $instalasiHtml);
    }

    public function test_support_engineer_must_have_technician_role(): void
    {
        Notification::fake();

        $sales = $this->userWithRole('sales');
        $coordinator = $this->userWithRole('lead-technician');
        $main = $this->userWithRole('technician');
        $lead = $this->makeLead($sales);
        $request = $this->makeRequest($sales, $lead);

        $this->actingAs($coordinator)
            ->post(route('sales.technical-requests.assign', $request), [
                'technician_id' => $main->id,
                'support_ids' => [$sales->id],
                'support_present' => 1,
            ])
            ->assertSessionHasErrors('support_ids.0');

        $this->assertNull($request->fresh()->assigned_technician_id);
    }
}

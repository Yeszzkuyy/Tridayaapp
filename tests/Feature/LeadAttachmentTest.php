<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LeadAttachmentTest extends TestCase
{
    use RefreshDatabase;

    private function marketingUser(): User
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('marketing');

        return $user;
    }

    private function leadPayload(array $overrides = []): array
    {
        return array_merge([
            'customer_mode' => 'new',
            'customer_name' => 'PT Lampiran',
            'pt_group' => 'NTI',
            'assigned_to' => User::first()->id,
            'segment' => 'vendor',
            'kebutuhan' => 'BOQ awal lampiran.',
            'incoming_date' => '2026-08-26',
        ], $overrides);
    }

    public function test_store_saves_attachments(): void
    {
        Storage::fake('private');
        $user = $this->marketingUser();

        $this->actingAs($user)->post(route('leads.store'), $this->leadPayload([
            'attachments' => [
                UploadedFile::fake()->create('boq-awal.pdf', 100, 'application/pdf'),
                UploadedFile::fake()->image('kebutuhan.png'),
            ],
        ]))->assertRedirect(route('leads.index'));

        $lead = Lead::whereHas('customer', fn ($q) => $q->where('name', 'PT Lampiran'))->first();

        $this->assertCount(2, $lead->documents);
        $this->assertSame('boq-awal.pdf', $lead->documents->first()->file_name);
        Storage::disk('private')->assertExists($lead->documents->first()->file_path);
    }

    public function test_show_lists_attachments_and_streams_file(): void
    {
        Storage::fake('private');
        $user = $this->marketingUser();
        $customer = Customer::create(['name' => 'PT Tampil']);
        $lead = Lead::create(['customer_id' => $customer->id, 'pt_group' => 'NTI', 'segment' => 'vendor']);
        Storage::disk('private')->put('leads/1/boq.pdf', 'isi');

        $lead->documents()->create([
            'file_name' => 'boq.pdf',
            'file_path' => 'leads/1/boq.pdf',
            'mime_type' => 'application/pdf',
        ]);

        $html = $this->actingAs($user)->get(route('leads.show', $lead))
            ->assertOk()
            ->assertSee('boq.pdf')
            ->getContent();

        $this->assertStringContainsString(route('leads.attachments.show', [$lead, $lead->documents->first()]), $html);

        $this->get(route('leads.attachments.show', [$lead, $lead->documents->first()]))
            ->assertOk();
    }

    public function test_edit_can_add_more_attachments(): void
    {
        Storage::fake('private');
        $user = $this->marketingUser();
        $customer = Customer::create(['name' => 'PT Tambah']);
        $lead = Lead::create(['customer_id' => $customer->id, 'pt_group' => 'NTI', 'segment' => 'vendor']);

        $this->actingAs($user)->put(route('leads.update', $lead), [
            'customer_mode' => 'existing',
            'customer_id' => $customer->id,
            'pt_group' => 'NTI',
            'assigned_to' => $user->id,
            'segment' => 'vendor',
            'kebutuhan' => 'BOQ jaringan kantor.',
            'incoming_date' => '2026-08-26',
            'attachments' => [UploadedFile::fake()->create('requirement.xlsx')],
        ])->assertRedirect(route('leads.index'));

        $this->assertSame('requirement.xlsx', $lead->fresh()->documents->first()->file_name);
    }

    public function test_destroy_removes_document_and_file(): void
    {
        Storage::fake('private');
        $user = $this->marketingUser();
        $customer = Customer::create(['name' => 'PT Hapus']);
        $lead = Lead::create(['customer_id' => $customer->id, 'pt_group' => 'NTI', 'segment' => 'vendor']);
        Storage::disk('private')->put('leads/1/boq.pdf', 'isi');

        $doc = $lead->documents()->create([
            'file_name' => 'boq.pdf',
            'file_path' => 'leads/1/boq.pdf',
            'mime_type' => 'application/pdf',
        ]);

        $this->actingAs($user)->delete(route('leads.attachments.destroy', [$lead, $doc]))
            ->assertRedirect();

        $this->assertDatabaseMissing('lead_documents', ['id' => $doc->id]);
        Storage::disk('private')->assertMissing('leads/1/boq.pdf');

        $this->assertDatabaseHas('lead_activities', [
            'lead_id' => $lead->id,
            'user_id' => $user->id,
            'action' => 'attachment_deleted',
        ]);
    }

    public function test_cannot_delete_attachment_via_other_lead(): void
    {
        Storage::fake('private');
        $user = $this->marketingUser();
        $c1 = Customer::create(['name' => 'PT Satu']);
        $c2 = Customer::create(['name' => 'PT Dua']);
        $lead1 = Lead::create(['customer_id' => $c1->id, 'pt_group' => 'NTI', 'segment' => 'vendor']);
        $lead2 = Lead::create(['customer_id' => $c2->id, 'pt_group' => 'NTI', 'segment' => 'vendor']);

        $doc = $lead1->documents()->create([
            'file_name' => 'boq.pdf',
            'file_path' => 'leads/1/boq.pdf',
        ]);

        $this->actingAs($user)->delete(route('leads.attachments.destroy', [$lead2, $doc]))
            ->assertNotFound();

        $this->assertDatabaseHas('lead_documents', ['id' => $doc->id]);
    }
}

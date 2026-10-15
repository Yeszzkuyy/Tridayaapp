<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class SalesDarExportTest extends TestCase
{
    use RefreshDatabase;

    private function salesUser(): User
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('sales');

        return $user;
    }

    private function makeLead(User $assignee, Customer $customer): Lead
    {
        return Lead::create([
            'customer_id' => $customer->id,
            'pt_group' => 'NTI',
            'segment' => 'end_user',
            'status' => 'cool',
            'incoming_date' => now()->toDateString(),
            'assigned_to' => $assignee->id,
        ]);
    }

    public function test_log_follow_up_from_lead_redirects_back_and_saves_details(): void
    {
        $sales = $this->salesUser();
        $customer = Customer::create(['name' => 'PT Log Lead']);
        $lead = $this->makeLead($sales, $customer);

        $this->actingAs($sales)->post(route('sales.follow-ups.store'), [
            'return' => 'lead',
            'customer_id' => $customer->id,
            'lead_id' => $lead->id,
            'type' => 'meeting',
            'follow_up_date' => today()->toDateString(),
            'activity_time' => '14:30',
            'location' => 'Kantor Klien',
            'description' => 'Kunjungan survei lokasi.',
        ])->assertRedirect(route('leads.show', $lead));

        $followUp = FollowUp::firstOrFail();
        $this->assertSame($lead->id, $followUp->lead_id);
        $this->assertSame('meeting', $followUp->type);
        $this->assertSame('Kantor Klien', $followUp->location);
    }

    public function test_dar_export_only_includes_current_sales_activities(): void
    {
        $sales = $this->salesUser();
        $other = User::factory()->create();
        $other->assignRole('sales');

        $ownCustomer = Customer::create(['name' => 'PT Punyaku']);
        $otherCustomer = Customer::create(['name' => 'PT Punya Orang']);
        $ownLead = $this->makeLead($sales, $ownCustomer);
        $otherLead = $this->makeLead($other, $otherCustomer);

        FollowUp::create([
            'customer_id' => $ownCustomer->id, 'lead_id' => $ownLead->id, 'created_by' => $sales->id,
            'type' => 'whatsapp', 'follow_up_date' => today()->toDateString(), 'description' => 'Chat penawaran.',
        ]);
        FollowUp::create([
            'customer_id' => $otherCustomer->id, 'lead_id' => $otherLead->id, 'created_by' => $other->id,
            'type' => 'call', 'follow_up_date' => today()->toDateString(), 'description' => 'Telepon orang lain.',
        ]);

        $response = $this->actingAs($sales)->get(route('sales.dar.export'));
        $response->assertOk();

        $flat = collect($this->readXlsx($response->streamedContent()))->flatten()->implode('|');
        $this->assertStringContainsString('PT Punyaku', $flat);
        $this->assertStringContainsString('End User', $flat);
        $this->assertStringNotContainsString('PT Punya Orang', $flat);
    }

    private function readXlsx(string $content): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'dar').'.xlsx';
        file_put_contents($tmp, $content);
        $rows = IOFactory::load($tmp)->getActiveSheet()->toArray();
        @unlink($tmp);

        return $rows;
    }
}

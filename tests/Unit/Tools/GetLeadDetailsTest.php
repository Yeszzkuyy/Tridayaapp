<?php

namespace Tests\Unit\Tools;

use App\Ai\Tools\GetLeadDetails;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class GetLeadDetailsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    private function makeUser(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function makeLead(?User $assignee = null): Lead
    {
        $customer = Customer::create(['name' => 'PT Contoh', 'company' => 'PT Contoh Teknologi']);

        return Lead::create([
            'customer_id' => $customer->id,
            'status' => 'hot',
            'assigned_to' => $assignee?->id,
            'kebutuhan' => 'Butuh CCTV 16 titik',
            'solusi' => 'Proposal kamera IP',
        ]);
    }

    public function test_marketing_can_view_any_lead(): void
    {
        $marketing = $this->makeUser('marketing');
        $lead = $this->makeLead($this->makeUser('sales'));

        $result = json_decode((string) (new GetLeadDetails($marketing))->handle(new Request([
            'lead_id' => $lead->id,
        ])), true);

        $this->assertSame('PT Contoh', $result['customer']);
        $this->assertSame('hot', $result['status']);
        $this->assertSame('Butuh CCTV 16 titik', $result['kebutuhan']);
    }

    public function test_sales_can_view_own_lead(): void
    {
        $sales = $this->makeUser('sales');
        $lead = $this->makeLead($sales);

        $result = json_decode((string) (new GetLeadDetails($sales))->handle(new Request([
            'lead_id' => $lead->id,
        ])), true);

        $this->assertSame('PT Contoh', $result['customer']);
    }

    public function test_sales_cannot_view_other_leads_lead(): void
    {
        $sales = $this->makeUser('sales');
        $lead = $this->makeLead($this->makeUser('sales'));

        $result = (string) (new GetLeadDetails($sales))->handle(new Request([
            'lead_id' => $lead->id,
        ]));

        $this->assertStringContainsString('tidak ditemukan atau kamu tidak berizin', $result);
    }

    public function test_user_without_permission_is_denied(): void
    {
        $teknisi = $this->makeUser('technician');
        $lead = $this->makeLead();

        $result = (string) (new GetLeadDetails($teknisi))->handle(new Request([
            'lead_id' => $lead->id,
        ]));

        $this->assertStringContainsString('Akses ditolak', $result);
    }

    public function test_summary_without_lead_id_is_scoped_per_user(): void
    {
        $sales = $this->makeUser('sales');
        $otherSales = $this->makeUser('sales');

        $this->makeLead($sales);
        $this->makeLead($sales);
        $this->makeLead($otherSales);

        $result = json_decode((string) (new GetLeadDetails($sales))->handle(new Request), true);

        $this->assertSame(2, $result['total']);
    }
}

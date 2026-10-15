<?php

namespace Tests\Unit\Tools;

use App\Ai\Tools\GetSalesSummary;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class GetSalesSummaryTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function seedSalesData(): void
    {
        $customer = Customer::create(['name' => 'PT Contoh']);

        Lead::create(['customer_id' => $customer->id, 'status' => 'cool']);
        Lead::create(['customer_id' => $customer->id, 'status' => 'won']);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-001',
            'customer_id' => $customer->id,
            'amount' => 10000000,
            'issue_date' => now()->toDateString(),
        ]);

        Payment::create([
            'invoice_id' => $invoice->id,
            'amount' => 4000000,
            'payment_date' => now()->toDateString(),
        ]);

        PurchaseOrder::create([
            'po_number' => 'PO-001',
            'customer_id' => $customer->id,
            'items' => 'Kamera CCTV',
            'amount' => 5000000,
            'issue_date' => now()->toDateString(),
        ]);
    }

    public function test_admin_gets_sales_summary(): void
    {
        $admin = $this->userWithRole('admin');
        $this->seedSalesData();

        $result = json_decode((string) (new GetSalesSummary($admin))->handle(new Request), true);

        $this->assertSame(2, $result['leads']['total']);
        $this->assertSame(1, $result['customers']);
        $this->assertSame(1, $result['invoices']['total']);
        $this->assertSame(10000000, (int) $result['invoices']['total_amount']);
        $this->assertSame(4000000, (int) $result['invoices']['paid']);
        $this->assertSame(6000000, (int) $result['invoices']['outstanding']);
        $this->assertSame(1, $result['purchase_orders']['total']);
    }

    public function test_ceo_gets_sales_summary(): void
    {
        $ceo = $this->userWithRole('ceo');
        $this->seedSalesData();

        $result = json_decode((string) (new GetSalesSummary($ceo))->handle(new Request), true);

        $this->assertSame(2, $result['leads']['total']);
    }

    public function test_marketing_is_denied(): void
    {
        $marketing = $this->userWithRole('marketing');

        $result = (string) (new GetSalesSummary($marketing))->handle(new Request);

        $this->assertStringContainsString('Akses ditolak', $result);
    }
}

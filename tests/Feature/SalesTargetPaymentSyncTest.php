<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\Proposal;
use App\Models\SalesTarget;
use App\Models\User;
use App\Notifications\InvoicePaidSalesNotification;
use App\Services\AdminService;
use App\Services\SalesKpiService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SalesTargetPaymentSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        return User::factory()->create()->assignRole($role);
    }

    private function invoiceForSales(User $sales, float $amount, string $number = 'INV-TEST-001'): Invoice
    {
        $customer = Customer::create(['name' => 'PT Uji Target']);

        $lead = Lead::create([
            'customer_id' => $customer->id,
            'status' => 'hot',
            'assigned_to' => $sales->id,
            'kebutuhan' => 'Instalasi CCTV',
            'incoming_date' => now()->toDateString(),
        ]);

        $proposal = Proposal::create([
            'lead_id' => $lead->id,
            'proposal_number' => 'QUO-TEST-001',
            'created_by' => $sales->id,
            'proposal_date' => now()->toDateString(),
            'customer' => $customer->name,
            'grand_total' => $amount,
            'status' => 'accepted',
            'po_verified_at' => now(),
            'po_verified_by' => $sales->id,
        ]);

        return Invoice::create([
            'invoice_number' => $number,
            'customer_id' => $customer->id,
            'proposal_id' => $proposal->id,
            'amount' => $amount,
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
        ]);
    }

    public function test_payment_that_settles_invoice_moves_target_and_notifies_sales_and_management(): void
    {
        Notification::fake();

        $sales = $this->userWithRole('sales');
        $management = $this->userWithRole('management');

        SalesTarget::create([
            'user_id' => $sales->id,
            'period_month' => now()->month,
            'period_year' => now()->year,
            'target_revenue' => 50000000,
            'target_deals' => 1,
        ]);

        $invoice = $this->invoiceForSales($sales, 50000000);

        app(AdminService::class)->createPayment([
            'invoice_id' => $invoice->id,
            'amount' => 50000000,
            'payment_date' => now()->toDateString(),
        ]);

        $this->assertSame('paid', $invoice->fresh()->status);

        $kpi = app(SalesKpiService::class)->getUserKpi($sales, now()->month, now()->year);

        $this->assertSame(50000000.0, $kpi['paid_revenue']);
        $this->assertSame(50000000.0, $kpi['verified_po_revenue']);
        $this->assertSame(1, $kpi['verified_po_deals']);
        $this->assertSame(100.0, $kpi['revenue_pct']);

        Notification::assertSentTo($sales, InvoicePaidSalesNotification::class);
        Notification::assertSentTo($management, InvoicePaidSalesNotification::class);
    }

    public function test_partial_payment_counts_as_paid_revenue_without_marking_invoice_paid(): void
    {
        Notification::fake();

        $sales = $this->userWithRole('sales');
        $invoice = $this->invoiceForSales($sales, 10000000);

        app(AdminService::class)->createPayment([
            'invoice_id' => $invoice->id,
            'amount' => 4000000,
            'payment_date' => now()->toDateString(),
        ]);

        $kpi = app(SalesKpiService::class)->getUserKpi($sales, now()->month, now()->year);

        $this->assertSame('partial', $invoice->fresh()->status);
        $this->assertSame(4000000.0, $kpi['paid_revenue']);
        $this->assertSame(10000000.0, $kpi['verified_po_revenue']);

        Notification::assertNothingSent();
    }

    public function test_payment_from_another_sales_does_not_count(): void
    {
        $sales = $this->userWithRole('sales');
        $otherSales = $this->userWithRole('sales');
        $invoice = $this->invoiceForSales($sales, 8000000);

        app(AdminService::class)->createPayment([
            'invoice_id' => $invoice->id,
            'amount' => 8000000,
            'payment_date' => now()->toDateString(),
        ]);

        $kpi = app(SalesKpiService::class)->getUserKpi($otherSales, now()->month, now()->year);

        $this->assertSame(0.0, $kpi['paid_revenue']);
        $this->assertSame(0.0, $kpi['verified_po_revenue']);
        $this->assertSame(1, Payment::count());
    }
}

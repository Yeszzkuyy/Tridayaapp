<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\GoodsReceipt;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ProjectDocument;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierPurchaseOrder;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminReadPermissionTest extends TestCase
{
    use RefreshDatabase;

    private string $storagePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->storagePath = sys_get_temp_dir().'/3dy-perm-'.uniqid();
        $this->app->useStoragePath($this->storagePath);
        File::ensureDirectoryExists($this->storagePath.'/framework/testing/disks');
        Storage::fake('private');

        $this->seed(RoleAndPermissionSeeder::class);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->storagePath);
        parent::tearDown();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function fixtures(): array
    {
        $customer = Customer::create(['name' => 'PT Permission Audit', 'pt_group' => 'NTI']);

        $supplier = Supplier::create([
            'code' => 'SUP-PERM',
            'name' => 'PT Supplier Permission',
            'is_active' => true,
        ]);

        $order = SupplierPurchaseOrder::create([
            'po_number' => 'SPO-PERM-'.uniqid(),
            'supplier_id' => $supplier->id,
            'issue_date' => now()->toDateString(),
            'status' => 'ordered',
        ]);

        $supplierInvoice = SupplierInvoice::create([
            'internal_ref_number' => 'SINV-PERM-'.uniqid(),
            'supplier_invoice_number' => 'SUP-PERM-'.uniqid(),
            'supplier_id' => $supplier->id,
            'supplier_purchase_order_id' => $order->id,
            'invoice_type' => 'full',
            'amount' => 100,
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
        ]);

        $receipt = GoodsReceipt::create([
            'receipt_number' => 'GR-PERM-'.uniqid(),
            'supplier_purchase_order_id' => $order->id,
            'supplier_id' => $supplier->id,
            'received_date' => now()->toDateString(),
            'status' => 'verified',
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-PERM-'.uniqid(),
            'customer_id' => $customer->id,
            'amount' => 100,
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
        ]);

        Storage::disk('private')->put('payments/perm-proof.pdf', 'fake proof');
        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'amount' => 100,
            'payment_date' => now()->toDateString(),
            'proof_file' => 'payments/perm-proof.pdf',
        ]);

        return compact('customer', 'supplier', 'order', 'supplierInvoice', 'receipt', 'invoice', 'payment');
    }

    public function test_role_matrix_for_split_admin_read_permissions(): void
    {
        $f = $this->fixtures();

        $pages = [
            'supplier_master' => route('admin.suppliers.index'),
            'supplier_po' => route('admin.supplier-pos.index'),
            'supplier_po_detail' => route('admin.supplier-pos.show', $f['order']),
            'supplier_invoice' => route('admin.supplier-invoices.index'),
            'supplier_invoice_detail' => route('admin.supplier-invoices.show', $f['supplierInvoice']),
            'payment_proof' => route('admin.payments.proof', $f['payment']),
            'goods_receipt' => route('admin.goods-receipts.index'),
            'goods_receipt_detail' => route('admin.goods-receipts.show', $f['receipt']),
            'admin_hub' => route('admin.hub'),
            'manage_hub' => route('manage.admin.index'),
        ];

        $allowed = [
            'super-admin' => array_keys($pages),
            'admin' => array_keys($pages),
            'purchasing' => ['supplier_master', 'supplier_po', 'supplier_po_detail', 'supplier_invoice', 'supplier_invoice_detail', 'goods_receipt', 'goods_receipt_detail', 'admin_hub', 'manage_hub'],
            'finance' => array_keys($pages),
            'management' => array_keys($pages),
            'ceo' => array_keys($pages),
        ];

        $denied = ['sales', 'sales-nti', 'inside-sales', 'marketing', 'technician', 'lead-technician', 'prakerin-admin'];

        foreach ($allowed as $role => $allowedPages) {
            $user = $this->userWithRole($role);

            foreach ($pages as $key => $url) {
                $response = $this->actingAs($user)->get($url);

                if (in_array($key, $allowedPages, true)) {
                    $this->assertSame(200, $response->getStatusCode(), "$role seharusnya boleh membuka $key");
                } else {
                    $this->assertSame(403, $response->getStatusCode(), "$role seharusnya ditolak di $key");
                }
            }
        }

        foreach ($denied as $role) {
            $user = $this->userWithRole($role);

            foreach ($pages as $key => $url) {
                $this->assertSame(
                    403,
                    $this->actingAs($user)->get($url)->getStatusCode(),
                    "$role seharusnya ditolak di $key"
                );
            }
        }
    }

    public function test_sales_can_still_read_own_quotation_and_margin(): void
    {
        $sales = $this->userWithRole('sales');
        $customer = Customer::create(['name' => 'PT Sales Quotation', 'pt_group' => 'NTI']);

        $lead = \App\Models\Lead::create([
            'customer_id' => $customer->id,
            'assigned_to' => $sales->id,
            'pt_group' => 'NTI',
            'segment' => 'vendor',
            'status' => 'hot',
        ]);

        $product = \App\Models\Product::create([
            'name' => 'Switch Permission',
            'sku' => 'SW-PERM-'.uniqid(),
            'category' => 'barang',
            'unit' => 'unit',
            'default_price' => 100,
            'cost_price' => 60,
            'status' => \App\Models\Product::STATUS_APPROVED,
            'is_active' => true,
        ]);

        $proposal = \App\Models\Proposal::create([
            'lead_id' => $lead->id,
            'proposal_number' => 'Q-PERM-'.uniqid(),
            'created_by' => $sales->id,
            'proposal_date' => now()->toDateString(),
            'customer' => $customer->name,
            'status' => 'draft',
        ]);

        $proposal->items()->create([
            'product_id' => $product->id,
            'description' => $product->name,
            'quantity' => 1,
            'unit' => 'unit',
            'unit_price' => 100,
            'unit_cost' => 60,
            'discount' => 0,
            'subtotal' => 100,
        ]);
        $proposal->recalculate();

        $this->actingAs($sales)
            ->get(route('sales.proposals.show', $proposal))
            ->assertOk()
            ->assertSee(__('Internal Financials'))
            ->assertSee(__('Gross Margin'));
    }

    public function test_customer_finance_fields_are_hidden_from_non_admin_roles(): void
    {
        $marketing = $this->userWithRole('marketing');
        $sales = $this->userWithRole('sales');
        $finance = $this->userWithRole('finance');

        $customer = Customer::create([
            'name' => 'PT Finance Visibility',
            'pt_group' => 'NTI',
            'billing_address' => 'Jl. Rahasia Finance 123',
            'finance_pic_name' => 'Ibu Finance Rahasia',
            'npwp' => '99.999.999.9-999.999',
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-FIN-'.uniqid(),
            'customer_id' => $customer->id,
            'amount' => 100,
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
        ]);
        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'amount' => 50,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Transfer',
            'proof_file' => 'payments/finance-proof.pdf',
        ]);
        ProjectDocument::create([
            'customer_id' => $customer->id,
            'doc_type' => 'payment',
            'file_name' => 'finance-proof.pdf',
            'file_path' => 'payments/finance-proof.pdf',
            'file_size' => 10,
            'mime_type' => 'application/pdf',
            'uploaded_by' => $finance->id,
            'documentable_type' => Payment::class,
            'documentable_id' => $payment->id,
        ]);

        $this->actingAs($marketing)
            ->get(route('customers.show', $customer))
            ->assertOk()
            ->assertDontSee('Jl. Rahasia Finance 123')
            ->assertDontSee('Ibu Finance Rahasia')
            ->assertDontSee('99.999.999.9-999.999')
            ->assertDontSee(__('File Payment'));

        $this->actingAs($marketing)
            ->get(route('customers.npwp', $customer))
            ->assertForbidden();

        $this->actingAs($sales)
            ->get(route('customers.show', $customer))
            ->assertOk()
            ->assertDontSee('Jl. Rahasia Finance 123')
            ->assertDontSee('Ibu Finance Rahasia')
            ->assertDontSee('99.999.999.9-999.999')
            ->assertDontSee(__('File Payment'));

        $this->actingAs($sales)
            ->get(route('customers.npwp', $customer))
            ->assertForbidden();

        // Finance tetap bisa membuka data penagihan; file NPWP tidak ada
        // pada fixture sehingga controller mengembalikan 404 (bukan 403).
        // Billing & shipping address sudah dihapus dari ringkasan customer
        // (commit style(cus/customers) e69ee835), jadi tidak dicek lagi.
        $this->actingAs($finance)
            ->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee('Ibu Finance Rahasia')
            ->assertSee('99.999.999.9-999.999')
            ->assertSee(__('File Payment'));

        $this->actingAs($finance)
            ->get(route('customers.npwp', $customer))
            ->assertNotFound();
    }
}

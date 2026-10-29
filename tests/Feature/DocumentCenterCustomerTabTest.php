<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\Proposal;
use App\Models\ProposalAttachment;
use App\Models\User;
use App\Models\WorkType;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Tab Dokumen pada Detail Customer: satu daftar terpadu, terbatas pada
 * customer tersebut, tanpa duplikasi file.
 */
class DocumentCenterCustomerTabTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        Storage::fake('private');
    }

    private function user(string ...$roles): User
    {
        $user = User::factory()->create();
        $user->assignRole($roles);

        return $user;
    }

    private function customer(string $name, ?string $pt = null): Customer
    {
        return Customer::create(['name' => $name, 'pt_group' => $pt]);
    }

    private function project(Customer $customer): Project
    {
        $workType = WorkType::firstOrCreate(['name' => 'Instalasi']);

        return Project::withoutEvents(fn () => Project::create([
            'customer_id' => $customer->id,
            'work_type_id' => $workType->id,
            'project_name' => 'Project '.$customer->name,
        ]));
    }

    private function document(Customer $customer, ?Project $project, string $name, array $attributes = []): ProjectDocument
    {
        Storage::disk('private')->put('documents/'.$name, 'isi');

        return ProjectDocument::create(array_merge([
            'customer_id' => $customer->id,
            'project_id' => $project?->id,
            'file_name' => $name,
            'file_path' => 'documents/'.$name,
            'file_size' => 100,
            'mime_type' => 'application/pdf',
            'uploaded_by' => User::first()?->id,
        ], $attributes));
    }

    public function test_customer_tab_lists_documents_from_multiple_sources_once(): void
    {
        $admin = $this->user('super-admin');
        $customer = $this->customer('PT Multi Sumber');
        $project = $this->project($customer);

        $this->document($customer, $project, 'laporan-survey.pdf', ['doc_type' => 'survey']);
        $this->document($customer, $project, 'bast-tertanda.pdf', ['doc_type' => 'bast']);
        $this->document($customer, null, 'dokumen-umum-customer.pdf');

        $invoice = Invoice::create([
            'invoice_number' => 'INV-TAB-1',
            'customer_id' => $customer->id,
            'amount' => 100000,
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
        ]);
        $this->document($customer, null, 'faktur-tab.pdf', [
            'doc_type' => 'invoice',
            'documentable_type' => Invoice::class,
            'documentable_id' => $invoice->id,
        ]);

        $lead = Lead::create(['customer_id' => $customer->id, 'status' => 'cool']);
        $proposal = Proposal::create([
            'lead_id' => $lead->id,
            'proposal_number' => 'QUO-TAB-1',
            'created_by' => $admin->id,
            'proposal_date' => now()->toDateString(),
            'customer' => $customer->name,
            'status' => 'accepted',
            'po_number' => 'CPO-TAB-1',
            'po_document_path' => 'quotations/tab-po.pdf',
        ]);
        Storage::disk('private')->put('quotations/tab-po.pdf', 'po');
        ProposalAttachment::create([
            'proposal_id' => $proposal->id,
            'file_path' => 'quotations/tab-quotation.pdf',
            'original_name' => 'quotation-tab.pdf',
            'mime' => 'application/pdf',
            'size' => 10,
            'uploaded_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get(route('customers.show', $customer))->assertOk();
        $documents = $response->viewData('customerDocuments');

        // 6 file berbeda dari 6 sumber; tidak ada baris ganda.
        $this->assertSame(6, $documents->total());
        $this->assertSame(
            $documents->total(),
            $documents->getCollection()->pluck('name')->unique()->count()
        );

        $html = $response->getContent();
        foreach (['laporan-survey.pdf', 'bast-tertanda.pdf', 'dokumen-umum-customer.pdf', 'faktur-tab.pdf', 'CPO-TAB-1', 'quotation-tab.pdf'] as $name) {
            $this->assertStringContainsString($name, $html);
        }
    }

    public function test_customer_tab_does_not_show_other_customers_documents(): void
    {
        $admin = $this->user('super-admin');
        $mine = $this->customer('PT Punya Saya');
        $other = $this->customer('PT Orang Lain');

        $this->document($mine, null, 'punya-saya.pdf');
        $this->document($other, null, 'punya-orang-lain.pdf');

        $response = $this->actingAs($admin)->get(route('customers.show', $mine))->assertOk();

        $response->assertSee('punya-saya.pdf', false);
        $response->assertDontSee('punya-orang-lain.pdf', false);
        $this->assertSame(1, $response->viewData('customerDocuments')->total());
    }

    public function test_customer_tab_search_and_category_filter_stay_scoped(): void
    {
        $admin = $this->user('super-admin');
        $customer = $this->customer('PT Filter');
        $project = $this->project($customer);

        $this->document($customer, $project, 'survey-bulan-ini.pdf', ['doc_type' => 'survey']);
        $this->document($customer, null, 'dokumen-umum-filter.pdf');

        $response = $this->actingAs($admin)->get(route('customers.show', ['customer' => $customer, 'documents_q' => 'survey-bulan']))->assertOk();

        $names = $response->viewData('customerDocuments')->getCollection()->pluck('name')->all();
        $this->assertContains('survey-bulan-ini.pdf', $names);
        $this->assertNotContains('dokumen-umum-filter.pdf', $names);
        $this->assertSame(1, $response->viewData('customerDocuments')->total());

        $response = $this->actingAs($admin)->get(route('customers.show', ['customer' => $customer, 'documents_category' => 'customer']))->assertOk();

        $names = $response->viewData('customerDocuments')->getCollection()->pluck('name')->all();
        $this->assertContains('dokumen-umum-filter.pdf', $names);
        $this->assertNotContains('survey-bulan-ini.pdf', $names);
    }

    public function test_customer_tab_paginates_with_its_own_page_name(): void
    {
        $admin = $this->user('super-admin');
        $customer = $this->customer('PT Banyak Dokumen');

        for ($i = 0; $i < 25; $i++) {
            $this->document($customer, null, "dokumen-{$i}.pdf");
        }

        $first = $this->actingAs($admin)->get(route('customers.show', $customer))->assertOk();
        $this->assertSame(25, $first->viewData('customerDocuments')->total());
        $this->assertSame(20, $first->viewData('customerDocuments')->count());

        $second = $this->actingAs($admin)->get(route('customers.show', ['customer' => $customer, 'documents_page' => 2]))->assertOk();
        $this->assertSame(5, $second->viewData('customerDocuments')->count());
        $this->assertStringContainsString("tab: 'documents'", $second->getContent());
    }

    public function test_sales_does_not_see_payment_proof_in_customer_tab(): void
    {
        $sales = $this->user('sales');
        $customer = $this->customer('PT Sales Tab');

        $this->document($customer, null, 'dokumen-umum-sales.pdf');

        $invoice = Invoice::create([
            'invoice_number' => 'INV-TAB-PAY',
            'customer_id' => $customer->id,
            'amount' => 100000,
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
        ]);
        $payment = \App\Models\Payment::create([
            'invoice_id' => $invoice->id,
            'amount' => 100000,
            'payment_date' => now()->toDateString(),
            'proof_file' => 'payments/tab-proof.jpg',
        ]);
        Storage::disk('private')->put('payments/tab-proof.jpg', 'proof');
        $this->document($customer, null, 'bukti-bayar-tab.jpg', [
            'doc_type' => 'payment',
            'documentable_type' => \App\Models\Payment::class,
            'documentable_id' => $payment->id,
        ]);

        $this->actingAs($sales)
            ->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee('dokumen-umum-sales.pdf', false)
            ->assertDontSee('bukti-bayar-tab.jpg', false);
    }
}

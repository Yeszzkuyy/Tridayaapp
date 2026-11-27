<?php

namespace Tests\Feature;

use App\Mail\QuotationMail;
use App\Models\Customer;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\Proposal;
use App\Models\User;
use App\Services\QuotationPdfService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class QuotationPdfTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    private function sales(string $name = 'Cici'): User
    {
        $user = User::factory()->create([
            'name' => $name,
            'phone' => '+62 811-1234-1234',
        ]);
        $user->assignRole('sales');

        return $user;
    }

    private function proposal(User $sales, string $pt, array $overrides = []): Proposal
    {
        $customer = Customer::create([
            'name' => 'Telkom '.$pt,
            'pt_group' => $pt,
            'email' => 'reki@telkom.com',
        ]);

        $lead = Lead::create([
            'customer_id' => $customer->id,
            'pt_group' => $pt,
            'segment' => 'vendor',
            'status' => 'hot',
            'assigned_to' => $sales->id,
        ]);

        FollowUp::create([
            'customer_id' => $customer->id,
            'lead_id' => $lead->id,
            'description' => 'Follow up awal.',
            'type' => 'note',
            'created_by' => $sales->id,
        ]);

        $proposal = Proposal::create(array_merge([
            'lead_id' => $lead->id,
            'proposal_number' => $pt.'/001/AS/2026',
            'created_by' => $sales->id,
            'proposal_date' => now(),
            'valid_until' => now()->addWeek(),
            'customer' => 'Telkom',
            'customer_address' => 'Kec. Ciracas, Kota Jakarta Timur',
            'contact_name' => 'Bpk. Reki',
            'contact_email' => 'reki@telkom.com',
            'contact_phone' => '+62 888-8888-888',
            'project_description' => 'Project '.$pt,
            'payment_terms_text' => 'TT in Advance',
            'delivery_terms' => '3-5 Days',
            'letterhead_pt' => $pt,
            'subtotal' => 1000000,
            'discount' => 0,
            'tax' => 110000,
            'tax_rate' => 11,
            'grand_total' => 1110000,
            'status' => 'ready',
        ], $overrides));

        $proposal->items()->create([
            'description' => "Product for {$pt}\n- First specification\n- Second specification",
            'scope_of_supply' => $pt.'-SKU-001',
            'lead_time' => 'Ready Stock',
            'quantity' => 2,
            'unit' => 'UNIT',
            'unit_price' => 500000,
            'discount' => 0,
            'subtotal' => 1000000,
        ]);

        return $proposal->fresh();
    }

    public function test_each_pt_uses_its_own_official_pdf_template(): void
    {
        @mkdir('/tmp/pdfs', 0775, true);

        $service = app(QuotationPdfService::class);

        foreach (['NTI', 'MGK', 'TPS', 'WANI'] as $pt) {
            $sales = $this->sales();
            $proposal = $this->proposal($sales, $pt);

            $this->assertSame(strtolower($pt), $service->templateKey($proposal));

            $path = $service->generate($proposal);
            $this->assertTrue(Storage::disk('private')->exists($path));

            $pdf = Storage::disk('private')->get($path);
            $this->assertStringStartsWith('%PDF', $pdf);

            copy(Storage::disk('private')->path($path), '/tmp/pdfs/'.$pt.'.pdf');
        }
    }

    public function test_preview_renders_official_template_and_sales_contact(): void
    {
        $sales = $this->sales('Cici Pertiwi');
        $proposal = $this->proposal($sales, 'NTI');

        $response = $this->actingAs($sales)
            ->get(route('sales.proposals.preview', $proposal))
            ->assertOk();

        $response->assertSee('NUSANTARA TRIDAYA INOVASI, PT');
        $response->assertSee('Cici Pertiwi');
        $response->assertSee($sales->email);
        $response->assertSee('General Terms and Conditions');
        $response->assertSee('BRI - KC JAKARTA RAWAMANGUN');
    }

    public function test_sales_profile_stores_phone_and_digital_signature(): void
    {
        Storage::fake('public');

        $sales = $this->sales();

        $this->actingAs($sales)->patch(route('profile.update'), [
            'name' => 'Cici Pertiwi',
            'email' => $sales->email,
            'phone' => '0812-3456-7890',
            'signature' => UploadedFile::fake()->image('ttd.png', 320, 160),
        ])->assertRedirect();

        $sales->refresh();

        $this->assertSame('0812-3456-7890', $sales->phone);
        $this->assertNotNull($sales->signature_path);
        $this->assertStringStartsWith('signatures/', $sales->signature_path);
        $this->assertTrue(Storage::disk('public')->exists($sales->signature_path));
    }

    public function test_send_email_attaches_official_pdf_and_uses_revision_number(): void
    {
        Mail::fake();

        $sales = $this->sales();
        $proposal = $this->proposal($sales, 'MGK', [
            'proposal_number' => '001R2/MGK-AS/2026',
            'revision_number' => 2,
        ]);

        $this->actingAs($sales)
            ->post(route('sales.proposals.send-email', $proposal), ['email' => 'reki@telkom.com'])
            ->assertRedirect();

        Mail::assertSent(QuotationMail::class, function (QuotationMail $mail) {
            return $mail->hasTo('reki@telkom.com')
                && str_contains($mail->mailSubject, '001R2/MGK-AS/2026')
                && str_contains($mail->mailSubject, 'PT Mega Gada Kencana')
                && count($mail->attachments()) === 1
                && Storage::disk('private')->exists($mail->pdfPath);
        });

        $this->assertSame('sent', $proposal->fresh()->status);
        $this->assertSame('email', $proposal->fresh()->sent_channel);
    }
}

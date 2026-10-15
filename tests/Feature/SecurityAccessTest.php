<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecurityAccessTest extends TestCase
{
    use RefreshDatabase;

    private function loginAs(string $role): User
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function makeProjectDocument(User $user, string $filename = 'boq.pdf'): ProjectDocument
    {
        $this->actingAs($user);

        $customer = Customer::create(['name' => 'PT Security']);
        $workType = \App\Models\WorkType::create(['name' => 'Instalasi']);
        $project = Project::create([
            'customer_id' => $customer->id,
            'work_type_id' => $workType->id,
            'project_name' => 'Project Security',
        ]);

        Storage::fake('private');
        Storage::disk('private')->put('documents/1/'.$filename, 'isi rahasia');

        return $project->documents()->create([
            'file_name' => $filename,
            'file_path' => 'documents/1/'.$filename,
            'mime_type' => 'application/pdf',
            'uploaded_by' => User::first()->id,
        ]);
    }

    /**
     * Dokumen project hanya boleh diakses divisi yang punya akses project
     * (view-technician/manage-technician/view-sales). Marketing dilarang.
     */
    public function test_marketing_cannot_download_project_document(): void
    {
        $u = $this->loginAs('marketing');
        $doc = $this->makeProjectDocument($u);

        $this->actingAs($u)->get(route('project-documents.download', $doc))->assertForbidden();
        $this->actingAs($u)->get(route('project-documents.preview', $doc))->assertForbidden();
    }

    /**
     * Teknisi boleh melihat/mengunduh dokumen project (read), sesuai keputusan
     * bisnis: sesama teknisi boleh lihat project yang bukan miliknya.
     */
    public function test_teknisi_can_download_project_document(): void
    {
        $u = $this->loginAs('technician');
        $doc = $this->makeProjectDocument($u);

        $this->actingAs($u)->get(route('project-documents.download', $doc))->assertOk();
    }

    /**
     * Sales boleh download (read-only), tapi dilarang upload/hapus dokumen.
     */
    public function test_sales_can_download_but_not_modify_project_document(): void
    {
        $u = $this->loginAs('sales');
        $doc = $this->makeProjectDocument($u);

        $this->actingAs($u)->get(route('project-documents.download', $doc))->assertOk();
        $this->actingAs($u)->delete(route('project-documents.destroy', $doc))->assertForbidden();
    }

    /**
     * Teknisi tetap bisa upload dokumen ke project (fitur existing tidak rusak).
     */
    public function test_teknisi_can_upload_project_document(): void
    {
        $u = $this->loginAs('technician');
        $this->actingAs($u);
        $customer = Customer::create(['name' => 'PT Upload']);
        $workType = \App\Models\WorkType::create(['name' => 'Instalasi']);
        $project = Project::create([
            'customer_id' => $customer->id,
            'work_type_id' => $workType->id,
            'project_name' => 'Project Upload',
        ]);
        Storage::fake('private');

        $this->actingAs($u)->post(route('project-documents.store', $project), [
            'file' => UploadedFile::fake()->create('laporan.xlsx', 50, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
        ])->assertRedirect();

        $this->assertSame(1, $project->fresh()->documents()->count());
        $this->assertSame('laporan.xlsx', $project->fresh()->documents()->first()->file_name);
    }

    /**
     * Bukti transfer pembayaran hanya untuk divisi admin.
     */
    public function test_payment_proof_admin_can_view_marketing_forbidden(): void
    {
        $customer = Customer::create(['name' => 'PT Bayar']);
        $invoice = \App\Models\Invoice::create([
            'invoice_number' => 'INV-001',
            'customer_id' => $customer->id,
            'amount' => 100000,
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
        ]);
        Storage::fake('private');
        Storage::disk('private')->put('payments/bukti.jpg', 'gambar');

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'amount' => 100000,
            'payment_date' => now()->toDateString(),
            'proof_file' => 'payments/bukti.jpg',
        ]);

        $admin = $this->loginAs('admin');
        $this->actingAs($admin)->get(route('admin.payments.proof', $payment))->assertOk();

        $marketing = $this->loginAs('marketing');
        $this->actingAs($marketing)->get(route('admin.payments.proof', $payment))->assertForbidden();
    }

    /**
     * H5: Bukti transfer hanya manage-admin. Sales (view-admin) ditolak
     * di proof, tapi tetap boleh lihat halaman show (read-only recap).
     */
    public function test_payment_proof_sales_forbidden_but_show_allowed(): void
    {
        $customer = Customer::create(['name' => 'PT Bukti Sales']);
        $invoice = \App\Models\Invoice::create([
            'invoice_number' => 'INV-003',
            'customer_id' => $customer->id,
            'amount' => 75000,
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
        ]);
        Storage::fake('private');
        Storage::disk('private')->put('payments/bukti3.jpg', "\xFF\xD8\xFF fake-jpeg");

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'amount' => 75000,
            'payment_date' => now()->toDateString(),
            'proof_file' => 'payments/bukti3.jpg',
        ]);

        $sales = $this->loginAs('sales');
        $this->actingAs($sales)->get(route('admin.payments.proof', $payment))->assertForbidden();
        $this->actingAs($sales)->get(route('admin.payments.show', $payment))->assertOk();
    }

    /**
     * Super admin tetap melewati semua policy (Gate::before).
     */
    public function test_super_admin_can_download_any_project_document(): void
    {
        $u = $this->loginAs('super-admin');
        $doc = $this->makeProjectDocument($u);

        $this->actingAs($u)->get(route('project-documents.download', $doc))->assertOk();
    }

    /**
     * File berbahaya (script/executable) ditolak walau ekstensi valid lainnya.
     */
    public function test_dangerous_file_extensions_rejected(): void
    {
        $u = $this->loginAs('technician');
        $this->actingAs($u);
        $customer = Customer::create(['name' => 'PT Bahaya']);
        $workType = \App\Models\WorkType::create(['name' => 'Instalasi']);
        $project = Project::create([
            'customer_id' => $customer->id,
            'work_type_id' => $workType->id,
            'project_name' => 'Project Bahaya',
        ]);
        Storage::fake('private');

        foreach (['shell.php', 'exploit.phtml', 'payload.phar', 'virus.exe', 'run.sh'] as $name) {
            $this->actingAs($u)->post(route('project-documents.store', $project), [
                'file' => UploadedFile::fake()->create($name, 50),
            ])->assertSessionHasErrors('file', "file {$name} harus ditolak");

            $this->assertSame(0, $project->fresh()->documents()->count(), "file {$name} tidak boleh tersimpan");
        }
    }

    /**
     * Ekstensi di luar daftar izin ditolak.
     */
    public function test_unallowed_extension_rejected(): void
    {
        $u = $this->loginAs('technician');
        $this->actingAs($u);
        $customer = Customer::create(['name' => 'PT Batas']);
        $workType = \App\Models\WorkType::create(['name' => 'Instalasi']);
        $project = Project::create([
            'customer_id' => $customer->id,
            'work_type_id' => $workType->id,
            'project_name' => 'Project Batas',
        ]);
        Storage::fake('private');

        $this->actingAs($u)->post(route('project-documents.store', $project), [
            'file' => UploadedFile::fake()->create('data.mp4', 50),
        ])->assertSessionHasErrors('file');

        $this->assertSame(0, $project->fresh()->documents()->count());
    }

    /**
     * File yang sah tetap diterima (regresi).
     */
    public function test_valid_document_still_accepted(): void
    {
        $u = $this->loginAs('technician');
        $this->actingAs($u);
        $customer = Customer::create(['name' => 'PT Valid']);
        $workType = \App\Models\WorkType::create(['name' => 'Instalasi']);
        $project = Project::create([
            'customer_id' => $customer->id,
            'work_type_id' => $workType->id,
            'project_name' => 'Project Valid',
        ]);
        Storage::fake('private');

        $this->actingAs($u)->post(route('project-documents.store', $project), [
            'file' => UploadedFile::fake()->create('boq.pdf', 50, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, $project->fresh()->documents()->count());
    }

    /**
     * H1: MIME di database yang dipalsukan (text/html) tidak dipercaya —
     * preview menyajikan MIME hasil deteksi isi file + nosniff.
     */
    public function test_preview_ignores_spoofed_mime_in_database(): void
    {
        $u = $this->loginAs('technician');
        $this->actingAs($u);
        Storage::fake('private');
        Storage::disk('private')->put('documents/1/asli.pdf', "%PDF-1.4 spoofed\n");

        $customer = Customer::create(['name' => 'PT Spoof']);
        $workType = \App\Models\WorkType::create(['name' => 'Instalasi']);
        $project = Project::create([
            'customer_id' => $customer->id,
            'work_type_id' => $workType->id,
            'project_name' => 'Project Spoof',
        ]);
        $doc = $project->documents()->create([
            'file_name' => 'asli.pdf',
            'file_path' => 'documents/1/asli.pdf',
            'mime_type' => 'text/html',
            'uploaded_by' => $u->id,
        ]);

        $response = $this->actingAs($u)->get(route('project-documents.preview', $doc));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    /**
     * H1: SVG tidak boleh inline (bisa berisi script) — dipaksa download.
     */
    public function test_svg_preview_forces_download(): void
    {
        $u = $this->loginAs('technician');
        $this->actingAs($u);
        Storage::fake('private');
        Storage::disk('private')->put('documents/1/gambar.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');

        $customer = Customer::create(['name' => 'PT Svg']);
        $workType = \App\Models\WorkType::create(['name' => 'Instalasi']);
        $project = Project::create([
            'customer_id' => $customer->id,
            'work_type_id' => $workType->id,
            'project_name' => 'Project Svg',
        ]);
        $doc = $project->documents()->create([
            'file_name' => 'gambar.svg',
            'file_path' => 'documents/1/gambar.svg',
            'mime_type' => 'image/svg+xml',
            'uploaded_by' => $u->id,
        ]);

        $response = $this->actingAs($u)->get(route('project-documents.preview', $doc));

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringStartsWith('attachment', $response->headers->get('Content-Disposition'));
    }

    /**
     * H1: Lampiran lead non-media (xlsx) tidak lagi inline — dipaksa download + nosniff.
     */
    public function test_lead_office_attachment_forces_download(): void
    {
        $u = $this->loginAs('super-admin');
        Storage::fake('private');
        Storage::disk('private')->put('leads/1/data.xlsx', 'bukan-zip-asli');

        $customer = Customer::create(['name' => 'PT Lead']);
        $lead = Lead::create(['customer_id' => $customer->id, 'status' => 'cool']);
        $doc = $lead->documents()->create([
            'file_name' => 'data.xlsx',
            'file_path' => 'leads/1/data.xlsx',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);

        $response = $this->actingAs($u)->get(route('leads.attachments.show', [$lead, $doc]));

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringStartsWith('attachment', $response->headers->get('Content-Disposition'));
    }

    /**
     * H1: Bukti pembayaran disajikan dengan nosniff.
     */
    public function test_payment_proof_serves_nosniff(): void
    {
        $customer = Customer::create(['name' => 'PT Bukti']);
        $invoice = \App\Models\Invoice::create([
            'invoice_number' => 'INV-002',
            'customer_id' => $customer->id,
            'amount' => 50000,
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
        ]);
        Storage::fake('private');
        Storage::disk('private')->put('payments/bukti2.jpg', "\xFF\xD8\xFF fake-jpeg");

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'amount' => 50000,
            'payment_date' => now()->toDateString(),
            'proof_file' => 'payments/bukti2.jpg',
        ]);

        $admin = $this->loginAs('admin');
        $response = $this->actingAs($admin)->get(route('admin.payments.proof', $payment));

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    /**
     * H2: Route serve file framework (/storage/{path}) tidak terdaftar —
     * tidak ada akses langsung ke storage/app/private tanpa auth.
     */
    public function test_storage_serve_route_disabled(): void
    {
        $this->get('/storage/leads/1/apapun.pdf')->assertNotFound();
    }

    /**
     * Setiap respons web membawa header keamanan dasar.
     */
    public function test_security_headers_present(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }
}

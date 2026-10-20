<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\PriceRequest;
use App\Models\TechnicalRequest;
use App\Models\User;
use App\Notifications\PriceRequestCompletedNotification;
use App\Notifications\PriceRequestCreatedNotification;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Request Harga = sistem terpisah dari Request Tim Engineer:
 * sales kirim quotation + customer + notes, engineer membalas dengan file.
 */
class PriceRequestTest extends TestCase
{
    use RefreshDatabase;

    private bool $seeded = false;

    private function userWithRole(string $role): User
    {
        if (! $this->seeded) {
            $this->seed(RoleAndPermissionSeeder::class);
            $this->seeded = true;
        }
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function makeCustomer(?string $name = null): Customer
    {
        return Customer::create(['name' => $name ?? 'PT Price '.uniqid()]);
    }

    private function makePriceRequest(User $sales, array $overrides = []): PriceRequest
    {
        return PriceRequest::create(array_merge([
            'customer_id' => $this->makeCustomer()->id,
            'requested_by' => $sales->id,
            'quotation_path' => 'price-requests/1/quotation.pdf',
            'status' => 'waiting_lead',
        ], $overrides));
    }

    public function test_sales_creates_price_request_with_quotation_and_notifies_lead_technician(): void
    {
        Storage::fake('private');
        Notification::fake();

        $sales = $this->userWithRole('sales');
        $leadTech = $this->userWithRole('lead-technician');
        $customer = $this->makeCustomer('PT Indoplas Karya');

        $this->actingAs($sales)->post(route('sales.price-requests.store'), [
            'customer_id' => $customer->id,
            'notes' => 'Mohon dihitung ulang untuk 8 titik.',
            'quotation' => UploadedFile::fake()->create('quotation-indoplas.pdf', 120, 'application/pdf'),
        ])->assertRedirect();

        $request = PriceRequest::firstOrFail();
        $this->assertSame($customer->id, $request->customer_id);
        $this->assertSame($sales->id, $request->requested_by);
        $this->assertSame('waiting_lead', $request->status);
        $this->assertNotNull($request->quotation_path);
        Storage::disk('private')->assertExists($request->quotation_path);

        Notification::assertSentTo($leadTech, PriceRequestCreatedNotification::class);
    }

    public function test_quotation_file_is_required(): void
    {
        $sales = $this->userWithRole('sales');
        $customer = $this->makeCustomer();

        $this->actingAs($sales)->post(route('sales.price-requests.store'), [
            'customer_id' => $customer->id,
        ])->assertSessionHasErrors('quotation');

        $this->assertSame(0, PriceRequest::count());
    }

    public function test_price_form_has_customer_quotation_and_notes(): void
    {
        $sales = $this->userWithRole('sales');

        $html = $this->actingAs($sales)->get(route('sales.price-requests.create'))
            ->assertOk()->getContent();

        $this->assertStringContainsString('name="customer_id"', $html);
        $this->assertStringContainsString('name="quotation"', $html);
        $this->assertStringContainsString('name="notes"', $html);
        // Bukan form Engineer: tidak ada lead/tipe/lokasi.
        $this->assertStringNotContainsString('name="lead_id"', $html);
        $this->assertStringNotContainsString('name="request_type"', $html);
    }

    public function test_sales_only_sees_own_price_requests_in_hub(): void
    {
        $mine = $this->userWithRole('sales');
        $other = $this->userWithRole('sales');
        $mineCustomer = $this->makeCustomer('PT Punyaku');
        $otherCustomer = $this->makeCustomer('PT Oranglain');
        $this->makePriceRequest($mine, ['customer_id' => $mineCustomer->id]);
        $this->makePriceRequest($other, ['customer_id' => $otherCustomer->id]);

        $html = $this->actingAs($mine)->get(route('sales.requests.index', ['tab' => 'price']))
            ->assertOk()->getContent();

        $this->assertStringContainsString('PT Punyaku', $html);
        $this->assertStringNotContainsString('PT Oranglain', $html);
    }

    public function test_management_sees_all_price_requests(): void
    {
        $mgmt = $this->userWithRole('management');
        $sales = $this->userWithRole('sales');
        $this->makePriceRequest($sales, ['customer_id' => $this->makeCustomer('PT Management Lihat')->id]);

        $html = $this->actingAs($mgmt)->get(route('sales.requests.index', ['tab' => 'price']))
            ->assertOk()->getContent();

        $this->assertStringContainsString('PT Management Lihat', $html);
    }

    public function test_technician_takes_and_replies_with_file(): void
    {
        Storage::fake('private');
        Notification::fake();

        $sales = $this->userWithRole('sales');
        $leadTech = $this->userWithRole('lead-technician');
        $request = $this->makePriceRequest($sales);

        $this->actingAs($leadTech)
            ->post(route('sales.price-requests.take', $request))
            ->assertRedirect();

        $request->refresh();
        $this->assertSame('in_progress', $request->status);
        $this->assertSame($leadTech->id, $request->assigned_to);

        $this->actingAs($leadTech)->post(route('sales.price-requests.reply.store', $request), [
            'reply' => UploadedFile::fake()->create('harga-instalasi.xlsx', 90, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
            'reply_notes' => 'Harga sudah termasuk ongkir.',
        ])->assertRedirect();

        $request->refresh();
        $this->assertSame('completed', $request->status);
        $this->assertNotNull($request->reply_path);
        $this->assertNotNull($request->completed_at);
        Storage::disk('private')->assertExists($request->reply_path);

        Notification::assertSentTo($sales, PriceRequestCompletedNotification::class);
    }

    public function test_technician_can_reject_request(): void
    {
        Notification::fake();
        $sales = $this->userWithRole('sales');
        $leadTech = $this->userWithRole('lead-technician');
        $request = $this->makePriceRequest($sales);

        $this->actingAs($leadTech)
            ->post(route('sales.price-requests.reject', $request))
            ->assertRedirect();

        $this->assertSame('rejected', $request->fresh()->status);
    }

    public function test_sales_cannot_take_or_reply(): void
    {
        $sales = $this->userWithRole('sales');
        $request = $this->makePriceRequest($sales);

        $this->actingAs($sales)->post(route('sales.price-requests.take', $request))->assertForbidden();
        $this->actingAs($sales)->post(route('sales.price-requests.reject', $request))->assertForbidden();
    }

    public function test_other_sales_cannot_view_foreign_price_request(): void
    {
        $sales = $this->userWithRole('sales');
        $other = $this->userWithRole('sales');
        $request = $this->makePriceRequest($sales);

        $this->actingAs($other)
            ->get(route('sales.price-requests.show', $request))
            ->assertForbidden();
    }

    public function test_quotation_download_is_authorized(): void
    {
        Storage::fake('private');
        $sales = $this->userWithRole('sales');
        $other = $this->userWithRole('sales');
        $request = $this->makePriceRequest($sales);
        Storage::disk('private')->put($request->quotation_path, 'pdf-bytes');

        $this->actingAs($sales)
            ->get(route('sales.price-requests.quotation', $request))
            ->assertOk();

        $this->actingAs($other)
            ->get(route('sales.price-requests.quotation', $request))
            ->assertForbidden();
    }

    public function test_teknisi_inbox_lists_both_request_systems(): void
    {
        $leadTech = $this->userWithRole('lead-technician');
        $sales = $this->userWithRole('sales');
        $price = $this->makePriceRequest($sales, ['customer_id' => $this->makeCustomer('PT Inbox Harga')->id]);

        $lead = Lead::create([
            'customer_id' => $this->makeCustomer('PT Inbox Engineer')->id,
            'assigned_to' => $sales->id,
            'status' => 'warm',
        ]);
        TechnicalRequest::create([
            'lead_id' => $lead->id,
            'requested_by' => $sales->id,
            'request_type' => 'survey',
            'title' => 'Survey Inbox',
            'status' => 'waiting_lead',
        ]);

        $this->actingAs($leadTech)->get(route('teknisi.requests.index'))
            ->assertOk()
            ->assertSee('PT Inbox Harga')
            ->assertDontSee('Survey Inbox');

        $this->actingAs($leadTech)->get(route('teknisi.requests.index', ['tab' => 'engineer']))
            ->assertOk()
            ->assertSee('Survey Inbox')
            ->assertDontSee('PT Inbox Harga');
    }

    public function test_teknisi_inbox_is_forbidden_for_sales(): void
    {
        $sales = $this->userWithRole('sales');

        $this->actingAs($sales)->get(route('teknisi.requests.index'))->assertForbidden();
    }

    public function test_technician_menu_shows_request_sales(): void
    {
        $leadTech = $this->userWithRole('lead-technician');

        $html = $this->actingAs($leadTech)->get(route('teknisi.requests.index'))
            ->assertOk()->getContent();

        $this->assertStringContainsString(route('teknisi.requests.index'), $html);
        $this->assertStringContainsString('Request Sales', $html);
    }

    public function test_technician_can_create_price_request_with_same_form(): void
    {
        Storage::fake('private');
        Notification::fake();

        $tech = $this->userWithRole('technician');
        $leadTech = $this->userWithRole('lead-technician');
        $customer = $this->makeCustomer('PT Dibuat Teknisi');

        // Form sama persis seperti sales: customer + quotation + notes.
        $html = $this->actingAs($tech)->get(route('sales.price-requests.create'))
            ->assertOk()->getContent();
        $this->assertStringContainsString('name="customer_id"', $html);
        $this->assertStringContainsString('name="quotation"', $html);
        $this->assertStringContainsString('name="notes"', $html);

        $this->actingAs($tech)->post(route('sales.price-requests.store'), [
            'customer_id' => $customer->id,
            'notes' => 'Mohon hitung dari sisi teknisi.',
            'quotation' => UploadedFile::fake()->create('quotation-teknisi.pdf', 120, 'application/pdf'),
        ])->assertRedirect();

        $request = PriceRequest::firstOrFail();
        $this->assertSame($customer->id, $request->customer_id);
        $this->assertSame($tech->id, $request->requested_by);
        $this->assertSame('waiting_lead', $request->status);
        Storage::disk('private')->assertExists($request->quotation_path);

        Notification::assertSentTo($leadTech, PriceRequestCreatedNotification::class);

        // Detail bisa dibuka teknisi pembuat (terhubung satu tabel).
        $detailHtml = $this->actingAs($tech)->get(route('sales.price-requests.show', $request))
            ->assertOk()->getContent();
        $this->assertStringContainsString(__('Request details'), $detailHtml);
        $this->assertStringContainsString($tech->name, $detailHtml);
        $this->assertStringContainsString(strtoupper(substr($tech->name, 0, 1)), $detailHtml);
        $this->assertStringContainsString(__('No engineering reply has been submitted yet.'), $detailHtml);
    }

    public function test_lead_reviews_and_assigns_technician(): void
    {
        Storage::fake('private');
        Notification::fake();

        $sales = $this->userWithRole('sales');
        $leadTech = $this->userWithRole('lead-technician');
        $tech = $this->userWithRole('technician');
        $request = $this->makePriceRequest($sales);

        // Lead menerima request.
        $this->actingAs($leadTech)
            ->post(route('sales.price-requests.review', $request), ['decision' => 'accepted'])
            ->assertRedirect();
        $this->assertSame('accepted', $request->fresh()->status);

        // Review ulang dari status accepted ditolak.
        $this->actingAs($leadTech)
            ->post(route('sales.price-requests.review', $request), ['decision' => 'accepted'])
            ->assertStatus(422);

        // Lead mengatur siapa teknisi penghandle.
        $this->actingAs($leadTech)
            ->post(route('sales.price-requests.assign', $request), ['technician_id' => $tech->id])
            ->assertRedirect();

        $request->refresh();
        $this->assertSame('assigned', $request->status);
        $this->assertSame($tech->id, $request->assigned_to);

        // Teknisi yang di-assign membalas dengan file → selesai.
        $this->actingAs($leadTech)->post(route('sales.price-requests.reply.store', $request), [
            'reply' => UploadedFile::fake()->create('harga-final.pdf', 90, 'application/pdf'),
            'reply_notes' => 'Sudah dihitung.',
        ])->assertRedirect();

        $this->assertSame('completed', $request->fresh()->status);
        Notification::assertSentTo($sales, PriceRequestCompletedNotification::class);
    }

    public function test_assign_requires_technician_role(): void
    {
        $sales = $this->userWithRole('sales');
        $leadTech = $this->userWithRole('lead-technician');
        $request = $this->makePriceRequest($sales);

        $this->actingAs($leadTech)
            ->post(route('sales.price-requests.assign', $request), ['technician_id' => $sales->id])
            ->assertStatus(422);

        $this->assertSame('waiting_lead', $request->fresh()->status);
    }

    public function test_teknisi_price_tab_has_same_new_request_entry(): void
    {
        $leadTech = $this->userWithRole('lead-technician');

        $html = $this->actingAs($leadTech)->get(route('teknisi.requests.index', ['tab' => 'price']))
            ->assertOk()->getContent();

        // Sama persis dengan hub sales: ada pintu buat request + aksi view.
        $this->assertStringContainsString(route('sales.price-requests.create'), $html);
        $this->assertStringContainsString(__('New Request'), $html);
    }

    public function test_notification_center_renders_price_request_cards(): void
    {
        $sales = $this->userWithRole('sales');
        $leadTech = $this->userWithRole('lead-technician');
        $request = $this->makePriceRequest($sales, ['customer_id' => $this->makeCustomer('PT Kartu Notif')->id]);

        $leadTech->notify(new PriceRequestCreatedNotification($request));

        $items = collect(\App\Http\Controllers\NotificationController::itemsFor($leadTech))->keyBy('type');

        $this->assertArrayHasKey('price_request_created', $items);
        $this->assertSame(__('Price Request'), $items['price_request_created']['app']);
        $this->assertSame(__('New price request'), $items['price_request_created']['title']);
        $this->assertSame('PT Kartu Notif', $items['price_request_created']['body']);
        $this->assertSame(route('teknisi.price-requests.show', $request, false), $items['price_request_created']['url']);
    }

    public function test_created_notification_opens_teknisi_request_sales_for_lead_technician(): void
    {
        $sales = $this->userWithRole('sales');
        $leadTech = $this->userWithRole('lead-technician');
        $request = $this->makePriceRequest($sales);

        $leadTech->notify(new PriceRequestCreatedNotification($request));
        $items = collect(\App\Http\Controllers\NotificationController::itemsFor($leadTech))->keyBy('type');

        $this->assertSame(
            route('teknisi.price-requests.show', $request, false),
            $items['price_request_created']['url']
        );
    }

    public function test_reply_notification_opens_sales_view_for_requester(): void
    {
        $sales = $this->userWithRole('sales');
        $request = $this->makePriceRequest($sales);

        $sales->notify(new PriceRequestCompletedNotification($request));
        $items = collect(\App\Http\Controllers\NotificationController::itemsFor($sales))->keyBy('type');

        $this->assertSame(
            route('sales.price-requests.show', $request, false),
            $items['price_request_completed']['url']
        );
    }

    public function test_teknisi_detail_route_renders_with_teknisi_menu(): void
    {
        $sales = $this->userWithRole('sales');
        $leadTech = $this->userWithRole('lead-technician');
        $request = $this->makePriceRequest($sales);

        $html = $this->actingAs($leadTech)
            ->get(route('teknisi.price-requests.show', $request))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(route('teknisi.requests.index'), $html);
        $this->assertStringContainsString('aria-current="page"', $html);
    }

    public function test_creator_is_not_notified_about_own_price_request(): void
    {
        Storage::fake('private');
        Notification::fake();

        $admin = $this->userWithRole('super-admin');
        $admin->assignRole('lead-technician');
        $otherTech = $this->userWithRole('lead-technician');
        $customer = $this->makeCustomer();

        $this->actingAs($admin)->post(route('sales.price-requests.store'), [
            'customer_id' => $customer->id,
            'quotation' => UploadedFile::fake()->create('quotation.pdf', 20, 'application/pdf'),
        ])->assertRedirect();

        Notification::assertNotSentTo($admin, PriceRequestCreatedNotification::class);
        Notification::assertSentTo($otherTech, PriceRequestCreatedNotification::class);
    }

    public function test_responder_is_not_notified_for_own_action_on_price_request(): void
    {
        Storage::fake('private');
        Notification::fake();

        $admin = $this->userWithRole('super-admin');
        $request = $this->makePriceRequest($admin);

        $this->actingAs($admin)
            ->post(route('sales.price-requests.take', $request))
            ->assertRedirect();

        Notification::assertNotSentTo($admin, \App\Notifications\PriceRequestReviewedNotification::class);

        $this->actingAs($admin)->post(route('sales.price-requests.reply.store', $request), [
            'reply' => UploadedFile::fake()->create('harga.pdf', 20, 'application/pdf'),
        ])->assertRedirect();

        Notification::assertNotSentTo($admin, PriceRequestCompletedNotification::class);
    }
}

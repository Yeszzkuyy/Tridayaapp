<?php

namespace Tests\Feature;

use App\Ai\Agents\WhatsappSalesBot;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\User;
use App\Models\WhatsappAccount;
use App\Models\WhatsappConversationPreference;
use App\Models\WhatsappMessage;
use App\Services\WhatsappBot;
use App\Services\WhatsappGateway;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppCenterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    private function marketingUser(?int $accountId = null)
    {
        $user = User::factory()->create();
        $user->assignRole('marketing');

        if ($accountId) {
            WhatsappAccount::whereKey($accountId)->update(['assigned_to' => $user->id]);
        }

        return $user;
    }

    private function makeAccount(string $code = 'wa_nti', ?string $gatewayType = null): WhatsappAccount
    {
        return WhatsappAccount::create([
            'name' => "WA {$code}",
            'phone_number' => '6281111111101',
            'account_code' => $code,
            'gateway_type' => $gatewayType ?? ($code === 'wa_wani' ? WhatsappAccount::GATEWAY_META : WhatsappAccount::GATEWAY_GREEN),
            'assigned_to' => null,
            'is_active' => true,
        ]);
    }

    public function test_webhook_stores_inbound_and_dedupes(): void
    {
        $account = $this->makeAccount();

        $payload = [
            'account_code' => 'wa_nti',
            'sender_number' => '6281234567890',
            'sender_name' => 'Rina',
            'message_body' => 'Halo, minta penawaran dong',
            'wa_message_id' => 'wa_msg_1',
        ];

        $this->postJson('/api/whatsapp/webhook', $payload)->assertOk();
        $this->postJson('/api/whatsapp/webhook', $payload)->assertOk();

        $this->assertSame(1, WhatsappMessage::count());
        $this->assertDatabaseHas('whatsapp_messages', [
            'whatsapp_account_id' => $account->id,
            'direction' => 'inbound',
        ]);
    }

    public function test_marketing_user_can_reply_and_convert_to_lead(): void
    {
        $account = $this->makeAccount();
        $user = $this->marketingUser($account->id);

        WhatsappMessage::create([
            'whatsapp_account_id' => $account->id,
            'sender_number' => '6281234567890',
            'sender_name' => 'Rina Putri',
            'message_body' => 'Butuh internet kantor',
            'direction' => 'inbound',
        ]);

        $this->actingAs($user)
            ->post(route('whatsapp-center.reply', [$account, '6281234567890']), ['message_body' => 'Baik, saya bantu'])
            ->assertOk();

        $this->assertDatabaseHas('whatsapp_messages', ['direction' => 'outbound', 'message_body' => 'Baik, saya bantu']);

        $this->actingAs($user)
            ->post(route('whatsapp-center.convert', [$account, '6281234567890']), [
                'customer_name' => 'Rina Putri',
                'segment' => 'end_user',
                'kebutuhan' => 'Internet kantor',
            ])
            ->assertOk()
            ->assertJsonPath('lead_id', Lead::first()->id);

        $this->assertDatabaseHas('customers', ['whatsapp' => '6281234567890']);
        $this->assertDatabaseHas('leads', [
            'pt_group' => 'NTI',
            'source' => 'whatsapp',
            'whatsapp_account_id' => $account->id,
        ]);
        $this->assertNotNull(WhatsappMessage::first()->lead_id);
    }

    public function test_convert_stores_customer_address(): void
    {
        $account = $this->makeAccount();
        $user = $this->marketingUser($account->id);

        WhatsappMessage::create([
            'whatsapp_account_id' => $account->id,
            'sender_number' => '6281234567890',
            'sender_name' => 'Rina Putri',
            'message_body' => 'Butuh internet kantor',
            'direction' => 'inbound',
        ]);

        $this->actingAs($user)
            ->post(route('whatsapp-center.convert', [$account, '6281234567890']), [
                'customer_name' => 'Rina Putri',
                'customer_address' => 'Jl. Merdeka No. 10, Bandung',
                'segment' => 'end_user',
            ])
            ->assertOk();

        $this->assertDatabaseHas('customers', [
            'whatsapp' => '6281234567890',
            'address' => 'Jl. Merdeka No. 10, Bandung',
        ]);
    }

    public function test_convert_fills_empty_address_on_existing_customer(): void
    {
        $account = $this->makeAccount();
        $user = $this->marketingUser($account->id);

        $customer = Customer::create(['name' => 'PT Lama', 'whatsapp' => '081234567890']);
        WhatsappMessage::create([
            'whatsapp_account_id' => $account->id,
            'sender_number' => '6281234567890',
            'sender_name' => 'Pak Lama',
            'message_body' => 'Mau perpanjang',
            'direction' => 'inbound',
        ]);

        $this->actingAs($user)
            ->post(route('whatsapp-center.convert', [$account, '6281234567890']), [
                'customer_name' => 'Pak Lama',
                'customer_address' => 'Jl. Lama No. 1',
                'segment' => 'vendor',
            ])
            ->assertOk();

        $this->assertSame(1, Customer::count());
        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'address' => 'Jl. Lama No. 1',
        ]);
    }

    public function test_convert_does_not_overwrite_existing_address(): void
    {
        $account = $this->makeAccount();
        $user = $this->marketingUser($account->id);

        $customer = Customer::create([
            'name' => 'PT Punya Alamat',
            'whatsapp' => '081234567890',
            'address' => 'Alamat lama',
        ]);
        WhatsappMessage::create([
            'whatsapp_account_id' => $account->id,
            'sender_number' => '6281234567890',
            'sender_name' => 'Pak Alamat',
            'message_body' => 'Halo',
            'direction' => 'inbound',
        ]);

        $this->actingAs($user)
            ->post(route('whatsapp-center.convert', [$account, '6281234567890']), [
                'customer_name' => 'Pak Alamat',
                'customer_address' => 'Alamat baru',
                'segment' => 'vendor',
            ])
            ->assertOk();

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'address' => 'Alamat lama',
        ]);
    }

    public function test_convert_reuses_existing_customer(): void
    {
        $account = $this->makeAccount('wa_wani');
        $user = $this->marketingUser($account->id);

        Customer::create(['name' => 'PT Lama', 'whatsapp' => '081234567890']);
        WhatsappMessage::create([
            'whatsapp_account_id' => $account->id,
            'sender_number' => '6281234567890',
            'sender_name' => 'Pak Lama',
            'message_body' => 'Mau perpanjang',
            'direction' => 'inbound',
        ]);

        $this->actingAs($user)
            ->post(route('whatsapp-center.convert', [$account, '6281234567890']), [
                'customer_name' => 'Pak Lama',
                'segment' => 'vendor',
            ])
            ->assertOk();

        $this->assertSame(1, Customer::count());
        $this->assertDatabaseHas('leads', ['pt_group' => 'WANI']);
    }

    public function test_user_only_sees_own_accounts(): void
    {
        $mine = $this->makeAccount('wa_nti');
        $other = $this->makeAccount('wa_mgk');
        $shared = $this->makeAccount('wa_tps');
        $user = $this->marketingUser($mine->id);
        $stranger = User::factory()->create();
        $stranger->assignRole('marketing');
        $other->update(['assigned_to' => $stranger->id]);

        $response = $this->actingAs($user)->get(route('whatsapp-center.index'))->assertOk();

        $accounts = collect($response->viewData('accounts'));
        $this->assertTrue($accounts->contains('id', $mine->id));
        $this->assertTrue($accounts->contains('id', $shared->id));
        $this->assertFalse($accounts->contains('id', $other->id));
        $response->assertSee('Pengaturan Bot');
    }

    public function test_user_cannot_access_account_belonging_to_others(): void
    {
        $mine = $this->makeAccount('wa_nti');
        $other = $this->makeAccount('wa_mgk');
        $user = $this->marketingUser($mine->id);
        $stranger = User::factory()->create();
        $stranger->assignRole('marketing');
        $other->update(['assigned_to' => $stranger->id]);

        $this->actingAs($user)->get(route('whatsapp-center.conversations', $other))->assertForbidden();
    }

    public function test_conversations_endpoint_lists_chats_with_preferences(): void
    {
        $account = $this->makeAccount();
        $user = $this->marketingUser($account->id);

        WhatsappMessage::create([
            'whatsapp_account_id' => $account->id,
            'sender_number' => '6281234567890',
            'sender_name' => 'Rina',
            'message_body' => 'Halo',
            'direction' => 'inbound',
        ]);

        WhatsappConversationPreference::create([
            'user_id' => $user->id,
            'whatsapp_account_id' => $account->id,
            'sender_number' => '6281234567890',
            'is_pinned' => true,
        ]);

        $this->actingAs($user)
            ->getJson(route('whatsapp-center.conversations', $account))
            ->assertOk()
            ->assertJsonPath('0.sender_number', '6281234567890')
            ->assertJsonPath('0.is_pinned', true);
    }

    public function test_lead_form_prefills_from_whatsapp_chat(): void
    {
        $account = $this->makeAccount();
        $user = $this->marketingUser($account->id);

        WhatsappMessage::create([
            'whatsapp_account_id' => $account->id,
            'sender_number' => '6281234567890',
            'sender_name' => 'Rina',
            'message_body' => 'Butuh 10 unit CCTV untuk gudang',
            'direction' => 'inbound',
        ]);

        $this->fakeBot(summary: 'Butuh 10 unit CCTV untuk gudang');

        $this->actingAs($user)
            ->get(route('leads.create', [
                'whatsapp_account_id' => $account->id,
                'sender' => '6281234567890',
                'name' => 'Rina',
            ]))
            ->assertOk()
            ->assertSee('Rina')
            ->assertSee('6281234567890')
            ->assertSee('Butuh 10 unit CCTV untuk gudang')
            ->assertSee('name="whatsapp_account_id"', false);
    }

    public function test_super_admin_sees_all_accounts(): void
    {
        $a = $this->makeAccount('wa_nti');
        $b = $this->makeAccount('wa_mgk');
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        $response = $this->actingAs($admin)->get(route('whatsapp-center.index'))->assertOk();

        $accounts = collect($response->viewData('accounts'));
        $this->assertTrue($accounts->contains('id', $a->id));
        $this->assertTrue($accounts->contains('id', $b->id));
    }

    public function test_reply_sends_via_green_api_when_configured(): void
    {
        $account = $this->makeAccount();
        $account->update(['gateway_instance' => '1101', 'gateway_token' => 'tok-123']);
        $user = $this->marketingUser($account->id);

        WhatsappMessage::create([
            'whatsapp_account_id' => $account->id,
            'sender_number' => '6281234567890',
            'sender_name' => 'Rina',
            'message_body' => 'Halo',
            'direction' => 'inbound',
        ]);

        Http::fake([
            'api.green-api.com/*' => Http::response(['idMessage' => 'g_msg_1']),
        ]);

        $this->actingAs($user)
            ->post(route('whatsapp-center.reply', [$account, '6281234567890']), ['message_body' => 'Baik'])
            ->assertOk()
            ->assertJsonPath('status', 'sent');

        $this->assertDatabaseHas('whatsapp_messages', [
            'direction' => 'outbound',
            'status' => 'sent',
            'gateway_message_id' => 'g_msg_1',
        ]);
    }

    public function test_reply_stored_only_when_gateway_unconfigured(): void
    {
        $account = $this->makeAccount();
        $user = $this->marketingUser($account->id);

        WhatsappMessage::create([
            'whatsapp_account_id' => $account->id,
            'sender_number' => '6281234567890',
            'sender_name' => 'Rina',
            'message_body' => 'Halo',
            'direction' => 'inbound',
        ]);

        $this->actingAs($user)
            ->post(route('whatsapp-center.reply', [$account, '6281234567890']), ['message_body' => 'Baik'])
            ->assertOk()
            ->assertJsonPath('status', 'queued');

        $this->assertDatabaseHas('whatsapp_messages', ['direction' => 'outbound', 'status' => 'queued']);
    }

    public function test_reply_marked_failed_when_gateway_send_fails(): void
    {
        $account = $this->makeAccount();
        $account->update(['gateway_instance' => '1101', 'gateway_token' => 'tok-123']);
        $user = $this->marketingUser($account->id);

        WhatsappMessage::create([
            'whatsapp_account_id' => $account->id,
            'sender_number' => '6281234567890',
            'sender_name' => 'Rina',
            'message_body' => 'Halo',
            'direction' => 'inbound',
        ]);

        Http::fake([
            'api.green-api.com/*' => Http::response(['error' => 'quota'], 466),
        ]);

        $this->actingAs($user)
            ->post(route('whatsapp-center.reply', [$account, '6281234567890']), ['message_body' => 'Baik'])
            ->assertOk()
            ->assertJsonPath('status', 'failed');

        $this->assertDatabaseHas('whatsapp_messages', ['direction' => 'outbound', 'status' => 'failed']);
    }

    public function test_green_api_webhook_stores_inbound_text(): void
    {
        $account = $this->makeAccount();
        $account->update(['gateway_instance' => '1101']);

        $payload = [
            'typeWebhook' => 'incomingMessageReceived',
            'instanceData' => ['idInstance' => 1101],
            'idMessage' => 'A1B2C3',
            'timestamp' => now()->timestamp,
            'senderData' => ['chatId' => '6281234567890@c.us', 'senderName' => 'Rina Putri'],
            'messageData' => ['typeMessage' => 'textMessage', 'textMessageData' => ['textMessage' => 'Minta penawaran']],
        ];

        $this->postJson('/api/whatsapp/webhook', $payload)->assertOk();

        $this->assertDatabaseHas('whatsapp_messages', [
            'whatsapp_account_id' => $account->id,
            'sender_number' => '6281234567890',
            'sender_name' => 'Rina Putri',
            'message_body' => 'Minta penawaran',
            'wa_message_id' => 'A1B2C3',
        ]);

        // Duplicate webhook ditolak via idMessage
        $this->postJson('/api/whatsapp/webhook', $payload)->assertOk();
        $this->assertSame(1, WhatsappMessage::count());
    }

    public function test_green_api_webhook_updates_outgoing_status(): void
    {
        $account = $this->makeAccount();
        $account->update(['gateway_instance' => '1101']);

        WhatsappMessage::create([
            'whatsapp_account_id' => $account->id,
            'sender_number' => '6281234567890',
            'message_body' => 'Baik',
            'direction' => 'outbound',
            'status' => 'sent',
            'gateway_message_id' => 'g_msg_1',
        ]);

        $this->postJson('/api/whatsapp/webhook', [
            'typeWebhook' => 'outgoingMessageStatus',
            'instanceData' => ['idInstance' => 1101],
            'idMessage' => 'g_msg_1',
            'status' => 'delivered',
        ])->assertOk();

        $this->assertDatabaseHas('whatsapp_messages', ['gateway_message_id' => 'g_msg_1', 'status' => 'delivered']);
    }

    public function test_green_api_webhook_updates_instance_status(): void
    {
        $account = $this->makeAccount();
        $account->update(['gateway_instance' => '1101']);

        $this->postJson('/api/whatsapp/webhook', [
            'typeWebhook' => 'instanceStatus',
            'instanceData' => ['idInstance' => 1101],
            'stateInstance' => 'authorized',
        ])->assertOk();

        $this->assertDatabaseHas('whatsapp_accounts', ['id' => $account->id, 'gateway_status' => 'authorized']);
    }

    public function test_green_api_webhook_rejects_unknown_instance(): void
    {
        $this->makeAccount();
        $this->postJson('/api/whatsapp/webhook', [
            'typeWebhook' => 'incomingMessageReceived',
            'instanceData' => ['idInstance' => 9999],
        ])->assertStatus(422);
    }

    public function test_super_admin_can_update_gateway_credentials(): void
    {
        $account = $this->makeAccount();
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        Http::fake([
            'api.green-api.com/waInstance1102/getStateInstance/*' => Http::response(['stateInstance' => 'authorized']),
        ]);

        $this->actingAs($admin)
            ->put(route('whatsapp-center.credentials', $account), [
                'gateway_instance' => '1102',
                'gateway_token' => 'tok-abc',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('whatsapp_accounts', [
            'id' => $account->id,
            'gateway_instance' => '1102',
            'gateway_status' => 'authorized',
        ]);
    }

    public function test_marketing_user_cannot_update_gateway_credentials(): void
    {
        $account = $this->makeAccount();
        $user = $this->marketingUser($account->id);

        $this->actingAs($user)
            ->put(route('whatsapp-center.credentials', $account), ['gateway_instance' => '999'])
            ->assertForbidden();
    }

    public function test_marketing_user_can_update_bot_settings(): void
    {
        $account = $this->makeAccount('wa_wani');
        $user = $this->marketingUser($account->id);

        $this->actingAs($user)
            ->put(route('whatsapp-center.bot-settings', $account), [
                'bot_instructions' => 'Sapa dengan ramah, gali kebutuhan + jumlah + lokasi.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('whatsapp_accounts', [
            'id' => $account->id,
            'bot_instructions' => 'Sapa dengan ramah, gali kebutuhan + jumlah + lokasi.',
        ]);
    }

    public function test_sales_user_cannot_update_bot_settings(): void
    {
        $account = $this->makeAccount('wa_wani');
        $user = User::factory()->create();
        $user->assignRole('sales');
        $account->update(['assigned_to' => $user->id]);

        $this->actingAs($user)
            ->put(route('whatsapp-center.bot-settings', $account), ['bot_instructions' => 'Halo'])
            ->assertForbidden();
    }

    public function test_agent_instructions_include_extra_when_given(): void
    {
        $plain = (string) (new WhatsappSalesBot)->instructions();
        $this->assertStringNotContainsString('INSTRUKSI TAMBAHAN', $plain);

        $custom = (string) (new WhatsappSalesBot('Sapa dengan nama.'))->instructions();
        $this->assertStringContainsString('INSTRUKSI TAMBAHAN PEMILIK AKUN', $custom);
        $this->assertStringContainsString('Sapa dengan nama.', $custom);
        $this->assertStringContainsString('Tujuan utamamu MENGUMPULKAN KEBUTUHAN', $custom);
    }

    public function test_blank_token_keeps_existing_gateway_token(): void
    {
        $account = $this->makeAccount();
        $account->update(['gateway_instance' => '1101', 'gateway_token' => 'tok-lama']);
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        Http::fake(['api.green-api.com/*' => Http::response(['stateInstance' => 'authorized'])]);

        $this->actingAs($admin)
            ->put(route('whatsapp-center.credentials', $account), [
                'gateway_instance' => '1101',
                'gateway_token' => '',
            ])
            ->assertRedirect();

        $this->assertSame('tok-lama', $account->fresh()->gateway_token);
    }

    public function test_verify_webhook_with_empty_config_returns_403_not_500(): void
    {
        config(['whatsapp.meta.verify_token' => '']);

        $this->get('/api/whatsapp/webhook?hub_mode=subscribe&hub_verify_token=x&hub_challenge=12345')
            ->assertForbidden();
    }

    public function test_meta_webhook_stores_placeholder_for_non_text_message(): void
    {
        $account = $this->makeAccount('wa_wani');
        $account->update(['gateway_instance' => 'PHONE_ID_1']);

        $this->postJson('/api/whatsapp/webhook', [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'metadata' => ['phone_number_id' => 'PHONE_ID_1'],
                        'messages' => [[
                            'id' => 'META-IMG-1',
                            'from' => '6281234567890',
                            'type' => 'image',
                            'image' => ['id' => 'media-1'],
                            'timestamp' => now()->timestamp,
                        ]],
                    ],
                ]],
            ]],
        ])->assertOk();

        $this->assertDatabaseHas('whatsapp_messages', [
            'whatsapp_account_id' => $account->id,
            'sender_number' => '6281234567890',
            'direction' => 'inbound',
        ]);
    }

    public function test_green_api_webhook_stores_extended_text_message(): void
    {
        $account = $this->makeAccount();
        $account->update(['gateway_instance' => '1101']);

        $this->postJson('/api/whatsapp/webhook', [
            'typeWebhook' => 'incomingMessageReceived',
            'instanceData' => ['idInstance' => 1101],
            'idMessage' => 'EXT1',
            'timestamp' => now()->timestamp,
            'senderData' => ['chatId' => '6281234567891@c.us', 'senderName' => 'Budi'],
            'messageData' => ['typeMessage' => 'extendedTextMessage', 'extendedTextMessageData' => ['text' => 'Pesan dari extendedTextMessage']],
        ])->assertOk();

        $this->assertDatabaseHas('whatsapp_messages', [
            'whatsapp_account_id' => $account->id,
            'message_body' => 'Pesan dari extendedTextMessage',
        ]);
    }

    public function test_receive_command_polls_inbound_and_acknowledges(): void
    {
        $account = $this->makeAccount();
        $account->update(['gateway_instance' => '1101', 'gateway_token' => 'tok-123']);

        Http::fake([
            'api.green-api.com/waInstance1101/getStateInstance/*' => Http::response(['stateInstance' => 'authorized']),
            'api.green-api.com/waInstance1101/receiveNotification/*' => Http::response([
                'receiptId' => 5,
                'body' => [
                    'typeWebhook' => 'incomingMessageReceived',
                    'instanceData' => ['idInstance' => 1101],
                    'idMessage' => 'POLL1',
                    'timestamp' => now()->timestamp,
                    'senderData' => ['chatId' => '6281234567890@c.us', 'senderName' => 'Rina Poll'],
                    'messageData' => ['typeMessage' => 'textMessage', 'textMessageData' => ['textMessage' => 'Halo dari polling']],
                ],
            ]),
            'api.green-api.com/waInstance1101/deleteNotification/*' => Http::response(['result' => true]),
        ]);

        $this->artisan('whatsapp:receive')->assertExitCode(0);

        // Status koneksi ikut diperbarui dari getStateInstance
        $this->assertDatabaseHas('whatsapp_accounts', ['id' => $account->id, 'gateway_status' => 'authorized']);

        // Notifikasi yang sama hanya disimpan sekali (dedupe idMessage)
        $this->assertSame(1, WhatsappMessage::count());
        $this->assertDatabaseHas('whatsapp_messages', [
            'whatsapp_account_id' => $account->id,
            'sender_number' => '6281234567890',
            'message_body' => 'Halo dari polling',
            'wa_message_id' => 'POLL1',
        ]);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/deleteNotification/'));
    }

    public function test_receive_command_skips_account_without_credentials(): void
    {
        $this->makeAccount();
        Http::fake();

        $this->artisan('whatsapp:receive')->assertExitCode(0);
        Http::assertNothingSent();
    }

    public function test_meta_webhook_verification_returns_challenge(): void
    {
        config(['whatsapp.meta.verify_token' => 'secret-token']);

        $this->get('/api/whatsapp/webhook?hub_mode=subscribe&hub_verify_token=secret-token&hub_challenge=12345')
            ->assertOk()
            ->assertSee('12345');
    }

    public function test_meta_webhook_verification_rejects_bad_token(): void
    {
        config(['whatsapp.meta.verify_token' => 'secret-token']);

        $this->get('/api/whatsapp/webhook?hub_mode=subscribe&hub_verify_token=wrong&hub_challenge=12345')
            ->assertForbidden();
    }

    public function test_meta_webhook_stores_inbound_message(): void
    {
        $account = $this->makeAccount('wa_wani');
        $account->update(['gateway_instance' => 'PHONE_ID_1']);

        $this->postJson('/api/whatsapp/webhook', [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'metadata' => ['phone_number_id' => 'PHONE_ID_1'],
                        'messages' => [[
                            'id' => 'META-1',
                            'from' => '6281234567890',
                            'type' => 'text',
                            'text' => ['body' => 'Halo dari Meta Cloud API'],
                            'timestamp' => now()->timestamp,
                        ]],
                    ],
                ]],
            ]],
        ])->assertOk();

        $this->assertDatabaseHas('whatsapp_messages', [
            'whatsapp_account_id' => $account->id,
            'sender_number' => '6281234567890',
            'message_body' => 'Halo dari Meta Cloud API',
            'direction' => 'inbound',
        ]);
    }

    public function test_meta_webhook_updates_outgoing_status(): void
    {
        $account = $this->makeAccount('wa_wani');
        $account->update(['gateway_instance' => 'PHONE_ID_1']);

        WhatsappMessage::create([
            'whatsapp_account_id' => $account->id,
            'sender_number' => '6281234567890',
            'message_body' => 'Balasan',
            'direction' => 'outbound',
            'status' => 'sent',
            'wa_message_id' => 'META-OUT-1',
        ]);

        $this->postJson('/api/whatsapp/webhook', [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'metadata' => ['phone_number_id' => 'PHONE_ID_1'],
                        'statuses' => [[
                            'id' => 'META-OUT-1',
                            'status' => 'delivered',
                        ]],
                    ],
                ]],
            ]],
        ])->assertOk();

        $this->assertDatabaseHas('whatsapp_messages', [
            'wa_message_id' => 'META-OUT-1',
            'status' => 'delivered',
        ]);
    }

    public function test_meta_webhook_routes_to_correct_account_by_phone_number_id(): void
    {
        $wani = $this->makeAccount('wa_wani');
        $wani->update(['gateway_instance' => 'PHONE_WANI']);

        $mgk = $this->makeAccount('wa_mgk', WhatsappAccount::GATEWAY_META);
        $mgk->update(['gateway_instance' => 'PHONE_MGK']);

        $this->postJson('/api/whatsapp/webhook', [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'metadata' => ['phone_number_id' => 'PHONE_MGK'],
                        'messages' => [[
                            'id' => 'META-MGK-1',
                            'from' => '6281234567890',
                            'type' => 'text',
                            'text' => ['body' => 'Pesan untuk MGK'],
                            'timestamp' => now()->timestamp,
                        ]],
                    ],
                ]],
            ]],
        ])->assertOk();

        $this->assertDatabaseHas('whatsapp_messages', [
            'whatsapp_account_id' => $mgk->id,
            'wa_message_id' => 'META-MGK-1',
        ]);
        $this->assertDatabaseMissing('whatsapp_messages', [
            'whatsapp_account_id' => $wani->id,
            'wa_message_id' => 'META-MGK-1',
        ]);
    }

    public function test_meta_webhook_rejects_unknown_phone_number_id(): void
    {
        $account = $this->makeAccount('wa_wani');
        $account->update(['gateway_instance' => 'PHONE_WANI']);

        $this->postJson('/api/whatsapp/webhook', [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'metadata' => ['phone_number_id' => 'PHONE_UNKNOWN'],
                        'messages' => [[
                            'id' => 'META-X',
                            'from' => '6281234567890',
                            'type' => 'text',
                            'text' => ['body' => 'Orphan'],
                            'timestamp' => now()->timestamp,
                        ]],
                    ],
                ]],
            ]],
        ])->assertStatus(422);
    }

    public function test_meta_webhook_updates_outgoing_status_by_gateway_message_id(): void
    {
        $account = $this->makeAccount('wa_wani');
        $account->update(['gateway_instance' => 'PHONE_ID_1']);

        WhatsappMessage::create([
            'whatsapp_account_id' => $account->id,
            'sender_number' => '6281234567890',
            'message_body' => 'Balasan kirim',
            'direction' => 'outbound',
            'status' => 'sent',
            'gateway_message_id' => 'wamid.OUT.123',
        ]);

        $this->postJson('/api/whatsapp/webhook', [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'metadata' => ['phone_number_id' => 'PHONE_ID_1'],
                        'statuses' => [[
                            'id' => 'wamid.OUT.123',
                            'status' => 'read',
                        ]],
                    ],
                ]],
            ]],
        ])->assertOk();

        $this->assertDatabaseHas('whatsapp_messages', [
            'gateway_message_id' => 'wamid.OUT.123',
            'status' => 'read',
        ]);
    }

    public function test_status_endpoint_does_not_call_meta_graph_api(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');
        Http::fake();

        $account = $this->makeAccount('wa_wani');
        $account->update(['gateway_instance' => '123', 'gateway_token' => 'tok', 'gateway_status' => 'authorized']);

        $this->actingAs($admin)
            ->getJson(route('whatsapp-center.status'))
            ->assertOk()
            ->assertJsonFragment(['account_code' => 'wa_wani', 'gateway_status' => 'authorized']);

        Http::assertNothingSent();
    }

    public function test_check_status_meta_updates_authorized(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        $account = $this->makeAccount('wa_wani');
        $account->update(['gateway_instance' => '123', 'gateway_token' => 'tok', 'gateway_status' => null]);

        Http::fake([
            'graph.facebook.com/v19.0/123*' => Http::response(['id' => '123', 'display_phone_number' => '6281111111101']),
        ]);

        $this->actingAs($admin)
            ->getJson(route('whatsapp-center.check-status', $account))
            ->assertOk()
            ->assertJson(['gateway_status' => 'authorized']);

        $this->assertDatabaseHas('whatsapp_accounts', [
            'id' => $account->id,
            'gateway_status' => 'authorized',
        ]);
    }

    public function test_check_status_meta_rejects_non_phone_number_id(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        $account = $this->makeAccount('wa_wani');
        $account->update(['gateway_instance' => 'APP_ID_123', 'gateway_token' => 'tok', 'gateway_status' => 'authorized']);

        Http::fake([
            'graph.facebook.com/v19.0/APP_ID_123*' => Http::response([
                'error' => ['message' => 'Tried accessing nonexisting field', 'code' => 100],
            ], 400),
        ]);

        $this->actingAs($admin)
            ->getJson(route('whatsapp-center.check-status', $account))
            ->assertOk()
            ->assertJson(['gateway_status' => null]);

        $this->assertDatabaseHas('whatsapp_accounts', [
            'id' => $account->id,
            'gateway_status' => null,
        ]);
    }

    public function test_check_status_meta_reports_connection_result(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        $account = $this->makeAccount('wa_wani');
        $account->update(['gateway_instance' => '123', 'gateway_token' => 'tok', 'gateway_status' => null]);

        Http::fake([
            'graph.facebook.com/v19.0/123*' => Http::response(['id' => '123', 'display_phone_number' => '6281111111101']),
        ]);

        $this->actingAs($admin)
            ->getJson(route('whatsapp-center.check-status', $account))
            ->assertOk()
            ->assertJson(['gateway_status' => 'authorized', 'connected' => true, 'detail' => null]);
    }

    public function test_check_status_meta_reports_failure_detail(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        $account = $this->makeAccount('wa_wani');
        $account->update(['gateway_instance' => 'WRONG_ID', 'gateway_token' => 'tok', 'gateway_status' => null]);

        Http::fake([
            'graph.facebook.com/v19.0/WRONG_ID*' => Http::response([
                'error' => ['message' => '(#100) Tried accessing nonexisting field', 'code' => 100],
            ], 400),
        ]);

        $this->actingAs($admin)
            ->getJson(route('whatsapp-center.check-status', $account))
            ->assertOk()
            ->assertJson(['gateway_status' => null, 'connected' => false])
            ->assertJsonPath('detail', '(#100) Tried accessing nonexisting field');
    }

    public function test_status_endpoint_reports_credential_presence(): void
    {
        $account = $this->makeAccount('wa_wani');
        $account->update(['gateway_instance' => 'PHONE_ID_1', 'gateway_token' => 'tok-meta']);
        $user = $this->marketingUser($account->id);

        $this->actingAs($user)
            ->getJson(route('whatsapp-center.status'))
            ->assertOk()
            ->assertJsonPath('0.has_credentials', true);
    }

    public function test_chat_endpoints_paginate_mark_read_save_contact_and_store_preferences(): void
    {
        $account = $this->makeAccount();
        $user = $this->marketingUser($account->id);

        WhatsappMessage::create([
            'whatsapp_account_id' => $account->id,
            'sender_number' => '6281234567890',
            'sender_name' => 'Rina',
            'message_body' => 'Pesan pertama',
            'direction' => 'inbound',
        ]);
        WhatsappMessage::create([
            'whatsapp_account_id' => $account->id,
            'sender_number' => '6281234567890',
            'sender_name' => 'Rina',
            'message_body' => 'Pesan kedua',
            'direction' => 'inbound',
        ]);

        $this->actingAs($user)
            ->getJson(route('whatsapp-center.messages', [$account, '6281234567890']).'?limit=1')
            ->assertOk()
            ->assertJsonPath('has_more', true)
            ->assertJsonCount(1, 'messages');

        $this->actingAs($user)
            ->postJson(route('whatsapp-center.mark-read', [$account, '6281234567890']))
            ->assertOk()
            ->assertJsonPath('read', true);

        $this->assertSame(2, WhatsappMessage::whereNotNull('read_at')->count());

        $this->actingAs($user)
            ->postJson(route('whatsapp-center.preference', [$account, '6281234567890']), [
                'is_pinned' => true,
                'is_muted' => true,
            ])
            ->assertOk()
            ->assertJsonPath('is_pinned', true)
            ->assertJsonPath('is_muted', true);

        $this->actingAs($user)
            ->postJson(route('whatsapp-center.contact-save', $account), [
                'name' => 'Rina Tersimpan',
                'whatsapp' => '6281234567890',
                'notes' => 'Kontak dari WhatsApp',
            ])
            ->assertOk()
            ->assertJsonPath('name', 'Rina Tersimpan');

        $this->assertDatabaseHas('customers', [
            'whatsapp' => '6281234567890',
            'name' => 'Rina Tersimpan',
        ]);
    }

    public function test_save_contact_combines_contact_and_company_as_name(): void
    {
        $account = $this->makeAccount();
        $user = $this->marketingUser($account->id);

        $this->actingAs($user)
            ->postJson(route('whatsapp-center.contact-save', $account), [
                'name' => 'Budi',
                'company' => 'PT Budi Corp',
                'whatsapp' => '6281234567890',
            ])
            ->assertOk()
            ->assertJsonPath('name', 'PT Budi Corp')
            ->assertJsonPath('contact_person', 'Budi');

        $this->assertDatabaseHas('customers', [
            'whatsapp' => '6281234567890',
            'name' => 'PT Budi Corp',
            'company' => 'PT Budi Corp',
            'contact_person' => 'Budi',
        ]);
    }

    public function test_save_contact_stores_address(): void
    {
        $account = $this->makeAccount();
        $user = $this->marketingUser($account->id);

        $this->actingAs($user)
            ->postJson(route('whatsapp-center.contact-save', $account), [
                'name' => 'Budi',
                'company' => 'PT Budi Corp',
                'whatsapp' => '6281234567890',
                'address' => 'Jl. Sudirman No. 5, Jakarta',
            ])
            ->assertOk()
            ->assertJsonPath('address', 'Jl. Sudirman No. 5, Jakarta');

        $this->assertDatabaseHas('customers', [
            'whatsapp' => '6281234567890',
            'address' => 'Jl. Sudirman No. 5, Jakarta',
        ]);
    }

    public function test_save_contact_keeps_address_when_field_empty(): void
    {
        $account = $this->makeAccount();
        $user = $this->marketingUser($account->id);
        $customer = Customer::create([
            'name' => 'PT Ada Alamat',
            'whatsapp' => '6281234567890',
            'address' => 'Alamat tersimpan',
        ]);

        $this->actingAs($user)
            ->postJson(route('whatsapp-center.contact-save', $account), [
                'name' => 'Budi',
                'whatsapp' => '6281234567890',
                'address' => '',
            ])
            ->assertOk();

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'address' => 'Alamat tersimpan',
        ]);
    }

    public function test_save_contact_keeps_existing_company_when_field_empty(): void
    {
        $account = $this->makeAccount();
        $user = $this->marketingUser($account->id);
        $customer = Customer::create([
            'name' => 'PT Ada Dulu',
            'company' => 'PT Ada Dulu',
            'contact_person' => 'Rina',
            'whatsapp' => '6281234567890',
        ]);

        $this->actingAs($user)
            ->postJson(route('whatsapp-center.contact-save', $account), [
                'name' => 'Budi',
                'company' => '',
                'whatsapp' => '6281234567890',
            ])
            ->assertOk();

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => 'PT Ada Dulu',
            'company' => 'PT Ada Dulu',
            'contact_person' => 'Budi',
        ]);
    }

    public function test_reply_normalizes_local_number_for_green_api(): void
    {
        $account = $this->makeAccount();
        $account->update(['gateway_instance' => '1101', 'gateway_token' => 'tok-123']);
        $user = $this->marketingUser($account->id);

        Http::fake([
            'api.green-api.com/*' => Http::response(['idMessage' => 'g_msg_2']),
        ]);

        $this->actingAs($user)
            ->post(route('whatsapp-center.reply', [$account, '081234567890']), ['message_body' => 'Baik'])
            ->assertOk()
            ->assertJsonPath('status', 'sent');

        $this->assertDatabaseHas('whatsapp_messages', [
            'whatsapp_account_id' => $account->id,
            'sender_number' => '6281234567890',
            'direction' => 'outbound',
        ]);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/sendMessage/')
            && $request['chatId'] === '6281234567890@c.us');
    }

    public function test_meta_reply_normalizes_recipient_number(): void
    {
        $account = $this->makeAccount('wa_wani');
        $account->update(['gateway_instance' => 'PHONE_ID_1', 'gateway_token' => 'tok-meta']);
        $user = $this->marketingUser($account->id);

        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]]),
        ]);

        $this->actingAs($user)
            ->post(route('whatsapp-center.reply', [$account, '081234567890']), ['message_body' => 'Halo'])
            ->assertOk()
            ->assertJsonPath('status', 'sent');

        $this->assertDatabaseHas('whatsapp_messages', [
            'whatsapp_account_id' => $account->id,
            'sender_number' => '6281234567890',
            'direction' => 'outbound',
        ]);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/messages')
            && $request['to'] === '6281234567890');
    }

    public function test_bot_replies_even_when_lock_fails(): void
    {
        $account = $this->makeAccount('wa_wani');
        $account->update(['gateway_instance' => 'PHONE_ID_1', 'gateway_token' => 'tok-meta', 'bot_enabled' => true]);

        WhatsappMessage::create([
            'whatsapp_account_id' => $account->id,
            'sender_number' => '6281234567890',
            'sender_name' => 'Rina',
            'message_body' => 'Halo mau tanya CCTV',
            'direction' => 'inbound',
        ]);

        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]]),
        ]);

        $lock = \Mockery::mock();
        $lock->shouldReceive('acquire')->andThrow(new \RuntimeException('lock dir gone'));
        $lock->shouldReceive('release')->never();
        Cache::shouldReceive('lock')->andReturn($lock);

        $bot = $this->fakeBot(reply: 'Halo, ada yang bisa dibantu?');

        $this->assertSame(1, $bot->replyPending());

        $this->assertDatabaseHas('whatsapp_messages', [
            'whatsapp_account_id' => $account->id,
            'direction' => 'outbound',
            'is_bot' => true,
        ]);
    }

    private function fakeBot(string $reply = 'Halo, butuh berapa unit dan brandnya?', ?string $summary = null): WhatsappBot
    {
        $bot = \Mockery::mock(WhatsappBot::class, [app(WhatsappGateway::class)])->makePartial();
        $bot->shouldReceive('generateReply')->andReturn($reply);
        $bot->shouldReceive('summarizeNeeds')->andReturn($summary);

        $this->app->instance(WhatsappBot::class, $bot);

        return $bot;
    }

    public function test_bot_replies_to_pending_inbound_and_marks_as_bot(): void
    {
        $account = $this->makeAccount();
        $account->update(['gateway_instance' => '1101', 'gateway_token' => 'tok-123', 'bot_enabled' => true]);

        WhatsappMessage::create([
            'whatsapp_account_id' => $account->id,
            'sender_number' => '6281234567890',
            'sender_name' => 'Rina',
            'message_body' => 'Halo mau tanya CCTV',
            'direction' => 'inbound',
        ]);

        Http::fake([
            'api.green-api.com/*' => Http::response(['idMessage' => 'bot_msg_1']),
        ]);

        $bot = $this->fakeBot();

        $this->assertSame(1, $bot->replyPending());
        $this->assertDatabaseHas('whatsapp_messages', [
            'whatsapp_account_id' => $account->id,
            'direction' => 'outbound',
            'is_bot' => true,
            'status' => 'sent',
            'gateway_message_id' => 'bot_msg_1',
        ]);
    }

    public function test_bot_skips_conversation_after_sticky_takeover(): void
    {
        $account = $this->makeAccount();
        $account->update(['gateway_instance' => '1101', 'gateway_token' => 'tok-123', 'bot_enabled' => true]);

        \App\Models\WhatsappConversation::create([
            'whatsapp_account_id' => $account->id,
            'sender_number' => '6281234567890',
            'mode' => 'human',
        ]);

        WhatsappMessage::create([
            'whatsapp_account_id' => $account->id,
            'sender_number' => '6281234567890',
            'message_body' => 'halo masih ada?',
            'direction' => 'inbound',
        ]);

        Http::fake();
        $bot = $this->fakeBot();

        // Sticky: walau tidak ada balasan manusia baru-baru ini, bot tetap diam.
        $this->assertSame(0, $bot->replyPending());
    }

    public function test_bot_replies_again_after_release(): void
    {
        $account = $this->makeAccount();
        $account->update(['gateway_instance' => '1101', 'gateway_token' => 'tok-123', 'bot_enabled' => true]);

        WhatsappMessage::create([
            'whatsapp_account_id' => $account->id,
            'sender_number' => '6281234567890',
            'message_body' => 'halo',
            'direction' => 'inbound',
        ]);

        Http::fake(['api.green-api.com/*' => Http::response(['idMessage' => 'bot_msg_2'])]);
        $bot = $this->fakeBot();

        $bot->takeover($account, '6281234567890');
        $this->assertSame(0, $bot->replyPending());

        $bot->release($account, '6281234567890');
        $this->assertSame(1, $bot->replyPending());
    }

    public function test_bot_stops_after_max_turns_and_notifies_handoff(): void
    {
        $account = $this->makeAccount();
        $account->update(['gateway_instance' => '1101', 'gateway_token' => 'tok-123', 'bot_enabled' => true]);
        $marketing = $this->marketingUser($account->id);

        \App\Models\WhatsappConversation::create([
            'whatsapp_account_id' => $account->id,
            'sender_number' => '6281234567890',
            'mode' => 'bot',
            'bot_turns' => 4,
        ]);

        WhatsappMessage::create([
            'whatsapp_account_id' => $account->id,
            'sender_number' => '6281234567890',
            'message_body' => 'tolong info lebih lanjut',
            'direction' => 'inbound',
        ]);

        Http::fake(['api.green-api.com/*' => Http::response(['idMessage' => 'bot_msg_5'])]);
        $bot = $this->fakeBot();

        $this->assertSame(1, $bot->replyPending());
        $this->assertSame(5, \App\Models\WhatsappConversation::where('whatsapp_account_id', $account->id)->first()->bot_turns);
        $this->assertSame(1, $marketing->notifications()->count());

        // Balasan ke-6 tidak dikirim karena sudah mencapai batas.
        WhatsappMessage::create([
            'whatsapp_account_id' => $account->id,
            'sender_number' => '6281234567890',
            'message_body' => 'halo?',
            'direction' => 'inbound',
        ]);
        $this->assertSame(0, $bot->replyPending());
    }

    public function test_lead_form_prefill_uses_ai_summary(): void
    {
        $account = $this->makeAccount();
        $user = $this->marketingUser($account->id);

        WhatsappMessage::create([
            'whatsapp_account_id' => $account->id,
            'sender_number' => '6281234567890',
            'message_body' => 'halo selamat siang',
            'direction' => 'inbound',
        ]);

        $this->fakeBot(summary: 'CCTV 8 kamera (Hikvision) untuk gudang');

        $this->actingAs($user)
            ->get(route('leads.create', [
                'whatsapp_account_id' => $account->id,
                'sender' => '6281234567890',
                'name' => 'Rina',
            ]))
            ->assertOk()
            ->assertSee('CCTV 8 kamera (Hikvision) untuk gudang');
    }

    public function test_new_inbound_notifies_marketing_users(): void
    {
        $account = $this->makeAccount();
        $marketing = $this->marketingUser($account->id);

        $this->postJson('/api/whatsapp/webhook', [
            'account_code' => $account->account_code,
            'sender_number' => '6281234567890',
            'message_body' => 'Halo, mau tanya harga CCTV',
        ])->assertOk();

        $this->assertSame(1, $marketing->notifications()->count());
        $this->assertSame('whatsapp', $marketing->notifications()->first()->data['type']);
    }

    public function test_save_contact_formats_name_with_company(): void
    {
        $account = $this->makeAccount();
        $user = $this->marketingUser($account->id);

        $this->actingAs($user)
            ->postJson(route('whatsapp-center.contact-save', $account), [
                'name' => 'Budi',
                'company' => 'PT ABC',
                'whatsapp' => '6281234567890',
            ])
            ->assertOk()
            ->assertJsonPath('name', 'PT ABC');

        $this->assertDatabaseHas('customers', [
            'whatsapp' => '6281234567890',
            'name' => 'PT ABC',
            'company' => 'PT ABC',
            'contact_person' => 'Budi',
        ]);
    }

    public function test_save_contact_without_company_uses_contact_name(): void
    {
        $account = $this->makeAccount();
        $user = $this->marketingUser($account->id);

        $this->actingAs($user)
            ->postJson(route('whatsapp-center.contact-save', $account), [
                'name' => 'Sinta',
                'whatsapp' => '628111222333',
            ])
            ->assertOk()
            ->assertJsonPath('name', 'Sinta');

        $this->assertDatabaseHas('customers', [
            'whatsapp' => '628111222333',
            'name' => 'Sinta',
            'contact_person' => 'Sinta',
        ]);
    }

    private function userWithAccounts(WhatsappAccount ...$accounts)
    {
        $user = User::factory()->create();
        $user->assignRole('marketing');
        WhatsappAccount::whereIn('id', collect($accounts)->map->id)->update(['assigned_to' => $user->id]);

        return $user;
    }

    public function test_sender_with_history_only_in_other_account_returns_404(): void
    {
        $wani = $this->makeAccount('wa_wani');
        $mgk = $this->makeAccount('wa_mgk');
        $user = $this->userWithAccounts($wani, $mgk);

        WhatsappMessage::create([
            'whatsapp_account_id' => $wani->id,
            'sender_number' => '6281234567890',
            'sender_name' => 'Yeski',
            'message_body' => 'Halo WANI',
            'direction' => 'inbound',
        ]);

        $this->actingAs($user)
            ->getJson(route('whatsapp-center.messages', [$mgk, '6281234567890']))
            ->assertNotFound();

        $this->actingAs($user)
            ->getJson(route('whatsapp-center.messages', [$wani, '6281234567890']))
            ->assertOk();
    }

    public function test_customer_owned_by_other_account_is_not_accessible(): void
    {
        $wani = $this->makeAccount('wa_wani');
        $mgk = $this->makeAccount('wa_mgk');
        $user = $this->userWithAccounts($wani, $mgk);

        Customer::create([
            'name' => 'PT Gasken',
            'company' => 'PT Gasken',
            'contact_person' => 'Yeski',
            'whatsapp' => '6281234567890',
            'whatsapp_account_id' => $mgk->id,
        ]);
        WhatsappMessage::create([
            'whatsapp_account_id' => $wani->id,
            'sender_number' => '6281234567890',
            'sender_name' => 'Yeski',
            'message_body' => 'Halo',
            'direction' => 'inbound',
        ]);

        $this->actingAs($user)
            ->getJson(route('whatsapp-center.messages', [$wani, '6281234567890']))
            ->assertNotFound();

        $this->actingAs($user)
            ->postJson(route('whatsapp-center.contact-save', $wani), [
                'name' => 'Yeski',
                'company' => 'PT Gasken',
                'whatsapp' => '6281234567890',
            ])
            ->assertForbidden();
    }

    public function test_save_contact_claims_unowned_customer_and_normalizes_number(): void
    {
        $wani = $this->makeAccount('wa_wani');
        $user = $this->marketingUser($wani->id);

        Customer::create(['name' => 'Rina', 'contact_person' => 'Rina', 'whatsapp' => '081234567890']);

        $this->actingAs($user)
            ->postJson(route('whatsapp-center.contact-save', $wani), [
                'name' => 'Rina',
                'company' => 'PT Rina',
                'whatsapp' => '081234567890',
            ])
            ->assertOk()
            ->assertJsonPath('name', 'PT Rina');

        $this->assertDatabaseHas('customers', [
            'whatsapp' => '6281234567890',
            'whatsapp_account_id' => $wani->id,
        ]);
    }

    public function test_contacts_only_lists_account_related_customers(): void
    {
        $wani = $this->makeAccount('wa_wani');
        $mgk = $this->makeAccount('wa_mgk');
        $user = $this->userWithAccounts($wani, $mgk);

        $owned = Customer::create(['name' => 'PT Wani Cust', 'whatsapp' => '628100000001', 'whatsapp_account_id' => $wani->id]);
        $foreign = Customer::create(['name' => 'PT Mgk Cust', 'whatsapp' => '628100000002', 'whatsapp_account_id' => $mgk->id]);
        $unrelated = Customer::create(['name' => 'PT entah', 'whatsapp' => '628100000003']);
        $byHistory = Customer::create(['name' => 'PT History', 'whatsapp' => '628100000004']);
        WhatsappMessage::create([
            'whatsapp_account_id' => $wani->id,
            'sender_number' => '628100000004',
            'sender_name' => 'Sejarah',
            'message_body' => 'Halo',
            'direction' => 'inbound',
        ]);

        $ids = collect($this->actingAs($user)->getJson(route('whatsapp-center.contacts', $wani))->assertOk()->json())->pluck('id');

        $this->assertTrue($ids->contains($owned->id));
        $this->assertTrue($ids->contains($byHistory->id));
        $this->assertFalse($ids->contains($foreign->id));
        $this->assertFalse($ids->contains($unrelated->id));
    }

    public function test_convert_claims_customer_to_account(): void
    {
        $wani = $this->makeAccount('wa_wani');
        $user = $this->marketingUser($wani->id);

        $customer = Customer::create(['name' => 'PT Lama', 'whatsapp' => '6281234567890']);
        WhatsappMessage::create([
            'whatsapp_account_id' => $wani->id,
            'sender_number' => '6281234567890',
            'sender_name' => 'Pak Lama',
            'message_body' => 'Mau perpanjang',
            'direction' => 'inbound',
        ]);

        $this->actingAs($user)
            ->post(route('whatsapp-center.convert', [$wani, '6281234567890']), [
                'customer_name' => 'Pak Lama',
                'segment' => 'vendor',
            ])
            ->assertOk();

        $this->assertSame($wani->id, $customer->fresh()->whatsapp_account_id);
    }

    public function test_reply_to_brand_new_number_is_allowed(): void
    {
        $wani = $this->makeAccount('wa_wani');
        $user = $this->marketingUser($wani->id);

        $this->actingAs($user)
            ->post(route('whatsapp-center.reply', [$wani, '628999888777']), ['message_body' => 'Halo baru'])
            ->assertOk();
    }

    public function test_conversations_hides_senders_owned_by_other_account(): void
    {
        $wani = $this->makeAccount('wa_wani');
        $mgk = $this->makeAccount('wa_mgk');
        $user = $this->userWithAccounts($wani, $mgk);

        Customer::create([
            'name' => 'PT Gasken',
            'company' => 'PT Gasken',
            'contact_person' => 'Yeski',
            'whatsapp' => '6281234567890',
            'whatsapp_account_id' => $wani->id,
        ]);
        foreach ([$wani, $mgk] as $account) {
            WhatsappMessage::create([
                'whatsapp_account_id' => $account->id,
                'sender_number' => '6281234567890',
                'sender_name' => 'Yeski',
                'message_body' => 'Halo',
                'direction' => 'inbound',
            ]);
        }

        $mgkSenders = collect($this->actingAs($user)->getJson(route('whatsapp-center.conversations', $mgk))->assertOk()->json())->pluck('sender_number');
        $waniSenders = collect($this->actingAs($user)->getJson(route('whatsapp-center.conversations', $wani))->assertOk()->json())->pluck('sender_number');

        $this->assertFalse($mgkSenders->contains('6281234567890'));
        $this->assertTrue($waniSenders->contains('6281234567890'));
    }

    public function test_conversations_unread_counts_inbound_after_last_outbound(): void
    {
        $account = $this->makeAccount();
        $user = $this->marketingUser($account->id);

        WhatsappMessage::create([
            'whatsapp_account_id' => $account->id, 'sender_number' => '6281111111111',
            'sender_name' => 'A', 'message_body' => 'satu', 'direction' => 'inbound',
        ]);
        WhatsappMessage::create([
            'whatsapp_account_id' => $account->id, 'sender_number' => '6281111111111',
            'sender_name' => 'Tim', 'message_body' => 'balasan', 'direction' => 'outbound',
        ]);
        WhatsappMessage::create([
            'whatsapp_account_id' => $account->id, 'sender_number' => '6281111111111',
            'sender_name' => 'A', 'message_body' => 'dua', 'direction' => 'inbound',
        ]);
        WhatsappMessage::create([
            'whatsapp_account_id' => $account->id, 'sender_number' => '6282222222222',
            'sender_name' => 'B', 'message_body' => 'halo', 'direction' => 'inbound', 'read_at' => now(),
        ]);

        $rows = collect($this->actingAs($user)->getJson(route('whatsapp-center.conversations', $account))->assertOk()->json())
            ->keyBy('sender_number');

        $this->assertSame(1, $rows['6281111111111']['unread']);
        $this->assertSame(0, $rows['6282222222222']['unread']);
    }

    public function test_contacts_hides_foreign_owned_even_with_local_history(): void
    {
        $wani = $this->makeAccount('wa_wani');
        $mgk = $this->makeAccount('wa_mgk');
        $user = $this->userWithAccounts($wani, $mgk);

        $owned = Customer::create(['name' => 'PT Gasken', 'whatsapp' => '6281234567890', 'whatsapp_account_id' => $wani->id]);
        WhatsappMessage::create([
            'whatsapp_account_id' => $mgk->id,
            'sender_number' => '6281234567890',
            'sender_name' => 'Yeski',
            'message_body' => 'Halo MGK',
            'direction' => 'inbound',
        ]);

        $ids = collect($this->actingAs($user)->getJson(route('whatsapp-center.contacts', $mgk))->assertOk()->json())->pluck('id');

        $this->assertFalse($ids->contains($owned->id));
    }

    public function test_reply_to_foreign_owned_number_returns_404(): void
    {
        $wani = $this->makeAccount('wa_wani');
        $mgk = $this->makeAccount('wa_mgk');
        $user = $this->userWithAccounts($wani, $mgk);

        Customer::create(['name' => 'PT Gasken', 'whatsapp' => '6281234567890', 'whatsapp_account_id' => $wani->id]);

        $this->actingAs($user)
            ->post(route('whatsapp-center.reply', [$mgk, '6281234567890']), ['message_body' => 'Halo'])
            ->assertNotFound();

        $this->assertDatabaseMissing('whatsapp_messages', [
            'whatsapp_account_id' => $mgk->id,
            'sender_number' => '6281234567890',
            'direction' => 'outbound',
        ]);
    }

    public function test_gateway_token_encrypted_at_rest_but_readable_via_model(): void
    {
        $account = $this->makeAccount('wa_nti');
        $account->update(['gateway_token' => 'token-rahasia-123']);

        $raw = \Illuminate\Support\Facades\DB::table('whatsapp_accounts')
            ->where('id', $account->id)->value('gateway_token');

        // Di DB tersimpan envelope terenkripsi (tanpa serialisasi,
        // sesuai cast 'encrypted'), bukan plaintext.
        $this->assertNotSame('token-rahasia-123', $raw);
        $this->assertStringStartsWith('eyJ', $raw);
        $this->assertSame('token-rahasia-123', $account->fresh()->gateway_token);
    }

    public function test_meta_webhook_rejects_missing_signature_when_secret_set(): void
    {
        config(['whatsapp.meta.app_secret' => 'test-app-secret']);

        $account = $this->makeAccount('wa_wani');
        $account->update(['gateway_instance' => 'PHONE_ID_1']);

        $this->postJson('/api/whatsapp/webhook', [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'metadata' => ['phone_number_id' => 'PHONE_ID_1'],
                        'messages' => [[
                            'id' => 'META-SPOOF',
                            'from' => '6281234567890',
                            'type' => 'text',
                            'text' => ['body' => 'Palsu'],
                            'timestamp' => now()->timestamp,
                        ]],
                    ],
                ]],
            ]],
        ])->assertForbidden();

        $this->assertDatabaseMissing('whatsapp_messages', ['wa_message_id' => 'META-SPOOF']);
    }

    public function test_meta_webhook_accepts_valid_signature_when_secret_set(): void
    {
        config(['whatsapp.meta.app_secret' => 'test-app-secret']);

        $account = $this->makeAccount('wa_wani');
        $account->update(['gateway_instance' => 'PHONE_ID_1']);

        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'metadata' => ['phone_number_id' => 'PHONE_ID_1'],
                        'messages' => [[
                            'id' => 'META-SIG-OK',
                            'from' => '6281234567890',
                            'type' => 'text',
                            'text' => ['body' => 'Asli'],
                            'timestamp' => now()->timestamp,
                        ]],
                    ],
                ]],
            ]],
        ];
        $content = json_encode($payload);
        $signature = 'sha256='.hash_hmac('sha256', $content, 'test-app-secret');

        $this->call('POST', '/api/whatsapp/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X-Hub-Signature-256' => $signature,
        ], $content)->assertOk();

        $this->assertDatabaseHas('whatsapp_messages', ['wa_message_id' => 'META-SIG-OK']);
    }
}

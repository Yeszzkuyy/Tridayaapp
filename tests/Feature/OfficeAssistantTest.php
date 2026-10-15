<?php

namespace Tests\Feature;

use App\Ai\Agents\OfficeAssistant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Models\ConversationMessage;
use Tests\TestCase;

class OfficeAssistantTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        return $user;
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('ai.assistant.index'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_chat_page(): void
    {
        $this->actingUser();

        $this->get(route('ai.assistant.index'))
            ->assertOk()
            ->assertSee('AI Assistant');
    }

    public function test_send_returns_error_when_openrouter_key_is_missing(): void
    {
        config()->set('ai.providers.openrouter.key', '');

        $this->actingUser();

        $this->postJson(route('ai.assistant.send'), ['message' => 'Halo'])
            ->assertStatus(422)
            ->assertJsonPath('message', __('Asisten AI belum aktif karena API key OpenRouter belum dikonfigurasi. Hubungi administrator.'));
    }

    public function test_message_requires_text(): void
    {
        config()->set('ai.providers.openrouter.key', 'test-key');
        OfficeAssistant::fake(['Balasan test']);

        $this->actingUser();

        $this->postJson(route('ai.assistant.send'), ['message' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('message');
    }

    public function test_send_creates_conversation_and_returns_reply(): void
    {
        config()->set('ai.providers.openrouter.key', 'test-key');
        OfficeAssistant::fake(['Balasan test']);

        $this->actingUser();

        $response = $this->postJson(route('ai.assistant.send'), ['message' => 'Apa itu 3DY Group?'])
            ->assertOk()
            ->assertJsonPath('message', 'Balasan test');

        $conversationId = $response->json('conversation_id');
        $this->assertNotNull($conversationId);

        $this->assertDatabaseHas('agent_conversations', ['id' => $conversationId]);
        $this->assertSame(2, ConversationMessage::where('conversation_id', $conversationId)->count());
    }

    public function test_send_continues_existing_conversation(): void    {
        config()->set('ai.providers.openrouter.key', 'test-key');
        OfficeAssistant::fake(['Balasan pertama', 'Balasan kedua']);

        $this->actingUser();

        $first = $this->postJson(route('ai.assistant.send'), ['message' => 'Pertanyaan pertama'])
            ->assertOk()
            ->json('conversation_id');

        $second = $this->postJson(route('ai.assistant.send'), [
            'message' => 'Pertanyaan kedua',
            'conversation_id' => $first,
        ])->assertOk();

        $this->assertSame($first, $second->json('conversation_id'));
        $this->assertSame(4, ConversationMessage::where('conversation_id', $first)->count());
    }

    public function test_send_rejects_conversation_owned_by_other_user(): void
    {
        config()->set('ai.providers.openrouter.key', 'test-key');
        OfficeAssistant::fake(['Balasan test']);

        $owner = $this->actingAs(User::factory()->create());
        $conversationId = $this->postJson(route('ai.assistant.send'), ['message' => 'Milik user A'])
            ->json('conversation_id');

        $this->actingAs(User::factory()->create())
            ->postJson(route('ai.assistant.send'), [
                'message' => 'Coba akses',
                'conversation_id' => $conversationId,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', __('Percakapan tidak valid. Silakan mulai percakapan baru.'));

        $this->assertSame(1, Conversation::where('id', $conversationId)->count());
    }

    public function test_user_can_list_and_open_owned_conversations(): void
    {
        $user = $this->actingUser();
        $older = Conversation::create([
            'id' => '00000000-0000-0000-0000-000000000001',
            'user_id' => $user->id,
            'title' => 'Chat Lama',
        ]);
        $newer = Conversation::create([
            'id' => '00000000-0000-0000-0000-000000000002',
            'user_id' => $user->id,
            'title' => 'Chat Terbaru',
        ]);

        $older->messages()->create([
            'id' => '00000000-0000-0000-0001-000000000001',
            'user_id' => $user->id,
            'agent' => OfficeAssistant::class,
            'role' => 'user',
            'content' => 'Pesan lama',
            'attachments' => '[]',
            'tool_calls' => '[]',
            'tool_results' => '[]',
            'usage' => '[]',
            'meta' => '[]',
        ]);

        $this->get(route('ai.assistant.index'))
            ->assertOk()
            ->assertSee('Chat Lama')
            ->assertSee('Chat Terbaru');

        $this->getJson(route('ai.assistant.conversations.show', $older))
            ->assertOk()
            ->assertJsonPath('conversation.title', 'Chat Lama')
            ->assertJsonPath('messages.0.content', 'Pesan lama');

        $this->assertNotNull($newer->id);
    }

    public function test_user_can_rename_and_delete_owned_conversation(): void
    {
        $user = $this->actingUser();
        $conversation = Conversation::create([
            'id' => '00000000-0000-0000-0000-000000000003',
            'user_id' => $user->id,
            'title' => 'Sebelum Rename',
        ]);

        $this->patchJson(route('ai.assistant.conversations.rename', $conversation), [
            'title' => 'Nama Baru',
        ])->assertOk()->assertJsonPath('conversation.title', 'Nama Baru');

        $this->deleteJson(route('ai.assistant.conversations.destroy', $conversation))
            ->assertOk()
            ->assertJsonPath('deleted', true);

        $this->assertDatabaseMissing('agent_conversations', ['id' => $conversation->id]);
    }

    public function test_conversation_actions_are_scoped_to_the_owner(): void
    {
        $owner = User::factory()->create();
        $conversation = Conversation::create([
            'id' => '00000000-0000-0000-0000-000000000004',
            'user_id' => $owner->id,
            'title' => 'Private Chat',
        ]);

        $this->actingAs(User::factory()->create())
            ->getJson(route('ai.assistant.conversations.show', $conversation))
            ->assertNotFound();
    }

    public function test_uploaded_attachment_is_stored_privately_and_removed_with_conversation(): void
    {
        Storage::fake('local');
        config()->set('ai.providers.openrouter.key', 'test-key');
        OfficeAssistant::fake(['Balasan dengan lampiran']);

        $this->actingUser();

        $response = $this->post(route('ai.assistant.send'), [
            'message' => 'Tolong lihat gambar ini',
            'attachments' => [UploadedFile::fake()->image('contoh.png')],
        ])->assertOk();

        $conversationId = $response->json('conversation_id');
        $message = ConversationMessage::where('conversation_id', $conversationId)
            ->where('role', 'user')
            ->firstOrFail();
        $attachment = $message->attachments[0];

        $this->assertSame('stored-image', $attachment['type']);
        Storage::disk('local')->assertExists($attachment['path']);

        $this->deleteJson(route('ai.assistant.conversations.destroy', $conversationId))
            ->assertOk();

        Storage::disk('local')->assertMissing($attachment['path']);
    }

    public function test_send_trims_invisible_trailing_whitespace(): void
    {
        config()->set('ai.providers.openrouter.key', 'test-key');
        OfficeAssistant::fake(['Balasan test']);

        $this->actingUser();

        $conversationId = $this->postJson(route('ai.assistant.send'), ['message' => "tes\n\n\n"])
            ->assertOk()
            ->json('conversation_id');

        $this->assertSame(
            'tes',
            ConversationMessage::where('conversation_id', $conversationId)
                ->where('role', 'user')
                ->firstOrFail()->content
        );
    }

    public function test_send_rejects_message_with_only_invisible_chars(): void
    {
        config()->set('ai.providers.openrouter.key', 'test-key');
        OfficeAssistant::fake(['Balasan test']);

        $this->actingUser();

        $this->postJson(route('ai.assistant.send'), ['message' => "​\n  " . "\u{200B}"])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('message');
    }

    public function test_user_message_text_keeps_template_whitespace_out_of_layout(): void
    {
        $this->actingUser();

        $html = $this->get(route('ai.assistant.index'))->assertOk()->getContent();

        // Indentasi markup tidak boleh ikut tampil: wadah teks user normal,
        // hanya isi pesan (ai-user-msg) yang pre-wrap.
        $this->assertStringContainsString('class="ai-user-msg"', $html);
        $this->assertMatchesRegularExpression('/\.ai-user-text\{[^}]*white-space:normal/', $html);
        $this->assertMatchesRegularExpression('/\.ai-user-msg\{[^}]*white-space:pre-wrap/', $html);
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Models\Conversation;
use Tests\TestCase;

class AiAssistantWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_widget_markup_is_present_on_app_layout_pages(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('aiChatV2(', false)
            ->assertSee('3DY AI', false);
    }

    public function test_widget_is_hidden_for_guests(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('aiChatV2(', false);
    }

    public function test_v2_embeds_owned_conversation_list(): void
    {
        $user = User::factory()->create();
        Conversation::create([
            'id' => '00000000-0000-0000-0000-000000000021',
            'user_id' => $user->id,
            'title' => 'Riset Pasar V2',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('aiChatV2(', false)
            ->assertSee('Riset Pasar V2', false);
    }

    public function test_existing_send_endpoint_still_accepts_text_message(): void
    {
        config()->set('ai.providers.openrouter.key', null);

        $user = User::factory()->create();

        // Tanpa API key endpoint menolak dengan 422 — kontrak tidak berubah.
        $this->actingAs($user)
            ->postJson(route('ai.assistant.send'), ['message' => 'Halo'])
            ->assertStatus(422);
    }
}

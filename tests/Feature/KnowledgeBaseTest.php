<?php

namespace Tests\Feature;

use App\Enums\KnowledgeBaseCategory;
use App\Models\KnowledgeBaseDocument;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Ai\Files;
use Laravel\Ai\Stores;
use Tests\TestCase;

class KnowledgeBaseTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function setStoreId(?string $storeId = 'store-test'): void
    {
        config()->set('ai.knowledge_base.store_id', $storeId);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('knowledge-base.index'))->assertRedirect(route('login'));
    }

    public function test_non_admin_cannot_access_knowledge_base(): void
    {
        $this->actingAs($this->userWithRole('marketing'))
            ->get(route('knowledge-base.index'))
            ->assertForbidden();
    }

    public function test_admin_can_view_knowledge_base_page(): void
    {
        $this->setStoreId();

        $this->actingAs($this->userWithRole('admin'))
            ->get(route('knowledge-base.index'))
            ->assertOk()
            ->assertSee('Knowledge Base');
    }

    public function test_upload_requires_configured_store(): void
    {
        $this->setStoreId(null);

        $this->actingAs($this->userWithRole('admin'))
            ->post(route('knowledge-base.store'), [
                'document' => UploadedFile::fake()->create('sop.pdf', 100, 'application/pdf'),
                'category' => 'sop',
            ])
            ->assertSessionHasErrors('document')
            ->assertSessionHas('errors');
    }

    public function test_upload_stores_document_and_indexes(): void
    {
        Stores::fake();
        $this->setStoreId();

        $this->actingAs($this->userWithRole('admin'))
            ->post(route('knowledge-base.store'), [
                'document' => UploadedFile::fake()->create('sop.pdf', 100, 'application/pdf'),
                'category' => 'sop',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('knowledge_base_documents', [
            'original_name' => 'sop.pdf',
            'category' => KnowledgeBaseCategory::Sop->value,
            'status' => 'indexing',
            'store_id' => 'store-test',
            'mime' => 'application/pdf',
        ]);

        Stores::get('store-test')->assertAdded(fn ($file) => $file->name() === 'sop.pdf');
    }

    public function test_upload_rejects_unsupported_mime_type(): void
    {
        Stores::fake();
        $this->setStoreId();

        $this->actingAs($this->userWithRole('admin'))
            ->post(route('knowledge-base.store'), [
                'document' => UploadedFile::fake()->create('virus.exe', 100, 'application/octet-stream'),
                'category' => 'sop',
            ])
            ->assertSessionHasErrors('document');

        $this->assertDatabaseCount('knowledge_base_documents', 0);
    }

    public function test_upload_rejects_html_documents(): void
    {
        Stores::fake();
        $this->setStoreId();

        $this->actingAs($this->userWithRole('admin'))
            ->post(route('knowledge-base.store'), [
                'document' => UploadedFile::fake()->create('page.html', 100, 'text/html'),
                'category' => 'sop',
            ])
            ->assertSessionHasErrors('document');

        $this->assertDatabaseCount('knowledge_base_documents', 0);
    }

    public function test_upload_rejects_invalid_category(): void
    {
        Stores::fake();
        $this->setStoreId();

        $this->actingAs($this->userWithRole('admin'))
            ->post(route('knowledge-base.store'), [
                'document' => UploadedFile::fake()->create('sop.pdf', 100, 'application/pdf'),
                'category' => 'bukan-kategori',
            ])
            ->assertSessionHasErrors('category');

        $this->assertDatabaseCount('knowledge_base_documents', 0);
    }

    public function test_sync_marks_indexing_documents_as_ready(): void
    {
        Stores::fake();
        $this->setStoreId();
        $admin = $this->userWithRole('admin');

        $document = KnowledgeBaseDocument::create([
            'original_name' => 'proposal.pdf',
            'category' => KnowledgeBaseCategory::Proposal->value,
            'user_id' => $admin->id,
            'store_id' => 'store-test',
            'provider_file_id' => 'files/abc123',
            'provider_document_id' => 'doc-abc123',
            'mime' => 'application/pdf',
            'size' => 100,
            'status' => 'indexing',
        ]);

        $this->actingAs($admin)
            ->post(route('knowledge-base.sync'))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('ready', $document->fresh()->status);
    }

    public function test_destroy_removes_document_from_provider_and_database(): void
    {
        Stores::fake();
        $this->setStoreId();
        $admin = $this->userWithRole('admin');

        $document = KnowledgeBaseDocument::create([
            'original_name' => 'laporan.pdf',
            'category' => KnowledgeBaseCategory::Reports->value,
            'user_id' => $admin->id,
            'store_id' => 'store-test',
            'provider_file_id' => 'files/xyz789',
            'provider_document_id' => 'doc-xyz789',
            'mime' => 'application/pdf',
            'size' => 100,
            'status' => 'ready',
        ]);

        $this->actingAs($admin)
            ->delete(route('knowledge-base.destroy', $document))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('knowledge_base_documents', ['id' => $document->id]);

        Stores::get('store-test')->assertRemoved(fn ($fileId) => $fileId === 'doc-xyz789');
        Files::assertDeleted('files/xyz789');
    }

    public function test_non_admin_cannot_upload_document(): void
    {
        Stores::fake();
        $this->setStoreId();

        $this->actingAs($this->userWithRole('sales'))
            ->post(route('knowledge-base.store'), [
                'document' => UploadedFile::fake()->create('sop.pdf', 100, 'application/pdf'),
                'category' => 'sop',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('knowledge_base_documents', 0);
    }
}
